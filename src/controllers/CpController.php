<?php

namespace csabourin\stamppassport\controllers;

use Craft;
use craft\helpers\UrlHelper;
use craft\db\Query;
use craft\web\Controller;
use csabourin\stamppassport\models\Settings;
use csabourin\stamppassport\Plugin;
use csabourin\stamppassport\records\ItemRecord;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class CpController extends Controller
{
    /**
     * Items listing page.
     */
    public function actionIndex(): Response
    {
        $items = Plugin::$plugin->items->getAllItems();

        return $this->renderTemplate('stamp-passport/items/index', [
            'items'        => $items,
            'settings'     => Plugin::$plugin->getSettings(),
            'allSites'     => Craft::$app->getSites()->getAllSites(),
        ]);
    }

    /**
     * Item edit / new page.
     */
    public function actionEdit(?int $itemId = null): Response
    {
        $sites = Craft::$app->getSites()->getAllSites();

        // Resolve the active site from the ?site=handle query param
        $siteHandle = Craft::$app->getRequest()->getQueryParam('site');
        $currentSite = $siteHandle
            ? Craft::$app->getSites()->getSiteByHandle($siteHandle)
            : null;
        if (!$currentSite) {
            $currentSite = Craft::$app->getSites()->getPrimarySite();
        }

        if ($itemId) {
            $item = Plugin::$plugin->items->getItemById($itemId);
            if (!$item) {
                throw new NotFoundHttpException('Item not found.');
            }
            $title = Craft::t('stamp-passport', 'Edit Item');
        } else {
            $item = null;
            $title = Craft::t('stamp-passport', 'New Item');
        }

        // Build content array keyed by siteId for the template
        $content = [];
        foreach ($sites as $site) {
            $content[$site->id] = [
                'title' => '',
                'description' => '',
                'linkUrl' => '',
                'linkEntryId' => null,
                'linkText' => '',
            ];
        }

        if ($item) {
            foreach ($item->contents as $c) {
                $content[$c->siteId] = [
                    'title' => $c->title ?? '',
                    'description' => $c->description ?? '',
                    'linkUrl' => $c->linkUrl ?? '',
                    'linkEntryId' => $c->linkEntryId ?? null,
                    'linkText' => $c->linkText ?? '',
                ];
            }
        }

        return $this->renderTemplate('stamp-passport/items/_edit', [
            'item' => $item,
            'content' => $content,
            'sites' => $sites,
            'currentSite' => $currentSite,
            'title' => $title,
        ]);
    }

    /**
     * Save item (POST).
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $id = $request->getBodyParam('itemId') ?: null;

        // Don't include sortOrder — service preserves existing value for updates
        // and auto-assigns for new records
        $attributes = [
            'latitude' => $request->getBodyParam('latitude') ?: null,
            'longitude' => $request->getBodyParam('longitude') ?: null,
            'imageId' => $request->getBodyParam('imageId')[0] ?? null,
            'enabled' => (bool)$request->getBodyParam('enabled', true),
        ];

        $content = $request->getBodyParam('content', []);

        $record = Plugin::$plugin->items->saveItem($attributes, $content, $id);

        if (!$record) {
            Craft::$app->getSession()->setError(Craft::t('stamp-passport', 'Could not save item.'));
            return null;
        }

        Craft::$app->getSession()->setNotice(Craft::t('stamp-passport', 'Item saved.'));

        // Redirect back preserving the active site
        $siteId = (int)$request->getBodyParam('siteId');
        $site = $siteId ? Craft::$app->getSites()->getSiteById($siteId) : null;
        $redirectParams = $site ? ['site' => $site->handle] : [];

        return $this->redirect(UrlHelper::cpUrl('stamp-passport/items/' . $record->id, $redirectParams));
    }

    /**
     * Delete item (POST).
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = Craft::$app->getRequest()->getRequiredBodyParam('id');

        if (Plugin::$plugin->items->deleteItem((int)$id)) {
            return $this->asJson(['success' => true]);
        }

        return $this->asJson(['success' => false]);
    }

    /**
     * Reorder items (POST, AJAX).
     */
    public function actionReorder(): Response
    {
        $this->requirePostRequest();

        $ids = Craft::$app->getRequest()->getRequiredBodyParam('ids');
        if (is_string($ids)) {
            $ids = json_decode($ids, true) ?? [];
        }
        Plugin::$plugin->items->reorder($ids);

        return $this->asJson(['success' => true]);
    }

    /**
     * QR code generator page.
     */
    public function actionQrGenerator(): Response
    {
        $items = Plugin::$plugin->items->getAllItems();
        $sites = Craft::$app->getSites()->getAllSites();

        return $this->renderTemplate('stamp-passport/qr-generator', [
            'items' => $items,
            'sites' => $sites,
            'settings' => Plugin::$plugin->getSettings(),
        ]);
    }

    /**
     * Save display text settings from the Items page (POST).
     */
    public function actionSaveDisplayText(): ?Response
    {
        $this->requirePostRequest();

        $request  = Craft::$app->getRequest();
        $settings = Plugin::$plugin->getSettings();

        // Start from current saved values so other settings are not clobbered
        $cleaned = $settings->uiText;
        $uiText  = $request->getBodyParam('uiText', []);
        if (is_array($uiText)) {
            foreach ($uiText as $handle => $texts) {
                if (!is_array($texts)) {
                    continue;
                }
                foreach ($texts as $key => $value) {
                    $value = trim((string)$value);
                    if ($value !== '') {
                        $cleaned[$handle][$key] = $value;
                    } else {
                        unset($cleaned[$handle][$key]);
                    }
                }
            }
        }
        $settings->uiText = $cleaned;

        Craft::$app->getPlugins()->savePluginSettings(Plugin::$plugin, $settings->toArray());
        Craft::$app->getSession()->setNotice(Craft::t('stamp-passport', 'Display text saved.'));

        return $this->redirectToPostedUrl();
    }


    /**
     * Display text page.
     */
    public function actionDisplayText(): Response
    {
        $siteHandle = Craft::$app->getRequest()->getQueryParam('site');
        $currentSite = $siteHandle
            ? (Craft::$app->getSites()->getSiteByHandle($siteHandle) ?? Craft::$app->getSites()->getPrimarySite())
            : Craft::$app->getSites()->getPrimarySite();

        return $this->renderTemplate('stamp-passport/text/index', [
            'settings'     => Plugin::$plugin->getSettings(),
            'allSites'     => Craft::$app->getSites()->getAllSites(),
            'currentSite'  => $currentSite,
            'textDefaults' => Settings::TEXT_DEFAULTS,
            'textLabels'   => Settings::TEXT_LABELS,
            'textKeys'     => Settings::TEXT_KEYS,
        ]);
    }

    /**
     * Stats dashboard page.
     */
    public function actionStats(): Response
    {
        $request = Craft::$app->getRequest();
        $siteHandle = $request->getQueryParam('site');
        $dateFrom   = (string)($request->getQueryParam('dateFrom') ?? '');
        $dateTo     = (string)($request->getQueryParam('dateTo')   ?? '');

        $currentSite = $siteHandle
            ? (Craft::$app->getSites()->getSiteByHandle($siteHandle) ?? Craft::$app->getSites()->getPrimarySite())
            : Craft::$app->getSites()->getPrimarySite();

        // Build shortcode → title map for the selected site
        $itemRows = (new Query())
            ->select(['i.shortCode', 'ic.title'])
            ->from(['i' => '{{%stamppassport_items}}'])
            ->leftJoin(['ic' => '{{%stamppassport_items_content}}'], '[[ic.itemId]] = [[i.id]] AND [[ic.siteId]] = ' . $currentSite->id)
            ->all();

        $shortCodeToTitle = [];
        foreach ($itemRows as $itemRow) {
            $shortCodeToTitle[(string)$itemRow['shortCode']] = (string)($itemRow['title'] ?? $itemRow['shortCode']);
        }

        // Total enabled items (used to determine sticker qualification)
        $totalItemsCount = (int)(new Query())
            ->from('{{%stamppassport_items}}')
            ->where(['enabled' => true])
            ->count();

        // Build query with optional date range
        $query = (new Query())->from('{{%stamppassport_contest_progress}}');
        if ($dateFrom !== '') {
            $query->andWhere(['>=', 'updated_at', $dateFrom . ' 00:00:00']);
        }
        if ($dateTo !== '') {
            $query->andWhere(['<=', 'updated_at', $dateTo . ' 23:59:59']);
        }
        $rows = $query->all();

        // Reference end date for rolling windows (last 7 / last 30 days)
        $endDate      = $dateTo !== '' ? new \DateTime($dateTo) : new \DateTime('today');
        $last7Start   = (clone $endDate)->modify('-6 days')->format('Y-m-d');
        $last30Start  = (clone $endDate)->modify('-29 days')->format('Y-m-d');

        // Pre-fill day arrays with zeroes so gaps render correctly in charts
        $last7Days  = [];
        $last30Days = [];
        $d = new \DateTime($last7Start);
        while ($d->format('Y-m-d') <= $endDate->format('Y-m-d')) {
            $last7Days[$d->format('Y-m-d')] = 0;
            $d->modify('+1 day');
        }
        $d = new \DateTime($last30Start);
        while ($d->format('Y-m-d') <= $endDate->format('Y-m-d')) {
            $last30Days[$d->format('Y-m-d')] = 0;
            $d->modify('+1 day');
        }

        $weekdayKeys = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $weekdayCounts = array_fill_keys($weekdayKeys, 0);

        $locationCounts   = []; // code  => total_scans
        $locationWeekdays = []; // code  => [weekday => count]
        $locationLast7    = []; // code  => [date => count]
        $locationLast30   = []; // code  => [date => count]

        $totalScans    = 0;
        $qualifyDraw   = 0;
        $qualifyStickers = 0;
        $drawThreshold = Plugin::$plugin->getSettings()->drawThreshold;

        foreach ($rows as $row) {
            $payload = json_decode((string)$row['payload_json'], true);
            $steps   = $payload['progress']['stepsCompleted'] ?? [];
            if (!is_array($steps)) {
                $steps = [];
            }

            $stepCount   = count($steps);
            $totalScans += $stepCount;

            if ($stepCount >= $drawThreshold) {
                $qualifyDraw++;
            }
            if ($totalItemsCount > 0 && $stepCount >= $totalItemsCount) {
                $qualifyStickers++;
            }

            $ts      = strtotime((string)$row['updated_at']);
            $date    = date('Y-m-d', $ts);
            $weekday = date('l', $ts);

            if (isset($weekdayCounts[$weekday])) {
                $weekdayCounts[$weekday]++;
            }
            if (isset($last7Days[$date])) {
                $last7Days[$date]++;
            }
            if (isset($last30Days[$date])) {
                $last30Days[$date]++;
            }

            foreach ($steps as $step) {
                $code = (string)$step;
                if ($code === '') {
                    continue;
                }

                $locationCounts[$code] = ($locationCounts[$code] ?? 0) + 1;

                $locationWeekdays[$code][$weekday] = ($locationWeekdays[$code][$weekday] ?? 0) + 1;

                if (isset($last7Days[$date])) {
                    $locationLast7[$code][$date] = ($locationLast7[$code][$date] ?? 0) + 1;
                }
                if (isset($last30Days[$date])) {
                    $locationLast30[$code][$date] = ($locationLast30[$code][$date] ?? 0) + 1;
                }
            }
        }

        arsort($locationCounts);

        // Convert shortcodes to titles and build per-location chart data
        $locationCountsByTitle = [];
        $locationDataByTitle   = [];
        foreach ($locationCounts as $code => $count) {
            $title = $shortCodeToTitle[$code] ?? $code;
            $locationCountsByTitle[$title] = $count;

            $lw = $locationWeekdays[$code] ?? [];
            $weekdayArr = [];
            foreach ($weekdayKeys as $wd) {
                $weekdayArr[$wd] = $lw[$wd] ?? 0;
            }

            $l7 = $locationLast7[$code] ?? [];
            $l7Full = [];
            foreach (array_keys($last7Days) as $d7) {
                $l7Full[$d7] = $l7[$d7] ?? 0;
            }

            $l30 = $locationLast30[$code] ?? [];
            $l30Full = [];
            foreach (array_keys($last30Days) as $d30) {
                $l30Full[$d30] = $l30[$d30] ?? 0;
            }

            $locationDataByTitle[$title] = [
                'weekdays' => $weekdayArr,
                'last7'    => $l7Full,
                'last30'   => $l30Full,
            ];
        }

        // Most visited location
        $mostVisitedCode  = !empty($locationCounts) ? (string)array_key_first($locationCounts) : null;
        $mostVisitedTitle = $mostVisitedCode !== null ? ($shortCodeToTitle[$mostVisitedCode] ?? $mostVisitedCode) : null;
        $mostVisitedCount = $mostVisitedCode !== null ? $locationCounts[$mostVisitedCode] : 0;

        // Avg visits per site (locations with at least 1 scan)
        $numSitesWithVisits = count($locationCounts);
        $avgVisitsPerSite   = $numSitesWithVisits > 0 ? round($totalScans / $numSitesWithVisits, 1) : 0;

        $totalVisitors      = count($rows);
        $avgScansPerVisitor = $totalVisitors > 0 ? round($totalScans / $totalVisitors, 1) : 0;

        return $this->renderTemplate('stamp-passport/stats/index', [
            'settings'           => Plugin::$plugin->getSettings(),
            'allSites'           => Craft::$app->getSites()->getAllSites(),
            'currentSite'        => $currentSite,
            'dateFrom'           => $dateFrom,
            'dateTo'             => $dateTo,
            'totalScans'         => $totalScans,
            'totalVisitors'      => $totalVisitors,
            'avgScansPerVisitor' => $avgScansPerVisitor,
            'avgVisitsPerSite'   => $avgVisitsPerSite,
            'mostVisitedSite'    => $mostVisitedTitle,
            'mostVisitedCount'   => $mostVisitedCount,
            'qualifyDraw'        => $qualifyDraw,
            'qualifyStickers'    => $qualifyStickers,
            'totalItemsCount'    => $totalItemsCount,
            'locationCounts'     => $locationCountsByTitle,
            'weekdayCounts'      => $weekdayCounts,
            'visitsByDayLast7'   => $last7Days,
            'visitsByDayLast30'  => $last30Days,
            'locationDataByTitle'=> $locationDataByTitle,
            // Legacy aliases kept for backward compatibility
            'totalIndividuals'   => $totalVisitors,
            'avgLocations'       => $avgScansPerVisitor,
        ]);
    }

    /**
     * Contest rules page.
     */
    public function actionContestRules(): Response
    {
        $siteHandle = Craft::$app->getRequest()->getQueryParam('site');
        $currentSite = $siteHandle
            ? (Craft::$app->getSites()->getSiteByHandle($siteHandle) ?? Craft::$app->getSites()->getPrimarySite())
            : Craft::$app->getSites()->getPrimarySite();

        return $this->renderTemplate('stamp-passport/contest-rules', [
            'settings'     => Plugin::$plugin->getSettings(),
            'contestRules' => Plugin::$plugin->getSettings()->contestRules,
            'allSites'     => Craft::$app->getSites()->getAllSites(),
            'currentSite'  => $currentSite,
        ]);
    }

    /**
     * Save contest rules (POST).
     */
    public function actionSaveContestRules(): ?Response
    {
        $this->requirePostRequest();

        $request  = Craft::$app->getRequest();
        $settings = Plugin::$plugin->getSettings();

        $raw     = $request->getBodyParam('contestRules', []);
        $cleaned = [];

        if (is_array($raw)) {
            foreach ($raw as $handle => $fields) {
                if (!is_array($fields)) {
                    continue;
                }
                $linkText      = trim((string)($fields['linkText'] ?? ''));
                $modalContent  = trim((string)($fields['modalContent'] ?? ''));
                $fullRulesText = trim((string)($fields['fullRulesText'] ?? ''));
                $fullRulesEntryId = (int)($fields['fullRulesEntryId'][0] ?? 0);

                // Only store entries that have at least a link label
                if ($linkText !== '') {
                    $cleaned[$handle] = [
                        'linkText'      => $linkText,
                        'modalContent'  => $modalContent,
                        'fullRulesText' => $fullRulesText,
                        'fullRulesEntryId' => $fullRulesEntryId ?: null,
                    ];
                }
            }
        }

        $settings->contestRules = $cleaned;

        Craft::$app->getPlugins()->savePluginSettings(Plugin::$plugin, $settings->toArray());
        Craft::$app->getSession()->setNotice(Craft::t('stamp-passport', 'Contest rules saved.'));

        return $this->redirectToPostedUrl();
    }

    /**
     * Plugin settings page.
     */
    public function actionSettings(): Response
    {
        return $this->renderTemplate('stamp-passport/settings', [
            'settings'     => Plugin::$plugin->getSettings(),
            'freeformForms' => Plugin::$plugin->getFreeformFormOptions(),
        ]);
    }

    /**
     * Save plugin settings (POST).
     */
    public function actionSaveSettings(): ?Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $settings = Plugin::$plugin->getSettings();

        $settings->pluginName = $request->getBodyParam('pluginName', $settings->pluginName);
        $settings->routePrefix = $request->getBodyParam('routePrefix', $settings->routePrefix);

        $rawPrefixes = $request->getBodyParam('siteRoutePrefixes', []);
        if (is_array($rawPrefixes)) {
            $cleanedPrefixes = [];
            foreach ($rawPrefixes as $handle => $prefix) {
                $prefix = trim((string)$prefix, '/');
                if ($prefix !== '') {
                    $cleanedPrefixes[(string)$handle] = $prefix;
                }
            }
            $settings->siteRoutePrefixes = $cleanedPrefixes;
        }
        $settings->enableGeofence = (bool)$request->getBodyParam('enableGeofence');
        $settings->geofenceRadius = (int)$request->getBodyParam('geofenceRadius', 550);
        $settings->ga4MeasurementId = $request->getBodyParam('ga4MeasurementId') ?: null;
        $settings->drawThreshold = (int)$request->getBodyParam('drawThreshold', 5);
        $settings->maxStickers = (int)$request->getBodyParam('maxStickers', 100);
        $settings->freeformDrawFormHandle = $request->getBodyParam('freeformDrawFormHandle') ?: null;
        $settings->freeformStickerFormHandle = $request->getBodyParam('freeformStickerFormHandle') ?: null;

        $logoIds = $request->getBodyParam('logoAssetId');
        $settings->logoAssetId = is_array($logoIds) ? ((int)($logoIds[0] ?? 0) ?: null) : ((int)($logoIds ?: 0) ?: null);

        $logoAltIds = $request->getBodyParam('logoAltAssetId');
        $settings->logoAltAssetId = is_array($logoAltIds) ? ((int)($logoAltIds[0] ?? 0) ?: null) : ((int)($logoAltIds ?: 0) ?: null);

        $woodIds = $request->getBodyParam('woodPanelAssetId');
        $settings->woodPanelAssetId = is_array($woodIds) ? ((int)($woodIds[0] ?? 0) ?: null) : ((int)($woodIds ?: 0) ?: null);

        $checkedMarkerIds = $request->getBodyParam('checkedMarkerAssetId');
        $settings->checkedMarkerAssetId = is_array($checkedMarkerIds)
            ? ((int)($checkedMarkerIds[0] ?? 0) ?: null)
            : ((int)($checkedMarkerIds ?: 0) ?: null);

        $bodyBackgroundIds = $request->getBodyParam('bodyBackgroundAssetId');
        $settings->bodyBackgroundAssetId = is_array($bodyBackgroundIds)
            ? ((int)($bodyBackgroundIds[0] ?? 0) ?: null)
            : ((int)($bodyBackgroundIds ?: 0) ?: null);

        $bodyBackgroundMode = (string)$request->getBodyParam('bodyBackgroundMode', $settings->bodyBackgroundMode);
        $settings->bodyBackgroundMode = in_array($bodyBackgroundMode, ['cover', 'tiled', 'custom', 'repeat-y'], true) ? $bodyBackgroundMode : 'cover';

        $bodyBackgroundSize = trim((string)$request->getBodyParam('bodyBackgroundSize', $settings->bodyBackgroundSize));
        $settings->bodyBackgroundSize = $bodyBackgroundSize !== '' ? $bodyBackgroundSize : '800px';

        $bodyBackgroundColor = trim((string)$request->getBodyParam('bodyBackgroundColor', ''));
        // Strip any characters not valid in CSS colors; normalize bare hex to include '#'.
        $bodyBackgroundColor = preg_replace('/[^#0-9a-fA-F()%.,\- ]/', '', $bodyBackgroundColor);
        if ($bodyBackgroundColor !== '' && $bodyBackgroundColor[0] !== '#' && ctype_xdigit($bodyBackgroundColor)) {
            $bodyBackgroundColor = '#' . $bodyBackgroundColor;
        }
        $settings->bodyBackgroundColor = $bodyBackgroundColor !== '' ? $bodyBackgroundColor : null;

        $qrCenterIds = $request->getBodyParam('qrCenterImageAssetId');
        $settings->qrCenterImageAssetId = is_array($qrCenterIds)
            ? ((int)($qrCenterIds[0] ?? 0) ?: null)
            : ((int)($qrCenterIds ?: 0) ?: null);

        // ── Brand Colors ──────────────────────────────────────────────────────
        foreach (['primaryColor', 'primaryColorDark', 'accentColor'] as $colorField) {
            $val = trim((string)$request->getBodyParam($colorField, ''));
            $val = preg_replace('/[^#0-9a-fA-F]/', '', $val);
            if ($val !== '' && $val[0] !== '#') {
                $val = '#' . $val;
            }
            $settings->$colorField = $val !== '' ? $val : null;
        }

        // ── OG Image & Favicon ────────────────────────────────────────────────
        $ogImageIds = $request->getBodyParam('ogImageAssetId');
        $settings->ogImageAssetId = is_array($ogImageIds)
            ? ((int)($ogImageIds[0] ?? 0) ?: null)
            : ((int)($ogImageIds ?: 0) ?: null);

        $faviconIds = $request->getBodyParam('faviconAssetId');
        $settings->faviconAssetId = is_array($faviconIds)
            ? ((int)($faviconIds[0] ?? 0) ?: null)
            : ((int)($faviconIds ?: 0) ?: null);

        // ── Custom CSS ────────────────────────────────────────────────────────
        $customCss = (string)$request->getBodyParam('customCss', '');
        // Neutralize </style> injection vectors before storing.
        $customCss = str_replace('</style', '<\/style', $customCss);
        $customCss = mb_substr($customCss, 0, 10000);
        $settings->customCss = $customCss !== '' ? $customCss : null;

        // ── UI Behavior Flags ─────────────────────────────────────────────────
        // Craft lightswitches POST '1' when on and '' when off.
        $settings->showLanguageSwitcher = (bool)$request->getBodyParam('showLanguageSwitcher', true);
        $settings->requireDisclaimerAck = (bool)$request->getBodyParam('requireDisclaimerAck', true);
        $settings->showOrgName = (bool)$request->getBodyParam('showOrgName', true);

        // ── QR Code Colors ────────────────────────────────────────────────────
        foreach (['qrForegroundColor', 'qrBackgroundColor'] as $colorField) {
            $val = trim((string)$request->getBodyParam($colorField, ''));
            $val = preg_replace('/[^#0-9a-fA-F]/', '', $val);
            if ($val !== '' && $val[0] !== '#') {
                $val = '#' . $val;
            }
            $settings->$colorField = $val !== '' ? $val : null;
        }

        // Preserve existing display text unless this payload is explicitly submitted.
        $uiText = $request->getBodyParam('uiText', null);
        if (is_array($uiText)) {
            $cleaned = [];
            foreach ($uiText as $handle => $texts) {
                if (!is_array($texts)) {
                    continue;
                }
                foreach ($texts as $key => $value) {
                    $value = trim((string)$value);
                    if ($value !== '') {
                        $cleaned[$handle][$key] = $value;
                    }
                }
            }
            $settings->uiText = $cleaned;
        }

        if (!$settings->validate()) {
            Craft::$app->getSession()->setError(Craft::t('stamp-passport', 'Settings not saved.'));
            return null;
        }

        Craft::$app->getPlugins()->savePluginSettings(Plugin::$plugin, $settings->toArray());
        Craft::$app->getSession()->setNotice(Craft::t('stamp-passport', 'Settings saved.'));

        return $this->redirectToPostedUrl();
    }
}
