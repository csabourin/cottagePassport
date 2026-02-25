<?php

namespace csabourin\stamppassport\controllers;

use Craft;
use craft\web\Controller;
use csabourin\stamppassport\Plugin;
use yii\web\Response;

/**
 * Public JSON API consumed by the frontend JavaScript.
 * All actions allow anonymous access.
 */
class ApiController extends Controller
{
    protected array|bool|int $allowAnonymous = true;

    public function beforeAction($action): bool
    {
        // Disable CSRF for API endpoints
        $this->enableCsrfValidation = false;
        return parent::beforeAction($action);
    }

    /**
     * GET actions/stamp-passport/api/locations
     *
     * Returns all enabled items with content for the current site.
     */
    public function actionLocations(): Response
    {
        $settings = Plugin::$plugin->getSettings();
        $siteId = Craft::$app->getSites()->getCurrentSite()->id;
        $items = Plugin::$plugin->items->getEnabledItems($siteId);

        $data = [];
        foreach ($items as $item) {
            $content = $item->contents[0] ?? null;
            $image = null;
            if ($item->imageId) {
                $asset = Craft::$app->getAssets()->getAssetById($item->imageId);
                if ($asset) {
                    $image = $asset->getUrl();
                }
            }

            $data[] = [
                'id' => $item->id,
                'shortCode' => $item->shortCode,
                'title' => $content->title ?? '',
                'description' => $content->description ?? '',
                'linkUrl' => $content->linkUrl ?? null,
                'linkText' => $content->linkText ?? null,
                'image' => $image,
                'latitude' => $item->latitude ? (float)$item->latitude : null,
                'longitude' => $item->longitude ? (float)$item->longitude : null,
            ];
        }

        return $this->asJson([
            'success' => true,
            'pluginName' => $settings->pluginName,
            'drawThreshold' => $settings->drawThreshold,
            'maxStickers' => $settings->maxStickers,
            'enableGeofence' => $settings->enableGeofence,
            'geofenceRadius' => $settings->geofenceRadius,
            'items' => $data,
        ]);
    }

    /**
     * POST actions/stamp-passport/api/collect
     *
     * Validate a QR check-in (geofence if enabled).
     * Body: { shortCode, latitude, longitude }
     */
    public function actionCollect(): Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $shortCode = $request->getRequiredBodyParam('shortCode');
        $lat = (float)$request->getRequiredBodyParam('latitude');
        $lng = (float)$request->getRequiredBodyParam('longitude');

        $result = Plugin::$plugin->items->validateGeofence($shortCode, $lat, $lng);

        if (!$result['item']) {
            $this->response->setStatusCode(404);
            return $this->asJson([
                'success' => false,
                'error' => Craft::t('stamp-passport', 'Unknown item.'),
            ]);
        }

        if (!$result['ok']) {
            $this->response->setStatusCode(403);
            return $this->asJson([
                'success' => false,
                'error' => Craft::t('stamp-passport', 'Outside allowed radius.'),
                'distance' => $result['distance'],
                'allowedRadius' => $result['allowedRadius'],
            ]);
        }

        $item = $result['item'];
        $siteId = Craft::$app->getSites()->getCurrentSite()->id;
        $content = null;
        foreach ($item->contents as $c) {
            if ($c->siteId === $siteId) {
                $content = $c;
                break;
            }
        }

        return $this->asJson([
            'success' => true,
            'distance' => $result['distance'],
            'allowedRadius' => $result['allowedRadius'],
            'item' => [
                'id' => $item->id,
                'shortCode' => $item->shortCode,
                'title' => $content->title ?? '',
                'description' => $content->description ?? '',
            ],
        ]);
    }

    /**
     * GET actions/stamp-passport/api/resolve?q=<shortCode>
     *
     * Resolve a short code to an item (used by frontend when loading from QR URL).
     */
    public function actionResolve(): Response
    {
        $shortCode = Craft::$app->getRequest()->getRequiredQueryParam('q');
        $item = Plugin::$plugin->items->getItemByShortCode($shortCode);

        if (!$item || !$item->enabled) {
            $this->response->setStatusCode(404);
            return $this->asJson([
                'success' => false,
                'error' => Craft::t('stamp-passport', 'Invalid QR code.'),
            ]);
        }

        $siteId = Craft::$app->getSites()->getCurrentSite()->id;
        $content = null;
        foreach ($item->contents as $c) {
            if ($c->siteId === $siteId) {
                $content = $c;
                break;
            }
        }

        return $this->asJson([
            'success' => true,
            'item' => [
                'id' => $item->id,
                'shortCode' => $item->shortCode,
                'title' => $content->title ?? '',
                'description' => $content->description ?? '',
            ],
        ]);
    }
}
