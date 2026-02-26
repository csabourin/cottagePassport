<?php

namespace csabourin\stamppassport\variables;

use Craft;
use csabourin\stamppassport\Plugin;
use csabourin\stamppassport\records\ItemRecord;

/**
 * Twig variable accessible via {{ craft.stampPassport }}.
 */
class StampPassportVariable
{
    /**
     * Return all enabled items with content for the current (or given) site.
     *
     * Usage: {% set items = craft.stampPassport.items %}
     */
    public function getItems(?int $siteId = null): array
    {
        return Plugin::$plugin->items->getEnabledItems($siteId);
    }

    /**
     * Get a single item by short code.
     *
     * Usage: {% set item = craft.stampPassport.itemByCode('abc12345') %}
     */
    public function itemByCode(string $shortCode): ?ItemRecord
    {
        return Plugin::$plugin->items->getItemByShortCode($shortCode);
    }

    /**
     * Plugin settings for use in templates.
     *
     * Usage: {{ craft.stampPassport.settings.pluginName }}
     */
    public function getSettings(): \csabourin\stamppassport\models\Settings
    {
        return Plugin::$plugin->getSettings();
    }

    /**
     * Get a customizable display text value for the current site.
     *
     * Usage: {{ craft.stampPassport.text('challengeTitle') }}
     */
    public function text(string $key, ?string $siteHandle = null): string
    {
        return Plugin::$plugin->getSettings()->getText($key, $siteHandle);
    }

    /**
     * Get the asset URL for an item's image.
     *
     * Usage: {{ craft.stampPassport.imageUrl(item.imageId) }}
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
        return \craft\helpers\UrlHelper::actionUrl('stamp-passport/api/collect');
    }

    /**
     * Return the action URL for the API locations endpoint.
     */
    public function locationsActionUrl(): string
    {
        return \craft\helpers\UrlHelper::actionUrl('stamp-passport/api/locations');
    }

    /**
     * Return the action URL for the API resolve endpoint.
     */
    public function resolveActionUrl(): string
    {
        return \craft\helpers\UrlHelper::actionUrl('stamp-passport/api/resolve');
    }

    /**
     * Return the site URL for the contest progress sync endpoint.
     */
    public function contestProgressUrl(): string
    {
        return \craft\helpers\UrlHelper::siteUrl('api/contest-progress');
    }
}
