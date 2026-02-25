<?php

namespace csabourin\stamppassport\models;

use craft\base\Model;

class Settings extends Model
{
    /** @var string Display name shown in the CP navigation */
    public string $pluginName = 'Stamp Passport';

    /** @var string URL prefix for the frontend route (e.g. "passport" → /en/passport) */
    public string $routePrefix = 'passport';

    /** @var bool Whether geofence validation is enabled for QR check-ins */
    public bool $enableGeofence = true;

    /** @var int Geofence radius in metres */
    public int $geofenceRadius = 550;

    /** @var string|null Google Analytics 4 measurement ID (e.g. G-XXXXXXXXXX) */
    public ?string $ga4MeasurementId = null;

    /** @var int Number of stamps required to unlock the draw form */
    public int $drawThreshold = 5;

    /** @var int Maximum number of sticker prizes available */
    public int $maxStickers = 100;

    /** @var string|null Freeform form handle for the end-of-season draw entry */
    public ?string $freeformDrawFormHandle = null;

    /** @var string|null Freeform form handle for the sticker / Memory Makers request */
    public ?string $freeformStickerFormHandle = null;

    /** @var int|null Asset ID for the circular logo displayed in the page header */
    public ?int $logoAssetId = null;

    /** @var int|null Asset ID for the wood panel header background image */
    public ?int $woodPanelAssetId = null;

    public function defineRules(): array
    {
        return [
            [['pluginName', 'routePrefix'], 'required'],
            [['pluginName', 'routePrefix'], 'string', 'max' => 100],
            [['geofenceRadius'], 'integer', 'min' => 50, 'max' => 10000],
            [['drawThreshold'], 'integer', 'min' => 1],
            [['maxStickers'], 'integer', 'min' => 0],
            [['ga4MeasurementId'], 'match', 'pattern' => '/^G-[A-Z0-9]+$/', 'skipOnEmpty' => true],
            [['freeformDrawFormHandle', 'freeformStickerFormHandle'], 'string', 'max' => 100],
            [['logoAssetId', 'woodPanelAssetId'], 'integer', 'min' => 1, 'skipOnEmpty' => true],
        ];
    }
}
