<?php

namespace csabourin\cottagepassport;

use Craft;
use craft\base\Plugin as BasePlugin;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\web\UrlManager;
use craft\web\View;
use craft\web\twig\variables\CraftVariable;
use csabourin\cottagepassport\models\Settings;
use csabourin\cottagepassport\services\Items;
use csabourin\cottagepassport\variables\CottagePassportVariable;
use yii\base\Event;

/**
 * Cottage Passport plugin for Craft CMS 4
 *
 * @property Items $items
 * @property Settings $settings
 */
class Plugin extends BasePlugin
{
    public static Plugin $plugin;

    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;
    public bool $hasCpSection = true;

    public function init(): void
    {
        parent::init();
        self::$plugin = $this;

        // Register Yii alias so templates can reference @csabourin/cottagepassport/...
        Craft::setAlias('@csabourin/cottagepassport', __DIR__);

        $this->setComponents([
            'items' => Items::class,
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
            'items' => ['label' => Craft::t('cottage-passport', 'Items'), 'url' => 'cottage-passport/items'],
            'qr-generator' => ['label' => Craft::t('cottage-passport', 'QR Codes'), 'url' => 'cottage-passport/qr-generator'],
            'settings' => ['label' => Craft::t('cottage-passport', 'Settings'), 'url' => 'cottage-passport/settings'],
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
            'cottage-passport/settings',
            ['settings' => $this->getSettings()]
        );
    }

    private function _registerCpRoutes(): void
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['cottage-passport'] = 'cottage-passport/cp/index';
                $event->rules['cottage-passport/items'] = 'cottage-passport/cp/index';
                $event->rules['cottage-passport/items/new'] = 'cottage-passport/cp/edit';
                $event->rules['cottage-passport/items/<itemId:\d+>'] = 'cottage-passport/cp/edit';
                $event->rules['cottage-passport/qr-generator'] = 'cottage-passport/cp/qr-generator';
                $event->rules['cottage-passport/settings'] = 'cottage-passport/cp/settings';
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
                $event->rules[$routePrefix] = ['template' => '_cottage-passport/index'];
            }
        );
    }

    private function _registerSiteTemplateRoot(): void
    {
        Event::on(
            View::class,
            View::EVENT_REGISTER_SITE_TEMPLATE_ROOTS,
            function (RegisterTemplateRootsEvent $event) {
                $event->roots['_cottage-passport'] = __DIR__ . '/templates/_frontend';
            }
        );
    }

    private function _registerVariables(): void
    {
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function ($event) {
                $event->sender->set('cottagePassport', CottagePassportVariable::class);
            }
        );
    }
}
