<?php

namespace csabourin\stamppassport\controllers;

use Craft;
use craft\helpers\UrlHelper;
use craft\db\Query;
use craft\web\Controller;
use csabourin\stamppassport\models\Settings;
use csabourin\stamppassport\Plugin;
use csabourin\stamppassport\records\ItemRecord;
use csabourin\stamppassport\services\Draw;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class CpController extends Controller
{
    public function beforeAction($action): bool
    {
        $this->requirePermission('stampPassport:manage');
        return parent::beforeAction($action);
    }

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
        $imageIds = $request->getBodyParam('imageId');
        $qrCenterImageAssetIds = $request->getBodyParam('qrCenterImageAssetId');
        $attributes = [
            'latitude' => $request->getBodyParam('latitude') ?: null,
            'longitude' => $request->getBodyParam('longitude') ?: null,
            'imageId' => is_array($imageIds)
                ? ((int)($imageIds[0] ?? 0) ?: null)
                : ((int)($imageIds ?: 0) ?: null),
            'qrCenterImageAssetId' => is_array($qrCenterImageAssetIds)
                ? ((int)($qrCenterImageAssetIds[0] ?? 0) ?: null)
                : ((int)($qrCenterImageAssetIds ?: 0) ?: null),
            'enabled' => (bool)$request->getBodyParam('enabled', true),
        ];

        $content = $request->getBodyParam('content', []);

        $record = Plugin::$plugin->items->saveItem($attributes, $content, $id);

        if (!$record) {
            Craft::$app->getSession()->setError(Craft::t('stamp-passport', 'Could not save item.'));
            return null;
        }

        Craft::$app->getSession()->setNotice(Craft::t('stamp-passport', 'Item saved.'));

        // If site-switching was requested, redirect to the target site; otherwise preserve the active site.
        $switchToSiteHandle = $request->getBodyParam('switchToSite');
        if ($switchToSiteHandle) {
            $targetSite = Craft::$app->getSites()->getSiteByHandle($switchToSiteHandle);
            $redirectParams = $targetSite ? ['site' => $targetSite->handle] : [];
        } else {
            $siteId = (int)$request->getBodyParam('siteId');
            $site = $siteId ? Craft::$app->getSites()->getSiteById($siteId) : null;
            $redirectParams = $site ? ['site' => $site->handle] : [];
        }

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
        if (!is_array($ids) || count($ids) > 500) {
            return $this->asJson(['success' => false]);
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
        $validSiteHandles = array_map(
            static fn($s) => $s->handle,
            Craft::$app->getSites()->getAllSites()
        );
        if (is_array($uiText)) {
            foreach ($uiText as $handle => $texts) {
                if (!is_string($handle) || !in_array($handle, $validSiteHandles, true)) {
                    continue;
                }
                if (!is_array($texts)) {
                    continue;
                }
                foreach ($texts as $key => $value) {
                    if (!in_array($key, Settings::TEXT_KEYS, true)) {
                        continue;
                    }
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

        if (!$settings->validate()) {
            Craft::$app->getSession()->setError(Craft::t('stamp-passport', 'Could not save display text.'));
            return null;
        }

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

        // Silently discard malformed or impossible calendar dates.
        $validateDate = static function (string $val): bool {
            $dt = \DateTime::createFromFormat('Y-m-d', $val);
            $err = \DateTime::getLastErrors();
            return $dt !== false && (!$err || ($err['warning_count'] === 0 && $err['error_count'] === 0));
        };
        if ($dateFrom !== '' && !$validateDate($dateFrom)) {
            $dateFrom = '';
        }
        if ($dateTo !== '' && !$validateDate($dateTo)) {
            $dateTo = '';
        }

        $settings = Plugin::$plugin->getSettings();

        // Build shortcode → title map for the selected site.
        $itemRows = (new Query())
            ->select(['i.shortCode', 'ic.title'])
            ->from(['i' => '{{%stamppassport_items}}'])
            ->leftJoin(
                ['ic' => '{{%stamppassport_items_content}}'],
                ['and', '[[ic.itemId]] = [[i.id]]', ['[[ic.siteId]]' => $currentSite->id]]
            )
            ->all();

        $shortCodeToTitle = [];
        foreach ($itemRows as $row) {
            $shortCodeToTitle[(string)$row['shortCode']] = (string)($row['title'] ?? $row['shortCode']);
        }

        $totalItemsCount = (int)(new Query())
            ->from('{{%stamppassport_items}}')
            ->where(['enabled' => true])
            ->count();

        $stats = Plugin::$plugin->contestProgress->getStats(
            $dateFrom,
            $dateTo,
            $settings->drawThreshold,
            $totalItemsCount
        );

        // Convert shortcodes to titles and build per-location chart data.
        $weekdayKeys = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $locationCountsByTitle = [];
        $locationDataByTitle   = [];

        foreach ($stats['locationCounts'] as $code => $count) {
            $title = $shortCodeToTitle[$code] ?? $code;
            $locationCountsByTitle[$title] = $count;

            $lw = $stats['locationWeekdays'][$code] ?? [];
            $weekdayArr = [];
            foreach ($weekdayKeys as $wd) {
                $weekdayArr[$wd] = $lw[$wd] ?? 0;
            }

            $l7  = $stats['locationLast7'][$code]  ?? [];
            $l7Full = [];
            foreach (array_keys($stats['last7Days']) as $d7) {
                $l7Full[$d7] = $l7[$d7] ?? 0;
            }

            $l30 = $stats['locationLast30'][$code] ?? [];
            $l30Full = [];
            foreach (array_keys($stats['last30Days']) as $d30) {
                $l30Full[$d30] = $l30[$d30] ?? 0;
            }

            $locationDataByTitle[$title] = [
                'weekdays' => $weekdayArr,
                'last7'    => $l7Full,
                'last30'   => $l30Full,
            ];
        }

        $mostVisitedCode  = !empty($stats['locationCounts']) ? (string)array_key_first($stats['locationCounts']) : null;
        $mostVisitedTitle = $mostVisitedCode !== null ? ($shortCodeToTitle[$mostVisitedCode] ?? $mostVisitedCode) : null;
        $mostVisitedCount = $mostVisitedCode !== null ? $stats['locationCounts'][$mostVisitedCode] : 0;

        $numSitesWithVisits = count($stats['locationCounts']);
        $avgVisitsPerSite   = $numSitesWithVisits > 0 ? round($stats['totalScans'] / $numSitesWithVisits, 1) : 0;
        $avgScansPerVisitor = $stats['totalVisitors'] > 0 ? round($stats['totalScans'] / $stats['totalVisitors'], 1) : 0;

        return $this->renderTemplate('stamp-passport/stats/index', [
            'settings'            => $settings,
            'allSites'            => Craft::$app->getSites()->getAllSites(),
            'currentSite'         => $currentSite,
            'dateFrom'            => $dateFrom,
            'dateTo'              => $dateTo,
            'totalScans'          => $stats['totalScans'],
            'totalVisitors'       => $stats['totalVisitors'],
            'avgScansPerVisitor'  => $avgScansPerVisitor,
            'avgVisitsPerSite'    => $avgVisitsPerSite,
            'mostVisitedSite'     => $mostVisitedTitle,
            'mostVisitedCount'    => $mostVisitedCount,
            'qualifyDraw'         => $stats['qualifyDraw'],
            'qualifyStickers'     => $stats['qualifyStickers'],
            'totalItemsCount'     => $totalItemsCount,
            'locationCounts'      => $locationCountsByTitle,
            'weekdayCounts'       => $stats['weekdayCounts'],
            'visitsByDayLast7'    => $stats['last7Days'],
            'visitsByDayLast30'   => $stats['last30Days'],
            'locationDataByTitle' => $locationDataByTitle,
            // Legacy aliases kept for backward compatibility
            'totalIndividuals'    => $stats['totalVisitors'],
            'avgLocations'        => $avgScansPerVisitor,
        ]);
    }

    /**
     * Weighted prize-draw tool (read-only view + history).
     */
    public function actionDraw(): Response
    {
        $request  = Craft::$app->getRequest();
        $settings = Plugin::$plugin->getSettings();
        $draw     = Plugin::$plugin->draw;

        $formHandle    = (string)($settings->freeformDrawFormHandle ?? '');
        $dateFrom      = (string)($request->getQueryParam('dateFrom') ?? '');
        $dateTo        = (string)($request->getQueryParam('dateTo')   ?? '');
        $weightingMode = (string)($request->getQueryParam('weightingMode') ?? Draw::WEIGHTING_TOTAL);

        [$dateFrom, $dateTo] = $this->_validateDateRange($dateFrom, $dateTo);
        if (!$draw->isWeightingMode($weightingMode)) {
            $weightingMode = Draw::WEIGHTING_TOTAL;
        }

        $pool = $formHandle !== ''
            ? $draw->buildPool($formHandle, $settings->drawThreshold, $weightingMode, $dateFrom, $dateTo)
            : null;

        // Winners to reveal: a comma-separated `results` list from a multi-prize run,
        // falling back to a single legacy `result` id.
        $resultIds = [];
        $resultsParam = (string)($request->getQueryParam('results') ?? '');
        if ($resultsParam !== '') {
            foreach (explode(',', $resultsParam) as $rid) {
                $rid = (int)trim($rid);
                if ($rid > 0) {
                    $resultIds[] = $rid;
                }
            }
        } elseif ((int)($request->getQueryParam('result') ?? 0) > 0) {
            $resultIds[] = (int)$request->getQueryParam('result');
        }

        $latestResults = [];
        foreach (array_unique($resultIds) as $rid) {
            $record = $draw->getResultById($rid);
            if ($record) {
                $latestResults[] = [
                    'result' => $record,
                    'verify' => $draw->verify($record),
                ];
            }
        }

        $currentUser = Craft::$app->getUser()->getIdentity();
        $canDraw = $currentUser && ($currentUser->admin || $currentUser->can('stampPassport:manageSettings'));

        return $this->renderTemplate('stamp-passport/draw/index', [
            'settings'          => $settings,
            'allSites'          => Craft::$app->getSites()->getAllSites(),
            'formHandle'        => $formHandle,
            'pool'              => $pool,
            'weightingMode'     => $weightingMode,
            'dateFrom'          => $dateFrom,
            'dateTo'            => $dateTo,
            'prizeCount'        => (int)$settings->drawPrizeCount,
            'latestResults'     => $latestResults,
            'history'           => $draw->recentResults(20),
            'freeformInstalled' => Craft::$app->getPlugins()->getPlugin('freeform') !== null,
            'canDraw'           => $canDraw,
        ]);
    }

    /**
     * Run a weighted draw (POST). Requires the settings-management permission and an
     * explicit confirmation token so a stray POST cannot trigger a draw.
     */
    public function actionDrawRun(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('stampPassport:manageSettings');

        $request  = Craft::$app->getRequest();
        $settings = Plugin::$plugin->getSettings();
        $draw     = Plugin::$plugin->draw;

        $formHandle    = (string)($settings->freeformDrawFormHandle ?? '');
        $dateFrom      = (string)($request->getBodyParam('dateFrom') ?? '');
        $dateTo        = (string)($request->getBodyParam('dateTo')   ?? '');
        $weightingMode = (string)($request->getBodyParam('weightingMode') ?? Draw::WEIGHTING_TOTAL);

        [$dateFrom, $dateTo] = $this->_validateDateRange($dateFrom, $dateTo);
        if (!$draw->isWeightingMode($weightingMode)) {
            $weightingMode = Draw::WEIGHTING_TOTAL;
        }

        // Preserve the active filters across the redirect.
        $redirectParams = array_filter([
            'dateFrom'      => $dateFrom,
            'dateTo'        => $dateTo,
            'weightingMode' => $weightingMode,
        ], static fn($v) => $v !== '');

        if ($formHandle === '') {
            Craft::$app->getSession()->setError(Craft::t('stamp-passport', 'No draw form is configured in Settings.'));
            return $this->redirect(UrlHelper::cpUrl('stamp-passport/draw', $redirectParams));
        }

        if ((string)$request->getBodyParam('confirm', '') !== 'DRAW') {
            Craft::$app->getSession()->setError(Craft::t('stamp-passport', 'Confirmation token missing.'));
            return $this->redirect(UrlHelper::cpUrl('stamp-passport/draw', $redirectParams));
        }

        $result = $draw->drawWinners(
            $formHandle,
            $settings->drawThreshold,
            (int)$settings->drawPrizeCount,
            $weightingMode,
            $dateFrom,
            $dateTo,
            Craft::$app->getUser()->getId()
        );

        if (!$result['ok']) {
            $messages = [
                'freeform_unavailable' => Craft::t('stamp-passport', 'Could not read draw submissions. Is Freeform installed and the draw form configured?'),
                'no_eligible_entries'  => Craft::t('stamp-passport', 'No eligible entries to draw from. Ensure the “contestCid” hidden field is added to the draw form, and that not everyone has already won.'),
                'save_failed'          => Craft::t('stamp-passport', 'Could not save the draw result.'),
            ];
            Craft::$app->getSession()->setError($messages[$result['error']] ?? Craft::t('stamp-passport', 'Could not run the draw.'));
            return $this->redirect(UrlHelper::cpUrl('stamp-passport/draw', $redirectParams));
        }

        $drawnCount = (int)($result['drawnCount'] ?? 0);
        $requested  = (int)($result['requestedCount'] ?? $drawnCount);
        if ($drawnCount < $requested) {
            Craft::$app->getSession()->setNotice(Craft::t('stamp-passport', 'Drew {n} winner(s). Fewer than requested — the eligible pool ran out.', ['n' => $drawnCount]));
        } else {
            Craft::$app->getSession()->setNotice(Craft::t('stamp-passport', 'Drew {n} winner(s).', ['n' => $drawnCount]));
        }
        $redirectParams['results'] = implode(',', $result['resultIds'] ?? []);
        return $this->redirect(UrlHelper::cpUrl('stamp-passport/draw', $redirectParams));
    }

    /**
     * Validate an optional Y-m-d date range, blanking malformed values.
     *
     * @return array{0:string,1:string}
     */
    private function _validateDateRange(string $dateFrom, string $dateTo): array
    {
        $validateDate = static function (string $val): bool {
            $dt  = \DateTime::createFromFormat('Y-m-d', $val);
            $err = \DateTime::getLastErrors();
            return $dt !== false && (!$err || ($err['warning_count'] === 0 && $err['error_count'] === 0));
        };
        if ($dateFrom !== '' && !$validateDate($dateFrom)) {
            $dateFrom = '';
        }
        if ($dateTo !== '' && !$validateDate($dateTo)) {
            $dateTo = '';
        }
        return [$dateFrom, $dateTo];
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
        // Start from existing saved values so unposted sites are not clobbered
        $cleaned = is_array($settings->contestRules) ? $settings->contestRules : [];

        if (is_array($raw)) {
            foreach ($raw as $handle => $fields) {
                if (!is_array($fields)) {
                    continue;
                }
                $linkText      = trim((string)($fields['linkText'] ?? ''));
                $modalContent  = trim((string)($fields['modalContent'] ?? ''));
                $fullRulesText = trim((string)($fields['fullRulesText'] ?? ''));
                $fullRulesEntryId = (int)($fields['fullRulesEntryId'][0] ?? 0);

                if ($linkText !== '') {
                    $cleaned[$handle] = [
                        'linkText'      => $linkText,
                        'modalContent'  => $modalContent,
                        'fullRulesText' => $fullRulesText,
                        'fullRulesEntryId' => $fullRulesEntryId ?: null,
                    ];
                } else {
                    // Explicitly clear this site's rules when linkText is removed
                    unset($cleaned[$handle]);
                }
            }
        }

        $settings->contestRules = $cleaned;

        if (!$settings->validate()) {
            Craft::$app->getSession()->setError(Craft::t('stamp-passport', 'Could not save contest rules.'));
            return null;
        }

        Craft::$app->getPlugins()->savePluginSettings(Plugin::$plugin, $settings->toArray());
        Craft::$app->getSession()->setNotice(Craft::t('stamp-passport', 'Contest rules saved.'));

        return $this->redirectToPostedUrl();
    }

    /**
     * Plugin settings page.
     */
    public function actionSettings(): Response
    {
        $this->requirePermission('stampPassport:manageSettings');

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
        $this->requirePermission('stampPassport:manageSettings');

        $request = Craft::$app->getRequest();
        $settings = Plugin::$plugin->getSettings();

        $settings->pluginName = $request->getBodyParam('pluginName', $settings->pluginName);
        $settings->routePrefix = $request->getBodyParam('routePrefix', $settings->routePrefix);
        $settings->contestVersion = trim((string)$request->getBodyParam('contestVersion', $settings->contestVersion)) ?: $settings->contestVersion;

        $rawPrefixes = $request->getBodyParam('siteRoutePrefixes', []);
        if (is_array($rawPrefixes)) {
            $cleanedPrefixes = [];
            $knownHandles = array_map(
                static fn($s) => $s->handle,
                Craft::$app->getSites()->getAllSites()
            );
            foreach ($rawPrefixes as $handle => $prefix) {
                if (!is_string($handle) || !in_array($handle, $knownHandles, true)) {
                    continue;
                }
                $prefix = trim((string)$prefix, '/');
                if ($prefix !== '') {
                    $cleanedPrefixes[$handle] = $prefix;
                }
            }
            $settings->siteRoutePrefixes = $cleanedPrefixes;
        }
        $settings->enableGeofence = (bool)$request->getBodyParam('enableGeofence');
        $settings->geofenceRadius = max(1, (int)$request->getBodyParam('geofenceRadius', 550));
        $settings->ga4MeasurementId = $request->getBodyParam('ga4MeasurementId') ?: null;
        $settings->drawThreshold = max(1, (int)$request->getBodyParam('drawThreshold', 5));
        $settings->maxStickers = max(1, (int)$request->getBodyParam('maxStickers', 100));
        $settings->drawPrizeCount = max(1, (int)$request->getBodyParam('drawPrizeCount', 1));
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

        $footerBackgroundIds = $request->getBodyParam('footerBackgroundAssetId');
        $settings->footerBackgroundAssetId = is_array($footerBackgroundIds)
            ? ((int)($footerBackgroundIds[0] ?? 0) ?: null)
            : ((int)($footerBackgroundIds ?: 0) ?: null);

        $footerImageDisplay = (string)$request->getBodyParam('footerImageDisplay', $settings->footerImageDisplay);
        $settings->footerImageDisplay = in_array($footerImageDisplay, ['full', 'content', 'custom'], true) ? $footerImageDisplay : 'full';

        $bodyBackgroundMode = (string)$request->getBodyParam('bodyBackgroundMode', $settings->bodyBackgroundMode);
        $settings->bodyBackgroundMode = in_array($bodyBackgroundMode, ['cover', 'tiled', 'custom', 'repeat-y'], true) ? $bodyBackgroundMode : 'cover';

        $bodyBackgroundSize = trim((string)$request->getBodyParam('bodyBackgroundSize', $settings->bodyBackgroundSize));
        // Whitelist safe CSS size tokens to prevent property injection via the style block.
        if ($bodyBackgroundSize !== '' && !preg_match('/^\d+(\.\d+)?(px|%|vw|vh|vmin|vmax|em|rem)$/', $bodyBackgroundSize)
            && !in_array($bodyBackgroundSize, ['auto', 'cover', 'contain'], true)) {
            $bodyBackgroundSize = '';
        }
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
        $settings->customCssEnabled = (bool)$request->getBodyParam('customCssEnabled', false);
        $customCss = (string)$request->getBodyParam('customCss', '');
        // Neutralize </style> tag close — case-insensitive per HTML5 RAWTEXT rules.
        $customCss = preg_replace('/<\/style/i', '<\\/style', $customCss);
        $customCss = mb_substr($customCss, 0, 10000);
        $settings->customCss = $customCss !== '' ? $customCss : null;

        // ── UI Behavior Flags ─────────────────────────────────────────────────
        // Craft lightswitches POST '1' when on and '' when off.
        $settings->showLanguageSwitcher = (bool)$request->getBodyParam('showLanguageSwitcher', true);
        $settings->requireDisclaimerAck = (bool)$request->getBodyParam('requireDisclaimerAck', true);
        $settings->showOrgName = (bool)$request->getBodyParam('showOrgName', true);
        $settings->showChallengeName = (bool)$request->getBodyParam('showChallengeName', true);
        $settings->showChallengeTitle = (bool)$request->getBodyParam('showChallengeTitle', true);

        // ── QR Code Appearance ────────────────────────────────────────────────
        $qrSize = (int)$request->getBodyParam('qrSize', 450);
        $settings->qrSize = max(100, min(1000, $qrSize));

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
            $knownHandlesForUi = array_map(
                static fn($s) => $s->handle,
                Craft::$app->getSites()->getAllSites()
            );
            foreach ($uiText as $handle => $texts) {
                if (!is_string($handle) || !in_array($handle, $knownHandlesForUi, true)) {
                    continue;
                }
                if (!is_array($texts)) {
                    continue;
                }
                foreach ($texts as $key => $value) {
                    if (!in_array($key, Settings::TEXT_KEYS, true)) {
                        continue;
                    }
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

    /**
     * Reset collected stats (POST, AJAX).
     *
     * Permanently deletes contest-progress rows — the data that powers the Stats
     * dashboard. Item definitions and settings are left untouched. Optionally
     * scoped to a `dateUpdated` range (inclusive) via `dateFrom`/`dateTo`, which
     * lets pre-launch test data be wiped without touching later real activity;
     * with both blank, every row is removed. Guarded by a typed double-
     * confirmation in the CP UI; still requires the settings management
     * permission server-side.
     */
    public function actionResetStats(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $this->requirePermission('stampPassport:manageSettings');

        $request = Craft::$app->getRequest();

        // Require the client to echo an explicit confirmation token so a stray
        // POST cannot wipe data on its own.
        $confirm = (string)$request->getBodyParam('confirm', '');
        if ($confirm !== 'RESET') {
            return $this->asJson([
                'success' => false,
                'error'   => Craft::t('stamp-passport', 'Confirmation token missing.'),
            ]);
        }

        $dateFrom = trim((string)$request->getBodyParam('dateFrom', ''));
        $dateTo   = trim((string)$request->getBodyParam('dateTo', ''));

        // Reject malformed dates rather than silently dropping the bound, which
        // could widen the delete beyond what the operator confirmed.
        $validateDate = static function (string $val): bool {
            $dt  = \DateTime::createFromFormat('Y-m-d', $val);
            $err = \DateTime::getLastErrors();
            return $dt !== false && (!$err || ($err['warning_count'] === 0 && $err['error_count'] === 0));
        };
        if (($dateFrom !== '' && !$validateDate($dateFrom)) || ($dateTo !== '' && !$validateDate($dateTo))) {
            return $this->asJson([
                'success' => false,
                'error'   => Craft::t('stamp-passport', 'Invalid date range.'),
            ]);
        }

        $condition = ['and'];
        if ($dateFrom !== '') {
            $condition[] = ['>=', 'dateUpdated', $dateFrom . ' 00:00:00'];
        }
        if ($dateTo !== '') {
            $condition[] = ['<=', 'dateUpdated', $dateTo . ' 23:59:59'];
        }
        // An ['and'] with no extra clauses deletes everything.
        if (count($condition) === 1) {
            $condition = '';
        }

        $deleted = Craft::$app->getDb()->createCommand()
            ->delete('{{%stamppassport_contest_progress}}', $condition)
            ->execute();

        return $this->asJson([
            'success' => true,
            'deleted' => $deleted,
        ]);
    }
}
