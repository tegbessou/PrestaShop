<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

namespace PrestaShopBundle\Controller\Admin;

use Country;
use Configuration;
use Context;
use Currency;
use Language;
use Media;
use ObjectModel;
use PrestaShopBundle\Security\Annotation\AdminSecurity;
use Profile;
use Psr\Container\ContainerInterface;
use Shop;
use Symfony\Component\HttpFoundation\Response;
use Validate;
use Tab;
use Tools;

abstract class LegacyAdminController extends FrameworkBundleAdminController implements LegacyAdminControllerInterface
{
    public const NEED_DISPLAY_ACTION = [
        'edit',
        'list',
        'add',
        'view',
        'details',
        'options'
    ];

    public $_conf;
    public $_error;
    protected $_defaultOrderBy;
    protected $_languages;

    /** @var array */
    public $warnings = [];

    /** @var array */
    public $informations = [];

    /** @var array */
    public $confirmations = [];

    public $id;
    public $className;
    public $identifier;
    public $identifier_name = 'name';
    public $table;
    public $object;

    public $ajax;
    public $json;
    public $template = 'content.tpl';

    public $token;
    protected $controller_type = 'admin';

    public $context;

    public $override_folder;
    public $tpl_folder;

    public $admin_webpath;

    protected $shopLinkType;

    public $display_header = true;
    public $display_header_javascript = true;
    public $display_footer = true;
    public $content_only;
    public $lite_display = false;

    public $multishop_context;

    //@Question Maybe replace by a voter
    protected $can_import;
    public $allow_export;

    public $bo_theme;
    public $bo_css;
    public $js_files;
    public $css_files;

    public $modals;

    //@Question Do redirection in the ResponseListener
    protected $redirect_after;

    public $lockedToAllShopContext;

    public $meta_title;

    public $breadcrumbs;
    public $toolbar_title;

    public $filter;
    public $fields_list;

    protected $default_form_language;

    public $toolbar_btn;
    public $page_header_toolbar_btn;
    public $show_page_header_toolbar;
    public $page_header_toolbar_title;

    public $bootstrap;

    private $translator;

    /** @var string */
    public $currentIndex;

    public function __construct()
    {
        //@Question instead of this, inject the 'prestashop.adapter.legacy.context' service ?
        $this->context = Context::getContext();
        $this->context->controller = $this;

        $this->ajax = $this->isAjax();
        $this->table = $this->getTable();

        if ($this->multishop_context == -1) {
            $this->multishop_context = Shop::CONTEXT_ALL | Shop::CONTEXT_GROUP | Shop::CONTEXT_SHOP;
        }

        $this->bo_theme = 'default';

        if (defined('_PS_BO_ALL_THEMES_DIR_')) {
            //@Question I don't find the constant and in legacy it's not defined
            //if (defined('_PS_BO_DEFAULT_THEME_') && _PS_BO_DEFAULT_THEME_
            //    && @filemtime(_PS_BO_ALL_THEMES_DIR_ . _PS_BO_DEFAULT_THEME_ . DIRECTORY_SEPARATOR . 'template')) {
            //    $defaultThemeName = _PS_BO_DEFAULT_THEME_;
            //}

            //
            //moved up $this->bo_theme = $defaultThemeName;

            //@Question Not needed because already default
            //if (!@filemtime(_PS_BO_ALL_THEMES_DIR_ . $this->bo_theme . DIRECTORY_SEPARATOR . 'template')) {
            //    $this->bo_theme = 'default';
            //}

            $this->context->employee->bo_theme = (
                Validate::isLoadedObject($this->context->employee)
                && $this->context->employee->bo_theme
            ) ? $this->context->employee->bo_theme : $this->bo_theme;

            $this->bo_css = (
                Validate::isLoadedObject($this->context->employee)
                && $this->context->employee->bo_css
            ) ? $this->context->employee->bo_css : 'theme.css';
            $this->context->employee->bo_css = $this->bo_css;

            $adminThemeCSSFile = _PS_BO_ALL_THEMES_DIR_ . $this->bo_theme . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . $this->bo_css;

            if (file_exists($adminThemeCSSFile)) {
                $this->bo_css = 'theme.css';
            }

            $this->context->smarty->setTemplateDir([
                _PS_BO_ALL_THEMES_DIR_ . $this->bo_theme . DIRECTORY_SEPARATOR . 'template',
                _PS_OVERRIDE_DIR_ . 'controllers' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'templates',
            ]);
        }

        //@Question Used new method, but inject the repository
        $this->id = Tab::getIdFromClassName($this->getLegacyController());
        $this->token = Tools::getAdminToken($this->getLegacyController() . (int) $this->id . (int) $this->context->employee->id);

        //Moved in init
        //$this->initConfig();
        //$this->initError();

        if (!$this->identifier) {
            $this->identifier = 'id_' . $this->table;
        }
        if (!$this->_defaultOrderBy) {
            $this->_defaultOrderBy = $this->identifier;
        }

        // Fix for homepage
        //@Question Not needed, I think
        //if ($this->controller_name == 'AdminDashboard') {
        //    $_POST['token'] = $this->token;
        //}

        if (!Shop::isFeatureActive()) {
            $this->shopLinkType = '';
        }

        $this->override_folder = Tools::toUnderscoreCase(substr($this->getLegacyController(), 5)) . '/';
        // Get the name of the folder containing the custom tpl files
        $this->tpl_folder = Tools::toUnderscoreCase(substr($this->getLegacyController(), 5)) . '/';

        $this->initShopContext();

        if (defined('_PS_ADMIN_DIR_')) {
            $this->admin_webpath = str_ireplace(_PS_CORE_DIR_, '', _PS_ADMIN_DIR_);
            $this->admin_webpath = preg_replace('/^' . preg_quote(DIRECTORY_SEPARATOR, '/') . '/', '', $this->admin_webpath);
        }

        // Set context mode
        if (defined('_PS_HOST_MODE_') && _PS_HOST_MODE_) {
            if (isset($this->context->cookie->is_contributor) && (int) $this->context->cookie->is_contributor === 1) {
                $this->context->mode = Context::MODE_HOST_CONTRIB;
            } else {
                $this->context->mode = Context::MODE_HOST;
            }
        } elseif (isset($this->context->cookie->is_contributor) && (int) $this->context->cookie->is_contributor === 1) {
            $this->context->mode = Context::MODE_STD_CONTRIB;
        } else {
            $this->context->mode = Context::MODE_STD;
        }

        /* Check if logged employee has access to AdminImport controller */
        $import_access = Profile::getProfileAccess($this->context->employee->id_profile, Tab::getIdFromClassName('AdminImport'));
        if (is_array($import_access) && isset($import_access['view']) && $import_access['view'] == 1) {
            $this->can_import = true;
        }

        $this->context->smarty->assign([
            'context_mode' => $this->context->mode,
            'logged_on_addons' => $this->isLoggedWithAddOn(),
            'can_import' => $this->can_import,
        ]);
    }

    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))")
     */
    public function indexAction(): ?Response
    {
        return new Response();
    }

    /**
     * Returns if the current request is an AJAX request.
     *
     * @return bool
     */
    private function isAjax()
    {
        // Usage of ajax parameter is deprecated
        $isAjax = Tools::getValue('ajax') || Tools::isSubmit('ajax');

        if (isset($_SERVER['HTTP_ACCEPT'])) {
            $isAjax = $isAjax || preg_match(
                    '#\bapplication/json\b#',
                    $_SERVER['HTTP_ACCEPT']
                );
        }

        return $isAjax;
    }

    /*
     * @Todo moved this in another class
     *
     * @throws \PrestaShopException
     */
    public function initShopContext()
    {
        // Do not initialize context when the shop is not installed
        if (defined('PS_INSTALLATION_IN_PROGRESS')) {
            return;
        }

        // Change shop context ?
        if (Shop::isFeatureActive() && Tools::getValue('setShopContext') !== false) {
            $this->context->cookie->shopContext = Tools::getValue('setShopContext');
            $url = parse_url($_SERVER['REQUEST_URI']);
            $query = (isset($url['query'])) ? $url['query'] : '';
            parse_str($query, $parse_query);
            unset($parse_query['setShopContext'], $parse_query['conf']);
            $http_build_query = http_build_query($parse_query, '', '&');
            $this->redirect_after = $url['path'] . ($http_build_query ? '?' . $http_build_query : '');
        } elseif (!Shop::isFeatureActive()) {
            $this->context->cookie->shopContext = 's-' . (int) Configuration::get('PS_SHOP_DEFAULT');
        } elseif (Shop::getTotalShops(false, null) < 2 && $this->context->employee->isLoggedBack()) {
            $this->context->cookie->shopContext = 's-' . (int) $this->context->employee->getDefaultShopID();
        }

        $shop_id = null;
        Shop::setContext(Shop::CONTEXT_ALL);
        if ($this->context->cookie->shopContext && $this->context->employee->isLoggedBack()) {
            $split = explode('-', $this->context->cookie->shopContext);
            if (count($split) == 2) {
                if ($split[0] == 'g') {
                    if ($this->context->employee->hasAuthOnShopGroup((int) $split[1])) {
                        Shop::setContext(Shop::CONTEXT_GROUP, (int) $split[1]);
                    } else {
                        $shop_id = (int) $this->context->employee->getDefaultShopID();
                        Shop::setContext(Shop::CONTEXT_SHOP, $shop_id);
                    }
                } elseif (Shop::getShop($split[1]) && $this->context->employee->hasAuthOnShop($split[1])) {
                    $shop_id = (int) $split[1];
                    Shop::setContext(Shop::CONTEXT_SHOP, $shop_id);
                } else {
                    $shop_id = (int) $this->context->employee->getDefaultShopID();
                    Shop::setContext(Shop::CONTEXT_SHOP, $shop_id);
                }
            }
        }

        // Check multishop context and set right context if need
        if (!($this->multishop_context & Shop::getContext())) {
            if (Shop::getContext() == Shop::CONTEXT_SHOP && !($this->multishop_context & Shop::CONTEXT_SHOP)) {
                Shop::setContext(Shop::CONTEXT_GROUP, Shop::getContextShopGroupID());
            }
            if (Shop::getContext() == Shop::CONTEXT_GROUP && !($this->multishop_context & Shop::CONTEXT_GROUP)) {
                Shop::setContext(Shop::CONTEXT_ALL);
            }
        }

        // Replace existing shop if necessary
        if (!$shop_id) {
            $this->context->shop = new Shop((int) Configuration::get('PS_SHOP_DEFAULT'));
        } elseif ($this->context->shop->id != $shop_id) {
            $this->context->shop = new Shop((int) $shop_id);
        }

        // Replace current default country
        $this->context->country = new Country((int) Configuration::get('PS_COUNTRY_DEFAULT'));
        $this->context->currency = new Currency(Configuration::get('PS_CURRENCY_DEFAULT'));
    }

    //@Question on garde ??
    public static function myErrorHandler($errno, $errstr, $errfile, $errline)
    {
        /**
         * Prior to PHP 8.0.0, the $errno value was always 0 if the expression which caused the diagnostic was prepended by the @ error-control operator.
         *
         * @see https://www.php.net/manual/fr/function.set-error-handler.php
         * @see https://www.php.net/manual/en/language.operators.errorcontrol.php
         */
        if (!(error_reporting() & $errno)) {
            return false;
        }

        switch ($errno) {
            case E_USER_ERROR:
            case E_ERROR:
                die('Fatal error: ' . $errstr . ' in ' . $errfile . ' on line ' . $errline);

                break;
            case E_USER_WARNING:
            case E_WARNING:
                $type = 'Warning';

                break;
            case E_USER_NOTICE:
            case E_NOTICE:
                $type = 'Notice';

                break;
            default:
                $type = 'Unknown error';

                break;
        }

        Context::getContext()->smarty->assign('php_errors', [
            'type' => $type,
            'errline' => (int) $errline,
            'errfile' => str_replace('\\', '\\\\', $errfile), // Hack for Windows paths
            'errno' => (int) $errno,
            'errstr' => $errstr,
        ]);

        return true;
    }

    /**
     * Add an entry to the meta title.
     *
     * @param string $entry new entry
     */
    public function addMetaTitle($entry)
    {
        // Only add entry if the meta title was not forced.
        if (is_array($this->meta_title)) {
            $this->meta_title[] = $entry;
        }
    }

    //@Question I create this function to replace the logged_on_addons. WDYT ?
    public function isLoggedWithAddOn(): bool
    {
        return !empty($this->context->cookie->username_addons)
            && !empty($this->context->cookie->password_addons)
        ;
    }

    /**
     * @return array
     */
    //@Question move this in other class, or use existent ?
    //But let this alias
    public function getLanguages()
    {
        $cookie = $this->context->cookie;
        $allowEmployeeFormLang = (int) Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');
        if ($allowEmployeeFormLang && !$cookie->employee_form_lang) {
            $cookie->employee_form_lang = (int) Configuration::get('PS_LANG_DEFAULT');
        }

        $lang_exists = false;
        $this->_languages = Language::getLanguages(false);
        foreach ($this->_languages as $lang) {
            if (isset($cookie->employee_form_lang) && $cookie->employee_form_lang == $lang['id_lang']) {
                $lang_exists = true;
            }
        }

        $this->default_form_language = $lang_exists ? (int) $cookie->employee_form_lang : (int) Configuration::get('PS_LANG_DEFAULT');

        foreach ($this->_languages as $k => $language) {
            $this->_languages[$k]['is_default'] = (int) ($language['id_lang'] == $this->default_form_language);
        }

        return $this->_languages;
    }

    /**
     * Set the filters used for the list display.
     */
    public function getCookieFilterPrefix()
    {
        return str_replace(['admin', 'controller'], '', Tools::strtolower(get_class($this)));
    }

    /**
     * Load class object using identifier in $_GET (if possible)
     * otherwise return an empty object, or die.
     *
     * @param bool $opt Return an empty object if load fail
     *
     * @return ObjectModel|bool
     */
    public function loadObject($opt = false)
    {
        return $this->get('prestashop.legacy_controller.load_object')->loadObject($this, (bool) $opt);
    }

    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    public function init(): void
    {
        $this->dispatchHook(
            'actionControllerInitBefore',
            [
                'controller' => $this,
            ]
        );

        $this->get('prestashop.legacy_controller.init_controller')->init($this);

        $this->initConfig();
        $this->initError();
        $this->initMultistoreHeader();
        $this->initBreadcrumbs();
        $this->initModal();
        $this->initToolbarFlags();
        $this->initNotifications();

        $this->dispatchHook(
            'actionControllerInitAfter',
            [
                'controller' => $this,
            ]
        );
    }

    public function initMultistoreHeader(): void
    {
        $this->get('prestashop.legacy_controller.init_controller')->initMultistoreHeader($this);
    }

    public function initBreadcrumbs(): void
    {
        $this->get('prestashop.legacy_controller.init_controller')->initBreadcrumbs($this);
    }

    public function initModal(): void
    {
        $this->get('prestashop.legacy_controller.init_controller')->initModal($this);
    }

    public function initToolbarFlags(): void
    {
        $this->getLanguages();

        $this->initToolbar();
        $this->initPageHeaderToolbar();
        $this->get('prestashop.legacy_controller.init_controller')->initToolbarFlags($this);
    }

    public function initToolbar(): void
    {
        $this->get('prestashop.legacy_controller.init_controller')->initToolbar($this);
    }

    public function initPageHeaderToolbar(): void
    {
        if (empty($this->toolbar_title)) {
            $this->initToolbarTitle();
        }

        $this->get('prestashop.legacy_controller.init_controller')->initPageHeaderToolbar($this);
    }

    public function initToolbarTitle(): void
    {
        $this->get('prestashop.legacy_controller.init_controller')->initToolbarTitle($this);
    }

    public function initNotifications(): void
    {
        $this->get('prestashop.legacy_controller.init_controller')->initNotifications($this);
    }

    private function initConfig(): void
    {
        //@Question Is it trully used
        //Moved in a method to load conf => si je veux le supprimer faire une PR à part pour faire le changement
        $this->_conf = [
            1 => $this->translator->trans('Successful deletion.', [], 'Admin.Notifications.Success'),
            2 => $this->translator->trans('The selection has been successfully deleted.', [], 'Admin.Notifications.Success'),
            3 => $this->translator->trans('Successful creation.', [], 'Admin.Notifications.Success'),
            4 => $this->translator->trans('Successful update.', [], 'Admin.Notifications.Success'),
            5 => $this->translator->trans('The status has been successfully updated.', [], 'Admin.Notifications.Success'),
            6 => $this->translator->trans('The settings have been successfully updated.', [], 'Admin.Notifications.Success'),
            7 => $this->translator->trans('The image was successfully deleted.', [], 'Admin.Notifications.Success'),
            8 => $this->translator->trans('The module was successfully downloaded.', [], 'Admin.Modules.Notification'),
            9 => $this->translator->trans('The thumbnails were successfully regenerated.', [], 'Admin.Notifications.Success'),
            10 => $this->translator->trans('The message was successfully sent to the customer.', [], 'Admin.Orderscustomers.Notification'),
            11 => $this->translator->trans('Comment successfully added.', [], 'Admin.Notifications.Success'),
            12 => $this->translator->trans('Module(s) installed successfully.', [], 'Admin.Modules.Notification'),
            13 => $this->translator->trans('Module(s) uninstalled successfully.', [], 'Admin.Modules.Notification'),
            14 => $this->translator->trans('The translation was successfully copied.', [], 'Admin.International.Notification'),
            15 => $this->translator->trans('The translations have been successfully added.', [], 'Admin.International.Notification'),
            16 => $this->translator->trans('The module transplanted successfully to the hook.', [], 'Admin.Modules.Notification'),
            17 => $this->translator->trans('The module was successfully removed from the hook.', [], 'Admin.Modules.Notification'),
            18 => $this->translator->trans('Successful upload.', [], 'Admin.Notifications.Success'),
            19 => $this->translator->trans('Duplication was completed successfully.', [], 'Admin.Notifications.Success'),
            20 => $this->translator->trans('The translation was added successfully, but the language has not been created.', [], 'Admin.International.Notification'),
            21 => $this->translator->trans('Module reset successfully.', [], 'Admin.Modules.Notification'),
            22 => $this->translator->trans('Module deleted successfully.', [], 'Admin.Modules.Notification'),
            23 => $this->translator->trans('Localization pack imported successfully.', [], 'Admin.International.Notification'),
            24 => $this->translator->trans('Localization pack imported successfully.', [], 'Admin.International.Notification'),
            25 => $this->translator->trans('The selected images have successfully been moved.', [], 'Admin.Notifications.Success'),
            26 => $this->translator->trans('Your cover image selection has been saved.', [], 'Admin.Notifications.Success'),
            27 => $this->translator->trans('The image\'s shop association has been modified.', [], 'Admin.Notifications.Success'),
            28 => $this->translator->trans('A zone has been assigned to the selection successfully.', [], 'Admin.Notifications.Success'),
            29 => $this->translator->trans('Successful upgrade.', [], 'Admin.Notifications.Success'),
            30 => $this->translator->trans('A partial refund was successfully created.', [], 'Admin.Orderscustomers.Notification'),
            31 => $this->translator->trans('The discount was successfully generated.', [], 'Admin.Catalog.Notification'),
            32 => $this->translator->trans('Successfully signed in to PrestaShop Addons.', [], 'Admin.Modules.Notification'),
        ];
    }

    private function initError(): void
    {
        //@Question Is it trully used
        //Moved in a method to load conf
        $this->_error = [
            1 => $this->translator->trans(
                'The root category of the shop %shop% is not associated with the current shop. You can\'t access this page. Please change the root category of the shop.',
                [
                    '%shop%' => $this->context->shop->name,
                ],
                'Admin.Catalog.Notification'
            ),
        ];
    }

    /**
     * Adds a new JavaScript file(s) to the page header.
     *
     * @param string|array $js_uri Path to JS file or an array like: array(uri, ...)
     * @param bool $check_path
     */
    public function addJS($js_uri, $check_path = true)
    {
        if (!is_array($js_uri)) {
            $js_uri = [$js_uri];
        }

        foreach ($js_uri as $js_file) {
            $js_file = explode('?', $js_file);
            $version = '';
            if (isset($js_file[1]) && $js_file[1]) {
                $version = $js_file[1];
            }
            $js_path = $js_file = $js_file[0];
            if ($check_path) {
                $js_path = Media::getJSPath($js_file);
            }

            if ($js_path && !in_array($js_path, $this->js_files)) {
                $this->js_files[] = $js_path . ($version ? '?' . $version : '');
            }
        }
    }

    /**
     * Adds a new stylesheet(s) to the page header.
     *
     * @param string|array $css_uri Path to CSS file, or list of css files like this : array(array(uri => media_type), ...)
     * @param string $css_media_type
     * @param int|null $offset
     * @param bool $check_path
     *
     * @return void
     */
    public function addCSS($css_uri, $css_media_type = 'all', $offset = null, $check_path = true)
    {
        if (!is_array($css_uri)) {
            $css_uri = [$css_uri];
        }

        foreach ($css_uri as $css_file => $media) {
            if (is_string($css_file) && strlen($css_file) > 1) {
                if ($check_path) {
                    $css_path = Media::getCSSPath($css_file, $media);
                } else {
                    $css_path = [$css_file => $media];
                }
            } else {
                if ($check_path) {
                    $css_path = Media::getCSSPath($media, $css_media_type);
                } else {
                    $css_path = [$media => $css_media_type];
                }
            }

            $key = is_array($css_path) ? key($css_path) : $css_path;
            if ($css_path && (!isset($this->css_files[$key]) || ($this->css_files[$key] != reset($css_path)))) {
                $size = count($this->css_files);
                if ($offset === null || $offset > $size || $offset < 0 || !is_numeric($offset)) {
                    $offset = $size;
                }

                $this->css_files = array_merge(array_slice($this->css_files, 0, $offset), $css_path, array_slice($this->css_files, $offset));
            }
        }
    }

    /**
     * @return string
     *
     * @throws Exception
     * @throws SmartyException
     */
    public function renderModal()
    {
        $modal_render = '';
        if (is_array($this->modals) && count($this->modals)) {
            foreach ($this->modals as $modal) {
                $this->context->smarty->assign($modal);
                $modal_render .= $this->context->smarty->fetch('modal.tpl');
            }
        }

        return $modal_render;
    }

    public function getDisplayFromAction(): string
    {
        $request = $this->get('request_stack')->getCurrentRequest();
        $routeElements = explode('_', $request->attributes->get('_route'));

        return !in_array(end($routeElements), self::NEED_DISPLAY_ACTION) ? 'list' : end($routeElements);
    }

    public function createTemplate($tpl_name)
    {
        return $this->get('prestashop.legacy_controller.rendering')->createTemplate($this, (string) $tpl_name);
    }
}
