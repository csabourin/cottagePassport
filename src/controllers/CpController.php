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
        return $this->renderTemplate('stamp-passport/text/index', [
            'settings'     => Plugin::$plugin->getSettings(),
            'allSites'     => Craft::$app->getSites()->getAllSites(),
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
        $rows = (new Query())
            ->from('{{%stamppassport_contest_progress}}')
            ->all();

        $users = [];
        $locationCounts = [];
        $weekdayCounts = [
            'Monday' => 0,
            'Tuesday' => 0,
            'Wednesday' => 0,
            'Thursday' => 0,
            'Friday' => 0,
            'Saturday' => 0,
            'Sunday' => 0,
        ];

        foreach ($rows as $row) {
            $payload = json_decode((string)$row['payload_json'], true);
            $steps = $payload['progress']['stepsCompleted'] ?? [];
            if (!is_array($steps)) {
                $steps = [];
            }

            $users[] = [
                'contestId' => (string)$row['contest_id'],
                'locationsScanned' => count($steps),
                'updatedAt' => (string)$row['updated_at'],
            ];

            foreach ($steps as $step) {
                $key = (string)$step;
                if ($key === '') {
                    continue;
                }
                $locationCounts[$key] = ($locationCounts[$key] ?? 0) + 1;
            }

            $weekday = date('l', strtotime((string)$row['updated_at']));
            if (isset($weekdayCounts[$weekday])) {
                $weekdayCounts[$weekday]++;
            }
        }

        arsort($locationCounts);

        return $this->renderTemplate('stamp-passport/stats/index', [
            'settings' => Plugin::$plugin->getSettings(),
            'users' => $users,
            'locationCounts' => $locationCounts,
            'weekdayCounts' => $weekdayCounts,
        ]);
    }

    /**
     * Contest rules page.
     */
    public function actionContestRules(): Response
    {
        return $this->renderTemplate('stamp-passport/contest-rules', [
            'settings'     => Plugin::$plugin->getSettings(),
            'contestRules' => Plugin::$plugin->getSettings()->contestRules,
            'allSites'     => Craft::$app->getSites()->getAllSites(),
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
