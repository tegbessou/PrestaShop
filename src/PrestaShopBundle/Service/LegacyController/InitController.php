<?php

namespace PrestaShopBundle\Service\LegacyController;

use Configuration;
use Context;
use Employee;
use Language;
use Link;
use Media;
use PrestaShop\PrestaShop\Core\Localization\Locale\Repository;
use PrestaShopBundle\Controller\Admin\LegacyAdminController;
use PrestaShopBundle\Controller\Admin\MultistoreController;
use PrestaShopBundle\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Tab;
use Tools;
use Validate;

class InitController
{
    public const AUTH_COOKIE_LIFETIME = 3600;

    /**
     * @var Repository
     */
    private $localeRepository;

    /**
     * @var MultistoreController
     */
    private $multistore;
    /**
     * @var TranslatorInterface
     */
    private $translator;
    /**
     * @var Request
     */
    private $request;

    public function __construct(Repository $localeRepository, MultistoreController $multistore, TranslatorInterface $translator, RequestStack $requestStack)
    {
        $this->localeRepository = $localeRepository;
        $this->multistore = $multistore;
        $this->translator = $translator;
        $this->request = $requestStack->getCurrentRequest();
    }

    public function init(LegacyAdminController $controller): void
    {
        if (_PS_MODE_DEV_) {
            set_error_handler([get_class($controller), 'myErrorHandler']);
        }

        if (!defined('_PS_BASE_URL_')) {
            define('_PS_BASE_URL_', Tools::getShopDomain(true));
        }

        if (!defined('_PS_BASE_URL_SSL_')) {
            define('_PS_BASE_URL_SSL_', Tools::getShopDomainSsl(true));
        }

        //Moved this in other function
        $controller->context->currentLocale = $this->localeRepository->getLocale(
            $controller->context->language->getLocale()
        );

        if (null === $controller->context->link) {
            $protocolLink = (Tools::usingSecureMode() && Configuration::get('PS_SSL_ENABLED')) ? 'https://' : 'http://';
            $protocolContent = (Tools::usingSecureMode() && Configuration::get('PS_SSL_ENABLED')) ? 'https://' : 'http://';
            $controller->context->link = new Link($protocolLink, $protocolContent);
        }

        //@Question
        //Maybe logout on a different way => Make a verification and create a following board => HG
        //Redirect to logout page
        if (isset($_GET['logout'])) {
            $controller->context->employee->logout();
        }
        if (isset(Context::getContext()->cookie->last_activity)) {
            if (((int) $controller->context->cookie->last_activity) + self::AUTH_COOKIE_LIFETIME < time()) {
                $controller->context->employee->logout();
            } else {
                $controller->context->cookie->last_activity = time();
            }
        }

        //@Question
        //Let's symfony security work on this, no? => Make verification que c'est géré comme ça partout et put this in a listener
        //if (
        //    !$this->isAnonymousAllowed()
        //    && (
        //        $this->controller_name != 'AdminLogin'
        //        && (
        //            !isset($this->context->employee)
        //            || !$this->context->employee->isLoggedBack()
        //        )
        //    )
        //) {
        //    if (isset($this->context->employee)) {
        //        $this->context->employee->logout();
        //    }
        //    $email = false;
        //    if (Tools::getValue('email') && Validate::isEmail(Tools::getValue('email'))) {
        //        $email = Tools::getValue('email');
        //    }
        //    Tools::redirectAdmin($this->context->link->getAdminLink('AdminLogin') . ((!isset($_GET['logout']) && $this->controller_name != 'AdminNotFound' && Tools::getValue('controller')) ? '&redirect=' . $this->controller_name : '') . ($email ? '&email=' . $email : ''));
        //}

        $currentIndex = 'index.php' . (($controller = Tools::getValue('controller')) ? '?controller=' . $controller : '');
        if ($back = Tools::getValue('back')) {
            $currentIndex .= '&back=' . urlencode($back);
        }
        $controller->currentIndex = $currentIndex;

        if ((int) Tools::getValue('liteDisplaying')) {
            $controller->display_header = false;
            $controller->display_header_javascript = true;
            $controller->display_footer = false;
            $controller->content_only = false;
            $controller->lite_display = true;
        }

        if ($controller->ajax && method_exists($controller, 'ajaxPreprocess')) {
            $controller->ajaxPreProcess();
        }

        Employee::setLastConnectionDate($controller->context->employee->id);

        //@Question
        //We can remove this => non car peut-être dans le controller
        //I remove this from here to move this in the LegacyAdminController
        //$this->initProcess();
        //$this->initMultistoreHeader($controller);
        //Remaining
        //$this->initBreadcrumbs();
        //$this->initModal();
        //$this->initToolbarFlags();
        //$this->initNotifications();
    }

    /**
     * Gets the multistore header and assigns its html content to a smarty variable
     *
     * @see PrestaShopBundle\Controller\Admin\MultistoreController
     *
     * (the decision to display it or not is taken by the MultistoreController)
     */
    public function initMultistoreHeader(LegacyAdminController $controller): void
    {
        if (!isset($controller->lockedToAllShopContext)) {
            return;
        }

        $controller->context->smarty->assign([
            'multistore_header' => $this->multistore->header($controller->lockedToAllShopContext)->getContent(),
        ]);
    }

    /**
     * Set breadcrumbs array for the controller page.
     *
     * @param int|null $tab_id
     * @param array|null $tabs
     */
    public function initBreadcrumbs(LegacyAdminController $controller, $tab_id = null, $tabs = null)
    {
        if (!is_array($tabs)) {
            $tabs = [];
        }

        if (null === $tab_id) {
            $tab_id = $controller->id;
        }

        $tabs = Tab::recursiveTab($tab_id, $tabs);

        $dummy = ['name' => '', 'href' => '', 'icon' => ''];
        $breadcrumbs2 = [
            'container' => $dummy,
            'tab' => $dummy,
            'action' => $dummy,
        ];
        if (!empty($tabs[0])) {
            $controller->addMetaTitle($tabs[0]['name']);
            $breadcrumbs2['tab']['name'] = $tabs[0]['name'];
            $breadcrumbs2['tab']['href'] = $controller->context->link->getTabLink($tabs[0]);
            if (!isset($tabs[1])) {
                $breadcrumbs2['tab']['icon'] = 'icon-' . $tabs[0]['class_name'];
            }
        }
        if (!empty($tabs[1])) {
            $breadcrumbs2['container']['name'] = $tabs[1]['name'];
            $breadcrumbs2['container']['href'] = $controller->context->link->getTabLink($tabs[1]);
            $breadcrumbs2['container']['icon'] = 'icon-' . $tabs[1]['class_name'];
        }

        /* content, edit, list, add, details, options, view */
        //@Question @Todo Move in another class or method
        switch ($controller->getDisplayFromAction()) {
            case 'add':
                $breadcrumbs2['action']['name'] = $this->translator->trans('Add');
                $breadcrumbs2['action']['icon'] = 'icon-plus';

                break;
            case 'edit':
                $breadcrumbs2['action']['name'] = $this->translator->trans('Edit');
                $breadcrumbs2['action']['icon'] = 'icon-pencil';

                break;
            //I delete this because I don't need this case '':
            case 'list':
                $breadcrumbs2['action']['name'] = $this->translator->trans('List');
                $breadcrumbs2['action']['icon'] = 'icon-th-list';

                break;
            case 'details':
            case 'view':
                $breadcrumbs2['action']['name'] = $this->translator->trans('View details');
                $breadcrumbs2['action']['icon'] = 'icon-zoom-in';

                break;
            case 'options':
                $breadcrumbs2['action']['name'] = $this->translator->trans('Options');
                $breadcrumbs2['action']['icon'] = 'icon-cogs';

                break;
            case 'generator':
                $breadcrumbs2['action']['name'] = $this->translator->trans('Generator');
                $breadcrumbs2['action']['icon'] = 'icon-flask';

                break;
        }

        $controller->context->smarty->assign([
            'breadcrumbs2' => $breadcrumbs2,
            'quick_access_current_link_name' => Tools::safeOutput($breadcrumbs2['tab']['name'] . (isset($breadcrumbs2['action']) ? ' - ' . $breadcrumbs2['action']['name'] : '')),
            'quick_access_current_link_icon' => $breadcrumbs2['container']['icon'],
        ]);

        /* BEGIN - Backward compatibility < 1.6.0.3 */
        $controller->breadcrumbs[] = $tabs[0]['name'] ?? '';
        $navigation_pipe = (Configuration::get('PS_NAVIGATION_PIPE') ? Configuration::get('PS_NAVIGATION_PIPE') : '>');
        $controller->context->smarty->assign('navigationPipe', $navigation_pipe);
        /* END - Backward compatibility < 1.6.0.3 */
    }

    public function initModal(LegacyAdminController $controller)
    {
        if ($controller->isLoggedWithAddOn()) {
            $controller->context->smarty->assign([
                'logged_on_addons' => 1,
                'username_addons' => $controller->context->cookie->username_addons,
            ]);
        }

        $controller->context->smarty->assign([
            'img_base_path' => __PS_BASE_URI__ . basename(_PS_ADMIN_DIR_) . '/',
            'check_url_fopen' => (ini_get('allow_url_fopen') ? 'ok' : 'ko'),
            'check_openssl' => (extension_loaded('openssl') ? 'ok' : 'ko'),
            'add_permission' => 1,
        ]);
    }

    //@Question, can I moved this in another class it was not surcharged
    public function initToolbarFlags(LegacyAdminController $controller)
    {
        //@Question: Remove this because I move this in proxy method
        //$this->controller->getLanguages();
        //
        //$this->initToolbar();
        //$this->initPageHeaderToolbar();

        $controller->context->smarty->assign([
            'maintenance_mode' => !(bool) Configuration::get('PS_SHOP_ENABLE'),
            'debug_mode' => (bool) _PS_MODE_DEV_,
            'lite_display' => $controller->lite_display,
            'url_post' => $this->currentIndex . '&token=' . $controller->token,
            'show_page_header_toolbar' => $controller->show_page_header_toolbar,
            'page_header_toolbar_title' => $controller->page_header_toolbar_title,
            'title' => $controller->page_header_toolbar_title,
            'toolbar_btn' => $controller->page_header_toolbar_btn,
            'page_header_toolbar_btn' => $controller->page_header_toolbar_btn,
        ]);
    }

    /**
     * assign default action in toolbar_btn smarty var, if they are not set.
     * uses override to specifically add, modify or remove items.
     */
    public function initToolbar(LegacyAdminController $controller)
    {
        switch ($controller->getDisplayFromAction()) {
            case 'add':
            case 'edit':
                // Default save button - action dynamically handled in javascript
                $controller->toolbar_btn['save'] = [
                    'href' => '#',
                    'desc' => $this->translator->trans('Save'),
                ];
                $back = $this->getBackLink($controller->currentIndex . '&token=' . $controller->token);
                $this->validateBackLink($back);
                if (!$controller->lite_display) {
                    $controller->toolbar_btn['cancel'] = [
                        'href' => $back,
                        'desc' => $this->translator->trans('Cancel'),
                    ];
                }

                break;
            case 'view':
                $back = $this->getBackLink($controller->currentIndex . '&token=' . $controller->token);
                $this->validateBackLink($back);

                if (!$controller->lite_display) {
                    $controller->toolbar_btn['back'] = [
                        'href' => $back,
                        'desc' => $this->translator->trans('Back to list'),
                    ];
                }

                break;
            case 'options':
                $controller->toolbar_btn['save'] = [
                    'href' => '#',
                    'desc' => $this->translator->trans('Save'),
                ];

                break;
            default:
                // list
                $controller->toolbar_btn['new'] = [
                    'href' => $controller->currentIndex . '&add' . $controller->table . '&token=' . $controller->token,
                    'desc' => $this->translator->trans('Add new'),
                ];
                if ($controller->allow_export) {
                    $controller->toolbar_btn['export'] = [
                        'href' => $controller->currentIndex . '&export' . $controller->table . '&token=' . $controller->token,
                        'desc' => $this->translator->trans('Export'),
                    ];
                }
        }
    }

    public function initPageHeaderToolbar(LegacyAdminController $controller)
    {
        //@Question Moved in the proxy method
        //if (empty($this->toolbar_title)) {
        //    $this->initToolbarTitle();
        //}

        if (!is_array($controller->toolbar_title)) {
            $controller->toolbar_title = [$controller->toolbar_title];
        }

        switch ($controller->getDisplayFromAction()) {
            case 'view':
                // Default cancel button - like old back link
                $back = $this->getBackLink($controller->currentIndex . '&token=' . $controller->token);
                $this->validateBackLink($back);
                if (!$controller->lite_display) {
                    $controller->page_header_toolbar_btn['back'] = [
                        'href' => $back,
                        'desc' => $this->translator->trans('Back to list'),
                    ];
                }
                $obj = $controller->loadObject(true);
                if (Validate::isLoadedObject($obj) && isset($obj->{$controller->identifier_name}) && !empty($obj->{$controller->identifier_name})) {
                    array_pop($controller->toolbar_title);
                    array_pop($controller->meta_title);
                    $controller->toolbar_title[] = is_array($obj->{$controller->identifier_name}) ? $obj->{$controller->identifier_name}[$controller->context->employee->id_lang] : $obj->{$controller->identifier_name};
                    $controller->addMetaTitle($controller->toolbar_title[count($controller->toolbar_title) - 1]);
                }

                break;
            case 'edit':
                $obj = $controller->loadObject(true);
                if (Validate::isLoadedObject($obj) && isset($obj->{$controller->identifier_name}) && !empty($obj->{$controller->identifier_name})) {
                    array_pop($controller->toolbar_title);
                    array_pop($controller->meta_title);
                    $controller->toolbar_title[] = $this->translator->trans(
                        'Edit: %s',
                        [
                            (is_array($obj->{$controller->identifier_name})
                                && isset($obj->{$controller->identifier_name}[$controller->context->employee->id_lang])
                            )
                                ? $obj->{$controller->identifier_name}[$controller->context->employee->id_lang]
                                : $obj->{$controller->identifier_name},
                        ]
                    );
                    $controller->addMetaTitle($controller->toolbar_title[count($controller->toolbar_title) - 1]);
                }

                break;
        }

        if (is_array($controller->page_header_toolbar_btn)
            && $controller->page_header_toolbar_btn instanceof \Traversable
            || count($controller->toolbar_title)) {
            $controller->show_page_header_toolbar = true;
        }

        if (empty($controller->page_header_toolbar_title)) {
            $controller->page_header_toolbar_title = $controller->toolbar_title[count($controller->toolbar_title) - 1];
        }

        $controller->context->smarty->assign('help_link', 'https://help.prestashop.com/' . Language::getIsoById($controller->context->employee->id_lang) . '/doc/'
            . Tools::getValue('controller') . '?version=' . _PS_VERSION_ . '&country=' . Language::getIsoById($controller->context->employee->id_lang));
    }

    public function initToolbarTitle(LegacyAdminController $controller): void
    {
        $controller->toolbar_title = is_array($controller->breadcrumbs)
            ? array_unique($controller->breadcrumbs)
            : [$controller->breadcrumbs];

        switch ($controller->getDisplayFromAction()) {
            case 'edit':
                $controller->toolbar_title[] = $this->translator->trans('Edit');
                $controller->addMetaTitle($this->translator->trans('Edit'));

                break;

            case 'add':
                $controller->toolbar_title[] = $this->translator->trans('Add new');
                $controller->addMetaTitle($this->translator->trans('Add new'));

                break;

            case 'view':
                $controller->toolbar_title[] = $this->translator->trans('View');
                $controller->addMetaTitle($this->translator->trans('View'));

                break;
        }

        if ($filter = $this->addFiltersToBreadcrumbs($controller)) {
            $controller->toolbar_title[] = $filter;
        }
    }

    /**
     * @return string|void
     */
    //@Question: The method addFiltersToBreadcrumbs is never override, so I put it private. It is a problem ?
    private function addFiltersToBreadcrumbs(LegacyAdminController $controller)
    {
        if ($controller->filter && is_array($controller->fields_list)) {
            $filters = [];

            foreach ($controller->fields_list as $field => $t) {
                if (isset($t['filter_key'])) {
                    $field = $t['filter_key'];
                }

                if (($val = Tools::getValue($controller->table . 'Filter_' . $field)) || $val = $controller->context->cookie->{$controller->getCookieFilterPrefix() . $controller->table . 'Filter_' . $field}) {
                    if (!is_array($val)) {
                        $filter_value = '';
                        if (isset($t['type']) && $t['type'] == 'bool') {
                            $filter_value = ((bool) $val) ? $this->translator->trans('yes') : $this->translator->trans('no');
                        } elseif (isset($t['type']) && $t['type'] == 'date' || isset($t['type']) && $t['type'] == 'datetime') {
                            $date = json_decode($val, true);
                            if (isset($date[0])) {
                                $filter_value = $date[0];
                                if (isset($date[1]) && !empty($date[1])) {
                                    $filter_value .= ' - ' . $date[1];
                                }
                            }
                        } elseif (is_string($val)) {
                            $filter_value = htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
                        }
                        if (!empty($filter_value)) {
                            $filters[] = $this->translator->trans('%s: %s', [$t['title'], $filter_value]);
                        }
                    } else {
                        $filter_value = '';
                        foreach ($val as $v) {
                            if (is_string($v) && !empty($v)) {
                                $filter_value .= ' - ' . htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
                            }
                        }
                        $filter_value = ltrim($filter_value, ' -');
                        if (!empty($filter_value)) {
                            $filters[] = $this->translator->trans('%s: %s', [$t['title'], $filter_value]);
                        }
                    }
                }
            }

            if (count($filters)) {
                return $this->translator->trans('filter by %s', [implode(', ', $filters)]);
            }
        }
    }

    public function initNotifications(LegacyAdminController $controller)
    {
        $notificationsSettings = [
            'show_new_orders' => Configuration::get('PS_SHOW_NEW_ORDERS'),
            'show_new_customers' => Configuration::get('PS_SHOW_NEW_CUSTOMERS'),
            'show_new_messages' => Configuration::get('PS_SHOW_NEW_MESSAGES'),
        ];
        $controller->context->smarty->assign($notificationsSettings);

        Media::addJsDef($notificationsSettings);
    }

    //@Todo move this in another class
    private function getBackLink(string $defaultValue = null)
    {
        $back = Tools::safeOutput(Tools::getValue('back', ''));

        return empty($back)
            ? $defaultValue
            : $back
        ;
    }

    //@Todo move this in another class
    private function validateBackLink(string $back): void
    {
        if (!Validate::isCleanHtml($back)) {
            die(Tools::displayError());
        }
    }
}
