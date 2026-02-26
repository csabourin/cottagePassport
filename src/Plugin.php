<?php

namespace csabourin\stamppassport;

use Craft;
use craft\base\Plugin as BasePlugin;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\web\UrlManager;
use craft\web\View;
use craft\web\twig\variables\CraftVariable;
use csabourin\stamppassport\models\Settings;
use csabourin\stamppassport\services\ContestProgress;
use csabourin\stamppassport\services\Items;
use csabourin\stamppassport\variables\StampPassportVariable;
use yii\base\Event;

/**
 * Stamp Passport plugin for Craft CMS 4
 *
 * @property Items $items
 * @property ContestProgress $contestProgress
 * @property Settings $settings
 */
class Plugin extends BasePlugin
{
    public static Plugin $plugin;

    public string $schemaVersion = '1.1.0';
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
        ]);

        $this->_registerCpRoutes();
        $this->_registerSiteRoutes();
        $this->_registerSiteTemplateRoot();
        $this->_registerVariables();
    }

    public function getCpNavItem(): ?array
    {
        $item = parent::getCpNavItem();
        $item['label'] = $this->getSettings()->pluginName;
        $item['subnav'] = [
            'items' => ['label' => Craft::t('stamp-passport', 'Items'), 'url' => 'stamp-passport/items'],
            'qr-generator' => ['label' => Craft::t('stamp-passport', 'QR Codes'), 'url' => 'stamp-passport/qr-generator'],
            'settings' => ['label' => Craft::t('stamp-passport', 'Settings'), 'url' => 'stamp-passport/settings'],
        ];
        return $item;
    }

    protected function createSettingsModel(): Settings
    {
        return new Settings();
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate(
            'stamp-passport/settings',
            ['settings' => $this->getSettings()]
        );
    }

    private function _registerCpRoutes(): void
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['stamp-passport'] = 'stamp-passport/cp/index';
                $event->rules['stamp-passport/items'] = 'stamp-passport/cp/index';
                $event->rules['stamp-passport/items/new'] = 'stamp-passport/cp/edit';
                $event->rules['stamp-passport/items/<itemId:\d+>'] = 'stamp-passport/cp/edit';
                $event->rules['stamp-passport/qr-generator'] = 'stamp-passport/cp/qr-generator';
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
                $routePrefix = $this->getSettings()->routePrefix ?? 'passport';
                $routePrefix = trim($routePrefix, '/');
                $event->rules[$routePrefix] = ['template' => '_stamp-passport/index'];
                $event->rules[$routePrefix . '/'] = ['template' => '_stamp-passport/index'];
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
}
