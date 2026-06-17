<?php

namespace csabourin\stamppassport;

use Craft;
use craft\base\Plugin as BasePlugin;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\services\UserPermissions;
use craft\web\UrlManager;
use craft\web\View;
use craft\web\twig\variables\CraftVariable;
use csabourin\stamppassport\models\Settings;
use csabourin\stamppassport\services\ContestProgress;
use csabourin\stamppassport\services\Draw;
use csabourin\stamppassport\services\Items;
use csabourin\stamppassport\variables\StampPassportVariable;
use Solspace\Freeform\Freeform as FreeformPlugin;
use yii\base\Event;

/**
 * Stamp Passport plugin for Craft CMS 4/5
 *
 * @property Items $items
 * @property ContestProgress $contestProgress
 * @property Draw $draw
 * @property Settings $settings
 */
class Plugin extends BasePlugin
{
    public static Plugin $plugin;

    public string $schemaVersion = '1.2.0';
    public bool $hasCpSettings = true;
    public bool $hasCpSection = true;

    public function init(): void
    {
        parent::init();
        self::$plugin = $this;

        // Register Yii alias so templates can reference @csabourin/stamppassport/...
        Craft::setAlias('@csabourin/stamppassport', __DIR__);

        $this->setComponents([
            'items' => Items::class,
            'contestProgress' => ContestProgress::class,
            'draw' => Draw::class,
        ]);

        $this->_registerCpRoutes();
        $this->_registerSiteRoutes();
        $this->_registerSiteTemplateRoot();
        $this->_registerVariables();
        $this->_registerPermissions();
    }

    public function getCpNavItem(): ?array
    {
        $item = parent::getCpNavItem();
        $item['label'] = $this->getSettings()->pluginName;
        $item['iconMask'] = __DIR__ . '/icon.svg';
        $item['subnav'] = [
            'stats' => ['label' => Craft::t('stamp-passport', 'Dashboard'), 'url' => 'stamp-passport/stats'],
            'draw' => ['label' => Craft::t('stamp-passport', 'Draw'), 'url' => 'stamp-passport/draw'],
            'items' => ['label' => Craft::t('stamp-passport', 'Items'), 'url' => 'stamp-passport/items'],
            'qr-generator' => ['label' => Craft::t('stamp-passport', 'QR Codes'), 'url' => 'stamp-passport/qr-generator'],
            'display-text' => ['label' => Craft::t('stamp-passport', 'Display Text'), 'url' => 'stamp-passport/display-text'],
            'contest-rules' => ['label' => Craft::t('stamp-passport', 'Contest Rules'), 'url' => 'stamp-passport/contest-rules'],
        ];

        $currentUser = Craft::$app->getUser()->getIdentity();
        if ($currentUser && ($currentUser->admin || $currentUser->can('stampPassport:manageSettings'))) {
            $item['subnav']['settings'] = ['label' => Craft::t('stamp-passport', 'Settings'), 'url' => 'stamp-passport/settings'];
        }

        return $item;
    }

    protected function createSettingsModel(): Settings
    {
        return new Settings();
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate(
            'stamp-passport/_settings-fields',
            [
                'settings' => $this->getSettings(),
                'freeformForms' => $this->getFreeformFormOptions(),
            ]
        );
    }

    /**
     * Returns Freeform form options for CP select fields.
     * Returns null when Freeform is not installed (template falls back to a text input).
     * Queries the database directly to avoid Freeform API version differences.
     */
    public function getFreeformFormOptions(): ?array
    {
        // Return null (not an empty array) when Freeform is absent so the
        // template shows a plain text field instead of an empty dropdown.
        $freeformInstalled = Craft::$app->getPlugins()->getPlugin('freeform') !== null
            || class_exists(FreeformPlugin::class);

        if (!$freeformInstalled) {
            return null;
        }

        try {
            $rows = Craft::$app->getDb()
                ->createCommand('SELECT [[name]], [[handle]] FROM {{%freeform_forms}} ORDER BY [[name]]')
                ->queryAll();

            return array_map(
                static fn(array $row) => ['label' => $row['name'], 'value' => $row['handle']],
                $rows
            );
        } catch (\Throwable $e) {
            Craft::warning('Unable to fetch Freeform forms: ' . $e->getMessage(), __METHOD__);
            return null;
        }
    }

    private function _registerCpRoutes(): void
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['stamp-passport'] = 'stamp-passport/cp/stats';
                $event->rules['stamp-passport/items'] = 'stamp-passport/cp/index';
                $event->rules['stamp-passport/items/new'] = 'stamp-passport/cp/edit';
                $event->rules['stamp-passport/items/<itemId:\d+>'] = 'stamp-passport/cp/edit';
                $event->rules['stamp-passport/qr-generator'] = 'stamp-passport/cp/qr-generator';
                $event->rules['stamp-passport/display-text'] = 'stamp-passport/cp/display-text';
                $event->rules['stamp-passport/contest-rules'] = 'stamp-passport/cp/contest-rules';
                $event->rules['stamp-passport/stats'] = 'stamp-passport/cp/stats';
                $event->rules['stamp-passport/draw'] = 'stamp-passport/cp/draw';
                $event->rules['stamp-passport/settings'] = 'stamp-passport/cp/settings';
            }
        );
    }

    private function _registerSiteRoutes(): void
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $settings = $this->getSettings();
                $defaultPrefix = trim($settings->routePrefix ?? 'passport', '/');
                $registered = [];

                // Register per-site prefixes
                foreach ($settings->siteRoutePrefixes as $prefix) {
                    $prefix = trim((string)$prefix, '/');
                    if ($prefix === '' || in_array($prefix, $registered, true)) {
                        continue;
                    }
                    $registered[] = $prefix;
                    $event->rules[$prefix] = ['template' => '_stamp-passport/index'];
                    $event->rules[$prefix . '/'] = ['template' => '_stamp-passport/index'];
                }

                // Register the default prefix if not already registered
                if ($defaultPrefix !== '' && !in_array($defaultPrefix, $registered, true)) {
                    $event->rules[$defaultPrefix] = ['template' => '_stamp-passport/index'];
                    $event->rules[$defaultPrefix . '/'] = ['template' => '_stamp-passport/index'];
                }

                $event->rules['api/contest-progress'] = 'stamp-passport/contest-progress/index';
            }
        );
    }

    private function _registerSiteTemplateRoot(): void
    {
        Event::on(
            View::class,
            View::EVENT_REGISTER_SITE_TEMPLATE_ROOTS,
            function (RegisterTemplateRootsEvent $event) {
                $event->roots['_stamp-passport'] = __DIR__ . '/templates/_frontend';
            }
        );
    }

    private function _registerVariables(): void
    {
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function ($event) {
                $event->sender->set('stampPassport', StampPassportVariable::class);
            }
        );
    }

    private function _registerPermissions(): void
    {
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function (RegisterUserPermissionsEvent $event) {
                $event->permissions[] = [
                    'heading' => Craft::t('stamp-passport', 'Stamp Passport'),
                    'permissions' => [
                        'stampPassport:manage' => [
                            'label' => Craft::t('stamp-passport', 'Access Stamp Passport'),
                            'nested' => [
                                'stampPassport:manageSettings' => [
                                    'label' => Craft::t('stamp-passport', 'Manage Settings'),
                                ],
                            ],
                        ],
                    ],
                ];
            }
        );
    }
}
