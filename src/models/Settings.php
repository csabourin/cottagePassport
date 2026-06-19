<?php

namespace csabourin\stamppassport\models;

use Craft;
use craft\base\Model;

class Settings extends Model
{
    /** @var string Display name shown in the CP navigation */
    public string $pluginName = 'Stamp Passport';

    /** @var string Default URL prefix for the frontend route (e.g. "passport" -> /passport) */
    public string $routePrefix = 'passport';

    /** @var array Per-site route prefixes, keyed by site handle. Falls back to routePrefix when not set. */
    public array $siteRoutePrefixes = [];

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

    /** @var int Number of prizes/winners to draw per run (each a distinct participant) */
    public int $drawPrizeCount = 1;

    /** @var string|null Date (Y-m-d) on/after which the draw may be run. Null/blank = no restriction */
    public ?string $drawDate = null;

    /** @var string|null Freeform form handle for the end-of-season draw entry */
    public ?string $freeformDrawFormHandle = null;

    /** @var string|null Freeform form handle for the sticker request */
    public ?string $freeformStickerFormHandle = null;

    /** @var int|null Asset ID for the primary logo displayed in the page header */
    public ?int $logoAssetId = null;

    /** @var int|null Asset ID for the alternate-language logo (e.g. French). Falls back to logoAssetId when not set */
    public ?int $logoAltAssetId = null;

    /** @var int|null Asset ID for the wood panel header background image */
    public ?int $woodPanelAssetId = null;

    /** @var int|null Asset ID for the checked-location badge image */
    public ?int $checkedMarkerAssetId = null;

    /** @var int|null Asset ID for the page body background image */
    public ?int $bodyBackgroundAssetId = null;

    /** @var int|null Asset ID for the decorative footer background image, anchored to the bottom of the page */
    public ?int $footerBackgroundAssetId = null;

    /** @var string Footer image width mode: "full" (viewport), "content" (560 px column), or "custom" (matches bodyBackgroundSize) */
    public string $footerImageDisplay = 'full';

    /** @var int|null Optional asset used as a center image in generated QR codes */
    public ?int $qrCenterImageAssetId = null;

    /** @var string Body background display mode when an image is set: "cover", "tiled", or "custom" */
    public string $bodyBackgroundMode = 'cover';

    /** @var string Custom background-size value used when bodyBackgroundMode is "custom" */
    public string $bodyBackgroundSize = '800px';

    /** @var string|null Optional background color shown behind the body background image (not used for tiled mode) */
    public ?string $bodyBackgroundColor = null;

    /** @var string Contest version identifier, changes when contest rules change */
    public string $contestVersion = '2026.02';

    /** @var array Per-site display text overrides, keyed by site handle */
    public array $uiText = [];

    /** @var array Per-site contest rules content, keyed by site handle */
    public array $contestRules = [];
    // Per-site keys: linkText, modalContent (HTML), fullRulesText, fullRulesEntryId

    // ── Brand Colors ──────────────────────────────────────────────────────────
    /** @var string|null Primary brand color (overrides --passport-teal CSS var). Full hex: #rrggbb */
    public ?string $primaryColor = null;

    /** @var string|null Dark variant of primary color (overrides --passport-teal-dark CSS var) */
    public ?string $primaryColorDark = null;

    /** @var string|null Accent color (overrides --passport-green CSS var) */
    public ?string $accentColor = null;

    // ── Social / OG Meta ─────────────────────────────────────────────────────
    /** @var int|null Asset ID for the Open Graph og:image (globally applied) */
    public ?int $ogImageAssetId = null;

    // ── Favicon ───────────────────────────────────────────────────────────────
    /** @var int|null Asset ID for the browser favicon shown on the frontend */
    public ?int $faviconAssetId = null;

    // ── Custom CSS ────────────────────────────────────────────────────────────
    /** @var bool Whether the custom CSS block is injected into the frontend <head> */
    public bool $customCssEnabled = false;

    /** @var string|null Raw CSS injected into a <style> block in the frontend <head>. Max 10 000 chars */
    public ?string $customCss = null;

    // ── UI Behavior ───────────────────────────────────────────────────────────
    /** @var bool Whether the language-switcher nav is rendered. Default true */
    public bool $showLanguageSwitcher = true;

    /** @var bool Whether the disclaimer modal is shown on first visit. Default true */
    public bool $requireDisclaimerAck = true;

    /** @var bool Whether the organisation name is displayed in the page header. Default true */
    public bool $showOrgName = true;

    /** @var bool Whether the challenge name (light heading) is displayed in the page header. Default true */
    public bool $showChallengeName = true;

    /** @var bool Whether the challenge title (bold heading) is displayed in the page header. Default true */
    public bool $showChallengeTitle = true;

    // ── QR Code Appearance ────────────────────────────────────────────────────
    /** @var string|null Foreground (dot) color for generated QR codes. Null → #000000 */
    public ?string $qrForegroundColor = null;

    /** @var string|null Background color for generated QR codes. Null → #ffffff */
    public ?string $qrBackgroundColor = null;

    /** @var int Pixel size of generated QR codes sent to QuickChart API. Default 450 */
    public int $qrSize = 450;

    public const TEXT_KEYS = [
        'orgName',
        'challengeName',
        'challengeTitle',
        'scanInstructions',
        'drawModalTitle',
        'drawModalBody',
        'stickerModalTitle',
        'stickerModalBody',
        'stickerSoldOutBody',
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
        'ogTitle',
        'ogDescription',
    ];

    public const TEXT_LABELS = [
        'orgName' => 'Organization Name',
        'challengeName' => 'Challenge Name',
        'challengeTitle' => 'Challenge Title',
        'scanInstructions' => 'Scan Instructions',
        'drawModalTitle' => 'Draw Modal Title',
        'drawModalBody' => 'Draw Modal Body',
        'stickerModalTitle' => 'Sticker Modal Title',
        'stickerModalBody' => 'Sticker Modal Body',
        'stickerSoldOutBody' => 'Sticker Sold-Out Message (HTML allowed)',
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
        'ogTitle' => 'OG / Social Share Title',
        'ogDescription' => 'OG / Social Share Description',
    ];

    public const TEXT_DEFAULTS = [
        'default' => [
            'orgName' => 'Your Organization',
            'challengeName' => 'Stamp Passport',
            'challengeTitle' => 'Challenge',
            'scanInstructions' => 'Scan all QR codes at participating locations to complete your passport.',
            'drawModalTitle' => 'Enter the Draw',
            'drawModalBody' => 'You unlocked draw entry. Complete the form below for a chance to win.',
            'stickerModalTitle' => 'Completed Passport',
            'stickerModalBody' => 'You completed every location. Claim your limited-edition sticker.',
            'stickerSoldOutBody' => '<p>All stickers have been claimed. Thank you for completing your passport!</p>',
            'disclaimerTitle' => 'Before You Begin',
            'disclaimerBody' => 'Your progress is saved locally and synced online.',
            'disclaimerButton' => "Got it, let's go!",
            'alreadyCheckedIn' => 'You already checked in here.',
            'checkingLocation' => "Checking your location",
            'locationError' => 'Could not determine your location. Please allow location access and try again.',
            'checkinFailed' => 'Check-in failed. Please confirm you are at the right location.',
            'checkedIn' => 'Checked in.',
            'qrNotRecognized' => 'This QR code is not recognized.',
            'loadError' => 'Could not load passport data. Please try again later.',
            'ogTitle' => '',
            'ogDescription' => '',
        ],
        'fr' => [
            'orgName' => 'Votre organisation',
            'challengeName' => 'Stamp Passport',
            'challengeTitle' => 'Défi',
            'scanInstructions' => 'Scannez tous les codes QR aux emplacements participants pour compléter votre passeport.',
            'drawModalTitle' => 'Participez au tirage',
            'drawModalBody' => 'Vous avez débloqué votre inscription au tirage. Remplissez le formulaire ci-dessous pour courir la chance de gagner.',
            'stickerModalTitle' => 'Passeport complété',
            'stickerModalBody' => 'Vous avez complété tous les emplacements. Réclamez votre autocollant en édition limitée.',
            'stickerSoldOutBody' => '<p>Tous les autocollants ont été réclamés. Merci d\'avoir complété votre passeport!</p>',
            'disclaimerTitle' => 'Avant de commencer',
            'disclaimerBody' => 'Votre progression est sauvegardée localement et synchronisée en ligne.',
            'disclaimerButton' => "Compris, c'est parti!",
            'alreadyCheckedIn' => 'Vous avez déjà validé cet emplacement.',
            'checkingLocation' => "Vérification de votre position",
            'locationError' => "Impossible de déterminer votre position. Veuillez autoriser l'accès à la localisation et réessayer.",
            'checkinFailed' => "Échec de la validation. Veuillez confirmer que vous êtes au bon emplacement.",
            'checkedIn' => 'Validation réussie.',
            'qrNotRecognized' => "Ce code QR n'est pas reconnu.",
            'loadError' => 'Impossible de charger les données du passeport. Veuillez réessayer plus tard.',
            'ogTitle' => '',
            'ogDescription' => '',
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
            [['drawPrizeCount'], 'integer', 'min' => 1],
            [['drawDate'], 'date', 'format' => 'php:Y-m-d', 'skipOnEmpty' => true],
            [['ga4MeasurementId'], 'match', 'pattern' => '/^G-[A-Z0-9]+$/', 'skipOnEmpty' => true],
            [['freeformDrawFormHandle', 'freeformStickerFormHandle'], 'string', 'max' => 100],
            [['qrCenterImageAssetId'], 'integer', 'min' => 1, 'skipOnEmpty' => true],
            [['qrSize'], 'integer', 'min' => 100, 'max' => 1000],
            [['logoAssetId', 'logoAltAssetId', 'woodPanelAssetId', 'checkedMarkerAssetId', 'bodyBackgroundAssetId', 'footerBackgroundAssetId'], 'integer', 'min' => 1, 'skipOnEmpty' => true],
            [['bodyBackgroundMode'], 'in', 'range' => ['cover', 'tiled', 'custom', 'repeat-y']],
            [['footerImageDisplay'], 'in', 'range' => ['full', 'content', 'custom']],
            [['bodyBackgroundSize'], 'string', 'max' => 50],
            [['bodyBackgroundColor'], 'string', 'max' => 50],
            [['contestVersion'], 'string', 'max' => 20],
            [['primaryColor', 'primaryColorDark', 'accentColor', 'qrForegroundColor', 'qrBackgroundColor'], 'string', 'max' => 50],
            [['primaryColor', 'primaryColorDark', 'accentColor', 'qrForegroundColor', 'qrBackgroundColor'], 'match', 'pattern' => '/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', 'skipOnEmpty' => true],
            [['ogImageAssetId', 'faviconAssetId'], 'integer', 'min' => 1, 'skipOnEmpty' => true],
            [['customCss'], 'string', 'max' => 10000],
            [['customCssEnabled'], 'boolean'],
        ];
    }
}
