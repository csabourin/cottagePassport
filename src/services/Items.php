<?php

namespace csabourin\stamppassport\services;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\elements\Entry;
use craft\helpers\StringHelper;
use csabourin\stamppassport\records\ItemRecord;
use csabourin\stamppassport\records\ItemContentRecord;

class Items extends Component
{
    /**
     * Return all items ordered by sortOrder, with content for the given site.
     */
    public function getAllItems(?int $siteId = null): array
    {
        $siteId = $siteId ?? Craft::$app->getSites()->getCurrentSite()->id;

        return ItemRecord::find()
            ->with(['contents' => function ($query) use ($siteId) {
                $query->andWhere(['siteId' => $siteId]);
            }])
            ->orderBy(['sortOrder' => SORT_ASC])
            ->all();
    }

    /**
     * Return enabled items only, with content for the given site.
     */
    public function getEnabledItems(?int $siteId = null): array
    {
        $siteId = $siteId ?? Craft::$app->getSites()->getCurrentSite()->id;

        return ItemRecord::find()
            ->where(['enabled' => true])
            ->with(['contents' => function ($query) use ($siteId) {
                $query->andWhere(['siteId' => $siteId]);
            }])
            ->orderBy(['sortOrder' => SORT_ASC])
            ->all();
    }

    /**
     * Get a single item by ID with all site content.
     */
    public function getItemById(int $id): ?ItemRecord
    {
        return ItemRecord::find()
            ->where(['id' => $id])
            ->with('contents')
            ->one();
    }

    /**
     * Get a single item by its short code.
     */
    public function getItemByShortCode(string $shortCode): ?ItemRecord
    {
        return ItemRecord::find()
            ->where(['shortCode' => $shortCode])
            ->with('contents')
            ->one();
    }

    /**
     * Save an item and its per-site content.
     *
     * @param array $attributes  Item-level attributes (latitude, longitude, imageId, qrCenterImageAssetId, enabled, sortOrder)
     * @param array $content     Per-site content keyed by siteId: [siteId => [title, description, linkUrl, linkEntryId, linkText]]
     * @param int|null $id       Existing item ID for updates, null for new items
     * @return ItemRecord|false  The saved record or false on failure
     */
    public function saveItem(array $attributes, array $content, ?int $id = null): ItemRecord|false
    {
        if ($id) {
            $record = ItemRecord::findOne($id);
            if (!$record) {
                return false;
            }
        } else {
            $record = new ItemRecord();
            $record->shortCode = $this->_generateShortCode();
            // Assign sort order only for new records
            $record->sortOrder = $attributes['sortOrder'] ?? $this->_getNextSortOrder();
        }

        $record->latitude = $attributes['latitude'] ?? null;
        $record->longitude = $attributes['longitude'] ?? null;
        $record->imageId = $attributes['imageId'] ?? null;
        $record->qrCenterImageAssetId = $attributes['qrCenterImageAssetId'] ?? null;
        $record->enabled = $attributes['enabled'] ?? true;
        // For existing records, only change sortOrder if explicitly provided
        if ($id && array_key_exists('sortOrder', $attributes)) {
            $record->sortOrder = $attributes['sortOrder'];
        }

        // Reject any content keys that don't map to a real site — prevents orphaned
        // rows and stops callers from writing content to non-existent or inaccessible sites.
        $validSiteIds = array_map(
            static fn($s) => $s->id,
            Craft::$app->getSites()->getAllSites()
        );
        $content = array_filter(
            $content,
            static fn($siteId) => is_numeric($siteId) && in_array((int)$siteId, $validSiteIds, true),
            ARRAY_FILTER_USE_KEY
        );

        $db = Craft::$app->getDb();

        // For new records, retry a few times on a shortCode collision (unique DB index is
        // the authoritative guard; the retry closes the TOCTOU window between exists() and INSERT).
        $maxAttempts = $id ? 1 : 3;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            if (!$id && $attempt > 0) {
                $record->shortCode = $this->_generateShortCode();
            }

            $transaction = $db->beginTransaction();

            try {
                if (!$record->save()) {
                    $transaction->rollBack();
                    Craft::error('Item save failed: ' . print_r($record->getErrors(), true), __METHOD__);
                    return false;
                }

                // Save per-site content
                foreach ($content as $siteId => $fields) {
                    $siteId = (int)$siteId;
                    $contentRecord = ItemContentRecord::findOne([
                        'itemId' => $record->id,
                        'siteId' => $siteId,
                    ]);

                    if (!$contentRecord) {
                        $contentRecord = new ItemContentRecord();
                        $contentRecord->itemId = $record->id;
                        $contentRecord->siteId = $siteId;
                    }

                    $contentRecord->title = $fields['title'] ?? null;
                    $contentRecord->description = $fields['description'] ?? null;
                    $contentRecord->linkUrl = $fields['linkUrl'] ?? null;
                    $rawLinkEntryId = $fields['linkEntryId'] ?? null;
                    if (is_array($rawLinkEntryId)) {
                        $rawLinkEntryId = $rawLinkEntryId[0] ?? null;
                    }
                    $contentRecord->linkEntryId = ((int)$rawLinkEntryId ?: null);
                    $contentRecord->linkText = $fields['linkText'] ?? null;

                    if (!$contentRecord->save()) {
                        $transaction->rollBack();
                        return false;
                    }
                }

                $transaction->commit();
                return $record;

            } catch (\yii\db\IntegrityException $e) {
                $transaction->rollBack();
                // If this is a new record and we have retries left, regenerate the short code
                if (!$id && $attempt < $maxAttempts - 1) {
                    continue;
                }
                Craft::error('Failed to save item (integrity constraint): ' . $e->getMessage(), __METHOD__);
                return false;
            } catch (\Throwable $e) {
                $transaction->rollBack();
                Craft::error('Failed to save item: ' . $e->getMessage(), __METHOD__);
                return false;
            }
        }

        return false;
    }

    /**
     * Return a map of item ID → linkEntryId across all sites.
     *
     * Prefers the given site's value so current-site overrides still work;
     * falls back to any other site so integrators only need to set the
     * linked entry once and all languages resolve their own version.
     *
     * @param int[] $itemIds
     * @param int   $preferredSiteId
     * @return array<int,int>
     */
    public function getLinkEntryIdMap(array $itemIds, int $preferredSiteId): array
    {
        if (empty($itemIds)) {
            return [];
        }

        $rows = (new Query())
            ->from('{{%stamppassport_items_content}}')
            ->select(['itemId', 'siteId', 'linkEntryId'])
            ->where(['itemId' => $itemIds])
            ->andWhere(['not', ['linkEntryId' => null]])
            ->all();

        $map = [];
        foreach ($rows as $row) {
            $itemId  = (int)$row['itemId'];
            $siteId  = (int)$row['siteId'];
            $entryId = (int)$row['linkEntryId'];

            // Prefer the current site's value; accept any other site as fallback.
            if (!isset($map[$itemId]) || $siteId === $preferredSiteId) {
                $map[$itemId] = $entryId;
            }
        }

        return $map;
    }

    /**
     * Return a map of item ID → resolved linked-entry URL for the given site.
     *
     * Resolves each item's effective linkEntryId (current site preferred, any
     * other site as fallback) to that entry's URL in the given site. Entry
     * element IDs are shared across sites, so integrators only need to select
     * the linked entry once and every language resolves its own localized URL.
     *
     * Built in PHP (not Twig) because Twig's |merge filter renumbers integer
     * array keys, which silently breaks an item-ID/entry-ID keyed lookup.
     *
     * @param int[]    $itemIds
     * @param int|null $siteId
     * @return array<int,string>  item ID => URL
     */
    public function getLinkEntryUrlMap(array $itemIds, ?int $siteId = null): array
    {
        $siteId = $siteId ?? Craft::$app->getSites()->getCurrentSite()->id;

        $entryIdByItem = $this->getLinkEntryIdMap($itemIds, $siteId);
        if (empty($entryIdByItem)) {
            return [];
        }

        $urlByEntryId = [];
        $entryIds = array_values(array_unique($entryIdByItem));
        foreach (Entry::find()->id($entryIds)->siteId($siteId)->all() as $entry) {
            $url = $entry->getUrl();
            if ($url) {
                $urlByEntryId[(int)$entry->id] = $url;
            }
        }

        $map = [];
        foreach ($entryIdByItem as $itemId => $entryId) {
            if (isset($urlByEntryId[$entryId])) {
                $map[(int)$itemId] = $urlByEntryId[$entryId];
            }
        }

        return $map;
    }

    /**
     * Delete an item and its content (cascaded by FK).
     */
    public function deleteItem(int $id): bool
    {
        $record = ItemRecord::findOne($id);
        if (!$record) {
            return false;
        }

        return (bool)$record->delete();
    }

    /**
     * Reorder items by an array of IDs.
     */
    public function reorder(array $ids): bool
    {
        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();
        try {
            foreach ($ids as $order => $id) {
                $db->createCommand()
                    ->update('{{%stamppassport_items}}', ['sortOrder' => (int)$order], ['id' => (int)$id])
                    ->execute();
            }
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            Craft::error('Failed to reorder items: ' . $e->getMessage(), __METHOD__);
            return false;
        }
        return true;
    }

    /**
     * Validate geofence for a given short code and user coordinates.
     *
     * @return array{ok: bool, distance: float, allowedRadius: int, item: ItemRecord|null}
     */
    public function validateGeofence(string $shortCode, float $lat, float $lng): array
    {
        $settings = \csabourin\stamppassport\Plugin::$plugin->getSettings();
        $item = $this->getItemByShortCode($shortCode);

        if (!$item || !$item->enabled) {
            return ['ok' => false, 'distance' => 0, 'allowedRadius' => 0, 'item' => null];
        }

        if (!$settings->enableGeofence) {
            return ['ok' => true, 'distance' => 0, 'allowedRadius' => 0, 'item' => $item];
        }

        if ($item->latitude === null || $item->longitude === null) {
            // No coordinates set — allow check-in without geofence
            return ['ok' => true, 'distance' => 0, 'allowedRadius' => $settings->geofenceRadius, 'item' => $item];
        }

        $distance = $this->_haversine($lat, $lng, (float)$item->latitude, (float)$item->longitude);
        $ok = $distance <= $settings->geofenceRadius;

        return [
            'ok' => $ok,
            'distance' => round($distance),
            'allowedRadius' => $settings->geofenceRadius,
            'item' => $item,
        ];
    }

    /**
     * Haversine formula — returns distance in metres between two lat/lng points.
     */
    private function _haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R = 6371000; // Earth radius in metres
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $R * $c;
    }

    private function _generateShortCode(): string
    {
        $maxAttempts = 20;
        for ($i = 0; $i < $maxAttempts; $i++) {
            $code = strtolower(StringHelper::randomString(8));
            if (!ItemRecord::find()->where(['shortCode' => $code])->exists()) {
                return $code;
            }
        }

        throw new \RuntimeException('Failed to generate a unique short code after ' . $maxAttempts . ' attempts.');
    }

    private function _getNextSortOrder(): int
    {
        $max = ItemRecord::find()->max('sortOrder');
        return ($max ?? -1) + 1;
    }
}
