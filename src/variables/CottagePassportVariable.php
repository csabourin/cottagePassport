<?php

namespace csabourin\cottagepassport\variables;

use Craft;
use csabourin\cottagepassport\Plugin;
use csabourin\cottagepassport\records\ItemRecord;

/**
 * Twig variable accessible via {{ craft.cottagePassport }}.
 */
class CottagePassportVariable
{
    /**
     * Return all enabled items with content for the current (or given) site.
     *
     * Usage: {% set items = craft.cottagePassport.items %}
     */
    public function getItems(?int $siteId = null): array
    {
        return Plugin::$plugin->items->getEnabledItems($siteId);
    }

    /**
     * Get a single item by short code.
     *
     * Usage: {% set item = craft.cottagePassport.itemByCode('abc12345') %}
     */
    public function itemByCode(string $shortCode): ?ItemRecord
    {
        return Plugin::$plugin->items->getItemByShortCode($shortCode);
    }

    /**
     * Plugin settings for use in templates.
     *
     * Usage: {{ craft.cottagePassport.settings.pluginName }}
     */
    public function getSettings(): \csabourin\cottagepassport\models\Settings
    {
        return Plugin::$plugin->getSettings();
    }

    /**
     * Get the asset URL for an item's image.
     *
     * Usage: {{ craft.cottagePassport.imageUrl(item.imageId) }}
     */
    public function imageUrl(?int $assetId): ?string
    {
        if (!$assetId) {
            return null;
        }

        $asset = Craft::$app->getAssets()->getAssetById($assetId);
        return $asset?->getUrl();
    }

    /**
     * Return the action URL for the API collect endpoint.
     */
    public function collectActionUrl(): string
    {
        return \craft\helpers\UrlHelper::actionUrl('cottage-passport/api/collect');
    }

    /**
     * Return the action URL for the API locations endpoint.
     */
    public function locationsActionUrl(): string
    {
        return \craft\helpers\UrlHelper::actionUrl('cottage-passport/api/locations');
    }

    /**
     * Return the action URL for the API resolve endpoint.
     */
    public function resolveActionUrl(): string
    {
        return \craft\helpers\UrlHelper::actionUrl('cottage-passport/api/resolve');
    }
}
