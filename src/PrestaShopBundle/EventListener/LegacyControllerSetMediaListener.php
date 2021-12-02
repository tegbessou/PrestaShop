<?php

namespace PrestaShopBundle\EventListener;

use Configuration;
use Context;
use Employee;
use Language;
use Link;
use Media;
use PrestaShop\PrestaShop\Core\Hook\HookDispatcher;
use PrestaShop\PrestaShop\Core\Localization\Locale\Repository;
use PrestaShopBundle\Controller\Admin\LegacyAdminController;
use PrestaShopBundle\Controller\Admin\LegacyAdminControllerInterface;
use PrestaShopBundle\Controller\Admin\MultistoreController;
use PrestaShopBundle\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Tab;
use Tools;
use Validate;

class LegacyControllerSetMediaListener
{
    /**
     * @var HookDispatcher
     */
    private $hookDispatcher;

    /**
     * @var MultistoreController
     */
    private $multistore;

    /**
     * @var LegacyAdminController
     */
    private $controller;

    public function __construct(HookDispatcher $hookDispatcher, MultistoreController $multistore)
    {
        $this->hookDispatcher = $hookDispatcher;
        $this->multistore = $multistore;
    }

    public function onKernelController(ControllerEvent $event): void
    {
        if (!$this->supports($event)) {
            return;
        }

        $controller = $event->getController()[0];



        if (method_exists($controller, 'setMedia')) {
            $controller->setMedia();
        }
    }

    private function setMedia(bool $isNewTheme = false)
    {
        if ($isNewTheme) {
            $this->controller->addCSS(__PS_BASE_URI__ . $this->admin_webpath . '/themes/new-theme/public/theme.css', 'all', 1);
            $this->addJS(__PS_BASE_URI__ . $this->admin_webpath . '/themes/new-theme/public/main.bundle.js');

            // the multistore dropdown should be called only once, and only if multistore is used
            //Todo inject this service
            if ($this->container->get('prestashop.adapter.multistore_feature')->isUsed()) {
                $this->addJs(__PS_BASE_URI__ . $this->admin_webpath . '/themes/new-theme/public/multistore_dropdown.bundle.js');
            }
            $this->addJqueryPlugin(['chosen', 'fancybox']);
        } else {
            //Bootstrap
            $this->addCSS(__PS_BASE_URI__ . $this->admin_webpath . '/themes/' . $this->bo_theme . '/css/' . $this->bo_css, 'all', 0);
            $this->addCSS(__PS_BASE_URI__ . $this->admin_webpath . '/themes/' . $this->bo_theme . '/css/vendor/titatoggle-min.css', 'all', 0);
            $this->addCSS(__PS_BASE_URI__ . $this->admin_webpath . '/themes/' . $this->bo_theme . '/public/theme.css', 'all', 0);

            // add Jquery 3 and its migration script
            $this->addJs(_PS_JS_DIR_ . 'jquery/jquery-3.5.1.min.js');
            $this->addJs(_PS_JS_DIR_ . 'jquery/bo-migrate-mute.min.js');
            $this->addJs(_PS_JS_DIR_ . 'jquery/jquery-migrate-3.1.0.min.js');
            // implement $.browser object and live method, that has been removed since jquery 1.9
            $this->addJs(_PS_JS_DIR_ . 'jquery/jquery.browser-0.1.0.min.js');
            $this->addJs(_PS_JS_DIR_ . 'jquery/jquery.live-polyfill-1.1.2.min.js');

            $this->addJqueryPlugin(['scrollTo', 'alerts', 'chosen', 'autosize', 'fancybox']);
            $this->addJqueryPlugin('growl', null, false);
            $this->addJqueryUI(['ui.slider', 'ui.datepicker']);

            $this->addJS(__PS_BASE_URI__ . $this->admin_webpath . '/themes/' . $this->bo_theme . '/js/vendor/bootstrap.min.js');
            $this->addJS(__PS_BASE_URI__ . $this->admin_webpath . '/themes/' . $this->bo_theme . '/js/vendor/modernizr.min.js');
            $this->addJS(__PS_BASE_URI__ . $this->admin_webpath . '/themes/' . $this->bo_theme . '/js/modernizr-loads.js');
            $this->addJS(__PS_BASE_URI__ . $this->admin_webpath . '/themes/' . $this->bo_theme . '/js/vendor/moment-with-langs.min.js');
            $this->addJS(__PS_BASE_URI__ . $this->admin_webpath . '/themes/' . $this->bo_theme . '/public/bundle.js');

            $this->addJS(_PS_JS_DIR_ . 'jquery/plugins/timepicker/jquery-ui-timepicker-addon.js');

            if (!$this->lite_display) {
                $this->addJS(__PS_BASE_URI__ . $this->admin_webpath . '/themes/' . $this->bo_theme . '/js/help.js');
            }

            if (!Tools::getValue('submitFormAjax')) {
                $this->addJS(_PS_JS_DIR_ . 'admin/notifications.js');
            }

            // Specific Admin Theme
            $this->addCSS(__PS_BASE_URI__ . $this->admin_webpath . '/themes/' . $this->bo_theme . '/css/overrides.css', 'all', PHP_INT_MAX);
        }

        $this->addJS([
            _PS_JS_DIR_ . 'admin.js?v=' . _PS_VERSION_, // TODO: SEE IF REMOVABLE
            __PS_BASE_URI__ . $this->admin_webpath . '/themes/new-theme/public/cldr.bundle.js',
            _PS_JS_DIR_ . 'tools.js?v=' . _PS_VERSION_,
            __PS_BASE_URI__ . $this->admin_webpath . '/public/bundle.js',
        ]);

        Media::addJsDef([
            'changeFormLanguageUrl' => $this->context->link->getAdminLink(
                'AdminEmployees',
                true,
                [],
                ['action' => 'formLanguage']
            ),
        ]);
        Media::addJsDef(['host_mode' => (defined('_PS_HOST_MODE_') && _PS_HOST_MODE_)]);
        Media::addJsDef(['baseDir' => __PS_BASE_URI__]);
        Media::addJsDef(['baseAdminDir' => __PS_BASE_URI__ . basename(_PS_ADMIN_DIR_) . '/']);
        Media::addJsDef(['currency' => [
            'iso_code' => Context::getContext()->currency->iso_code,
            'sign' => Context::getContext()->currency->sign,
            'name' => Context::getContext()->currency->name,
            'format' => Context::getContext()->currency->format,
        ]]);
        Media::addJsDef(
            [
                'currency_specifications' => $this->preparePriceSpecifications($this->context),
                'number_specifications' => $this->prepareNumberSpecifications($this->context),
            ]
        );

        Media::addJsDef([
            'prestashop' => [
                'debug' => _PS_MODE_DEV_,
            ],
        ]);

        // Execute Hook AdminController SetMedia
        Hook::exec('actionAdminControllerSetMedia');
    }

    private function supports(ControllerEvent $event): bool
    {
        if (!is_array($event->getController())) {
            return false;
        }

        return $event->getController()[0] instanceof LegacyAdminControllerInterface;
    }
}
