<?php

namespace csabourin\stamppassport\variables;

use Craft;
use craft\helpers\HtmlPurifier;
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
     * Sanitize admin-authored HTML before it is rendered on the public frontend.
     */
    public function sanitizeHtml(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }

        return HtmlPurifier::process($html, [
            'HTML.Allowed' => implode(',', [
                'p', 'br', 'strong', 'em', 'b', 'i', 'u',
                'ul', 'ol', 'li',
                'a[href|target|rel]',
                'span', 'blockquote', 'hr',
                'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            ]),
            'Attr.AllowedFrameTargets' => ['_blank'],
            'HTML.Nofollow' => true,
            'HTML.TargetBlank' => true,
            'URI.AllowedSchemes' => [
                'http' => true,
                'https' => true,
                'mailto' => true,
                'tel' => true,
            ],
        ]);
    }

    /**
     * Return a map of item ID → effective linkEntryId for the current site.
     *
     * Queries all sites and prefers the current-site value, falling back to
     * any other site so integrators only need to set the linked entry once.
     *
     * @param int[] $itemIds
     * @return array<int,int>
     */
    public function getLinkEntryIdMap(array $itemIds): array
    {
        $siteId = Craft::$app->getSites()->getCurrentSite()->id;
        return Plugin::$plugin->items->getLinkEntryIdMap($itemIds, $siteId);
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
