<?php

namespace csabourin\stamppassport\services;

use Craft;
use craft\base\Component;
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
     * @param array $attributes  Item-level attributes (latitude, longitude, imageId, enabled, sortOrder)
     * @param array $content     Per-site content keyed by siteId: [siteId => [title, description, linkUrl, linkText]]
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
        $record->enabled = $attributes['enabled'] ?? true;
        // For existing records, only change sortOrder if explicitly provided
        if ($id && array_key_exists('sortOrder', $attributes)) {
            $record->sortOrder = $attributes['sortOrder'];
        }

        if (!$record->save()) {
            return false;
        }

        // Save per-site content
        foreach ($content as $siteId => $fields) {
            $contentRecord = ItemContentRecord::findOne([
                'itemId' => $record->id,
                'siteId' => $siteId,
            ]);

            if (!$contentRecord) {
                $contentRecord = new ItemContentRecord();
                $contentRecord->itemId = $record->id;
                $contentRecord->siteId = (int)$siteId;
            }

            $contentRecord->title = $fields['title'] ?? null;
            $contentRecord->description = $fields['description'] ?? null;
            $contentRecord->linkUrl = $fields['linkUrl'] ?? null;
            $contentRecord->linkText = $fields['linkText'] ?? null;

            if (!$contentRecord->save()) {
                return false;
            }
        }

        return $record;
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
        foreach ($ids as $order => $id) {
            Craft::$app->getDb()->createCommand()
                ->update('{{%stamppassport_items}}', ['sortOrder' => $order], ['id' => $id])
                ->execute();
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
        do {
            $code = strtolower(StringHelper::randomString(8));
        } while (ItemRecord::find()->where(['shortCode' => $code])->exists());

        return $code;
    }

    private function _getNextSortOrder(): int
    {
        $max = ItemRecord::find()->max('sortOrder');
        return ($max ?? -1) + 1;
    }
}
