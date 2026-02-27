<?php

namespace csabourin\stamppassport\models;

use Craft;
use craft\base\Model;

class Settings extends Model
{
    /** @var string Display name shown in the CP navigation */
    public string $pluginName = 'Stamp Passport';

    /** @var string URL prefix for the frontend route (e.g. "passport" -> /passport) */
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

    /** @var int|null Asset ID for the checked-location badge image */
    public ?int $checkedMarkerAssetId = null;

    /** @var int|null Asset ID for the page body background image */
    public ?int $bodyBackgroundAssetId = null;

    /** @var string Body background display mode when an image is set: "cover" or "tiled" */
    public string $bodyBackgroundMode = 'cover';

    /** @var string Contest version identifier, changes when contest rules change */
    public string $contestVersion = '2026.02';

    /** @var array Per-site display text overrides, keyed by site handle */
    public array $uiText = [];

    public const TEXT_KEYS = [
        'orgName',
        'challengeTitle',
        'scanInstructions',
        'drawModalTitle',
        'drawModalBody',
        'stickerModalTitle',
        'stickerModalBody',
        'disclaimerTitle',
        'disclaimerBody',
        'disclaimerButton',
        'alreadyCheckedIn',
        'checkingLocation',
        'locationError',
        'checkinFailed',
        'checkedIn',
        'qrNotRecognized',
        'loadError',
    ];

    public const TEXT_LABELS = [
        'orgName' => 'Organization Name',
        'challengeTitle' => 'Challenge Title',
        'scanInstructions' => 'Scan Instructions',
        'drawModalTitle' => 'Draw Modal Title',
        'drawModalBody' => 'Draw Modal Body',
        'stickerModalTitle' => 'Sticker Modal Title',
        'stickerModalBody' => 'Sticker Modal Body',
        'disclaimerTitle' => 'Disclaimer Title',
        'disclaimerBody' => 'Disclaimer Body',
        'disclaimerButton' => 'Disclaimer Button',
        'alreadyCheckedIn' => 'Already Checked In',
        'checkingLocation' => 'Checking Location',
        'locationError' => 'Location Error',
        'checkinFailed' => 'Check-in Failed',
        'checkedIn' => 'Checked In',
        'qrNotRecognized' => 'QR Not Recognized',
        'loadError' => 'Load Error',
    ];

    public const TEXT_DEFAULTS = [
        'default' => [
            'orgName' => 'Your Organization',
            'challengeTitle' => 'Challenge',
            'scanInstructions' => 'Scan all QR codes at participating locations to complete your passport.',
            'drawModalTitle' => 'Enter the Draw',
            'drawModalBody' => 'You unlocked draw entry. Complete the form below for a chance to win.',
            'stickerModalTitle' => 'Completed Passport',
            'stickerModalBody' => 'You completed every location. Claim your limited-edition sticker.',
            'disclaimerTitle' => 'Before You Begin',
            'disclaimerBody' => 'Your progress is saved locally and synced online.',
            'disclaimerButton' => "Got it, let's go!",
            'alreadyCheckedIn' => 'You already checked in here.',
            'checkingLocation' => "Checking your location\u2026",
            'locationError' => 'Could not determine your location. Please allow location access and try again.',
            'checkinFailed' => 'Check-in failed. Please confirm you are at the right location.',
            'checkedIn' => 'Checked in.',
            'qrNotRecognized' => 'This QR code is not recognized.',
            'loadError' => 'Could not load passport data. Please try again later.',
        ],
        'fr' => [
            'orgName' => 'Votre organisation',
            'challengeTitle' => 'Defi',
            'scanInstructions' => 'Scannez tous les codes QR aux emplacements participants pour completer votre passeport.',
            'drawModalTitle' => 'Participez au tirage',
            'drawModalBody' => 'Vous avez debloque votre inscription au tirage. Remplissez le formulaire ci-dessous pour courir la chance de gagner.',
            'stickerModalTitle' => 'Passeport complete',
            'stickerModalBody' => 'Vous avez complete tous les emplacements. Reclamez votre autocollant en edition limitee.',
            'disclaimerTitle' => 'Avant de commencer',
            'disclaimerBody' => 'Votre progression est sauvegardee localement et synchronisee en ligne.',
            'disclaimerButton' => "Compris, c'est parti!",
            'alreadyCheckedIn' => 'Vous avez deja valide cet emplacement.',
            'checkingLocation' => "Verification de votre position\u2026",
            'locationError' => "Impossible de determiner votre position. Veuillez autoriser l'acces a la localisation et reessayer.",
            'checkinFailed' => "Echec de la validation. Veuillez confirmer que vous etes au bon emplacement.",
            'checkedIn' => 'Validation reussie.',
            'qrNotRecognized' => "Ce code QR n'est pas reconnu.",
            'loadError' => 'Impossible de charger les donnees du passeport. Veuillez reessayer plus tard.',
        ],
    ];

    /**
     * Get a display text value for the current (or specified) site.
     * Falls back to built-in defaults (localized by site language when available).
     */
    public function getText(string $key, ?string $siteHandle = null): string
    {
        if ($siteHandle === null) {
            $siteHandle = Craft::$app->getSites()->getCurrentSite()->handle;
        }

        // Check for admin override first
        $override = $this->uiText[$siteHandle][$key] ?? '';
        if ($override !== '') {
            return $override;
        }

        $site = Craft::$app->getSites()->getSiteByHandle($siteHandle);
        $lang = $site ? strtolower(substr($site->language, 0, 2)) : null;

        if ($lang !== null) {
            $localized = self::TEXT_DEFAULTS[$lang][$key] ?? null;
            if ($localized !== null && $localized !== '') {
                return $localized;
            }
        }

        return self::TEXT_DEFAULTS['default'][$key] ?? '';
    }

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
            [['logoAssetId', 'woodPanelAssetId', 'checkedMarkerAssetId', 'bodyBackgroundAssetId'], 'integer', 'min' => 1, 'skipOnEmpty' => true],
            [['bodyBackgroundMode'], 'in', 'range' => ['cover', 'tiled']],
            [['contestVersion'], 'string', 'max' => 20],
        ];
    }
}
