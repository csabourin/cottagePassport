<?php

namespace csabourin\stamppassport\models;

use Craft;
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
        'en' => [
            'orgName' => "National Capital Commission\nCommission de la capitale nationale",
            'challengeTitle' => 'Challenge',
            'scanInstructions' => 'Scan all QR codes at official locations to complete your summer bucket list.',
            'drawModalTitle' => 'Enter the Draw!',
            'drawModalBody' => "You've unlocked the draw entry! Fill in the form below for a chance to win.",
            'stickerModalTitle' => 'Memory Makers!',
            'stickerModalBody' => "Congratulations! You've completed the full bucket list. Claim your limited-edition sticker.",
            'disclaimerTitle' => 'Before You Begin',
            'disclaimerBody' => 'Your progress is saved locally and synced online. Use the language toggle to switch between English and French without losing your stamps.',
            'disclaimerButton' => "Got it, let's go!",
            'alreadyCheckedIn' => 'You already checked in here!',
            'checkingLocation' => "Checking your location\u2026",
            'locationError' => 'Could not determine your location. Please allow location access and try again.',
            'checkinFailed' => 'Check-in failed. Are you at the right location?',
            'checkedIn' => 'Checked in!',
            'qrNotRecognized' => 'This QR code is not recognized.',
            'loadError' => 'Could not load passport data. Please try again later.',
        ],
        'fr' => [
            'orgName' => "Commission de la capitale nationale\nNational Capital Commission",
            'challengeTitle' => "Défi",
            'scanInstructions' => "Scannez tous les codes QR aux emplacements officiels pour compléter votre passeport estival.",
            'drawModalTitle' => 'Participez au tirage!',
            'drawModalBody' => "Vous avez débloqué la participation au tirage! Remplissez le formulaire ci-dessous pour courir la chance de gagner.",
            'stickerModalTitle' => "Créateurs de souvenirs!",
            'stickerModalBody' => "Félicitations! Vous avez complété le passeport au complet. Réclamez votre autocollant en édition limitée.",
            'disclaimerTitle' => 'Avant de commencer',
            'disclaimerBody' => "Votre progrès est sauvegardé localement et synchronisé en ligne. Utilisez le sélecteur de langue pour passer du français à l'anglais sans perdre vos étampes.",
            'disclaimerButton' => "Compris, c'est parti!",
            'alreadyCheckedIn' => "Vous avez déjà visité cet endroit!",
            'checkingLocation' => "Vérification de votre position\u2026",
            'locationError' => "Impossible de déterminer votre position. Veuillez autoriser l'accès à la localisation et réessayer.",
            'checkinFailed' => "Échec de la validation. \u00cates-vous au bon endroit?",
            'checkedIn' => "Visite confirmée!",
            'qrNotRecognized' => "Ce code QR n'est pas reconnu.",
            'loadError' => "Impossible de charger les données du passeport. Veuillez réessayer plus tard.",
        ],
    ];

    /**
     * Get a display text value for the current (or specified) site.
     * Falls back to built-in language defaults if no admin override is set.
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

        // Fall back to built-in defaults by language
        $site = Craft::$app->getSites()->getSiteByHandle($siteHandle);
        $lang = $site ? substr($site->language, 0, 2) : 'en';

        return self::TEXT_DEFAULTS[$lang][$key] ?? self::TEXT_DEFAULTS['en'][$key] ?? '';
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
            [['logoAssetId', 'woodPanelAssetId'], 'integer', 'min' => 1, 'skipOnEmpty' => true],
            [['contestVersion'], 'string', 'max' => 20],
        ];
    }
}
