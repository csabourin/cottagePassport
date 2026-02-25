<?php

namespace csabourin\stamppassport\controllers;

use Craft;
use craft\helpers\UrlHelper;
use craft\web\Controller;
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
            'items' => $items,
            'settings' => Plugin::$plugin->getSettings(),
        ]);
    }

    /**
     * Item edit / new page.
     */
    public function actionEdit(?int $itemId = null): Response
    {
        $sites = Craft::$app->getSites()->getAllSites();

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
                'linkText' => '',
            ];
        }

        if ($item) {
            foreach ($item->contents as $c) {
                $content[$c->siteId] = [
                    'title' => $c->title ?? '',
                    'description' => $c->description ?? '',
                    'linkUrl' => $c->linkUrl ?? '',
                    'linkText' => $c->linkText ?? '',
                ];
            }
        }

        return $this->renderTemplate('stamp-passport/items/_edit', [
            'item' => $item,
            'content' => $content,
            'sites' => $sites,
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

        $attributes = [
            'latitude' => $request->getBodyParam('latitude') ?: null,
            'longitude' => $request->getBodyParam('longitude') ?: null,
            'imageId' => $request->getBodyParam('imageId')[0] ?? null,
            'enabled' => (bool)$request->getBodyParam('enabled', true),
            'sortOrder' => (int)$request->getBodyParam('sortOrder', 0),
        ];

        $content = $request->getBodyParam('content', []);

        $record = Plugin::$plugin->items->saveItem($attributes, $content, $id);

        if (!$record) {
            Craft::$app->getSession()->setError(Craft::t('stamp-passport', 'Could not save item.'));
            return null;
        }

        Craft::$app->getSession()->setNotice(Craft::t('stamp-passport', 'Item saved.'));
        return $this->redirectToPostedUrl(['itemId' => $record->id]);
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
        $this->requireAcceptsJson();

        $ids = Craft::$app->getRequest()->getRequiredBodyParam('ids');
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
     * Plugin settings page.
     */
    public function actionSettings(): Response
    {
        return $this->renderTemplate('stamp-passport/settings', [
            'settings' => Plugin::$plugin->getSettings(),
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

        if (!$settings->validate()) {
            Craft::$app->getSession()->setError(Craft::t('stamp-passport', 'Settings not saved.'));
            return null;
        }

        Craft::$app->getPlugins()->savePluginSettings(Plugin::$plugin, $settings->toArray());
        Craft::$app->getSession()->setNotice(Craft::t('stamp-passport', 'Settings saved.'));

        return $this->redirectToPostedUrl();
    }
}
