<?php

namespace PrestaShopBundle\Service\LegacyController;

use Configuration;
use Context;
use HelperShop;
use PrestaShop\PrestaShop\Core\Hook\HookDispatcher;
use PrestaShopBundle\Component\ActionBar\ActionsBarButtonsCollection;
use PrestaShopBundle\Controller\Admin\LegacyAdminController;
use PrestaShopBundle\Translation\TranslatorInterface;
use Profile;
use QuickAccess;
use Shop;
use ShopGroup;
use Tab;
use Tools;
use Validate;

class InitHeader
{
    /**
     * @var HookDispatcher
     */
    private $hookDispatcher;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    public function __construct(HookDispatcher $hookDispatcher, TranslatorInterface $translator)
    {
        $this->hookDispatcher = $hookDispatcher;
        $this->translator = $translator;
    }
    public function initHeader(LegacyAdminController $controller)
    {
        header('Cache-Control: no-store, no-cache');

        $controller->context->smarty->assign([
            'table' => $controller->table,
            'current' => $controller->currentIndex,
            'token' => $controller->token,
            'host_mode' => (int) defined('_PS_HOST_MODE_'),
            'stock_management' => (int) Configuration::get('PS_STOCK_MANAGEMENT'),
            'no_order_tip' => $this->getNotificationTip($controller, 'order'),
            'no_customer_tip' => $this->getNotificationTip($controller, 'customer'),
            'no_customer_message_tip' => $this->getNotificationTip($controller, 'customer_message'),
        ]);

        if ($controller->display_header) {
            $controller->context->smarty->assign(
                'displayBackOfficeHeader',
                $this->hookDispatcher->dispatchRenderingWithParameters('displayBackOfficeHeader')
            );
        }

        // Fetch Employee Menu
        $menuLinksCollections = new ActionsBarButtonsCollection();
        $this->hookDispatcher->dispatchRenderingWithParameters(
            'displayBackOfficeEmployeeMenu',
            [
                'links' => $menuLinksCollections,
            ]
        );
        //Hook::exec(
        //    'displayBackOfficeEmployeeMenu',
        //    [
        //        'links' => $menuLinksCollections,
        //    ],
        //    null,
        //    true
        //);

        $controller->context->smarty->assign([
            'displayBackOfficeTop' => $this->hookDispatcher->dispatchRenderingWithParameters('displayBackOfficeTop'),
            'displayBackOfficeEmployeeMenu' => $menuLinksCollections,
            'submit_form_ajax' => (int) Tools::getValue('submitFormAjax'),
        ]);

        // Multishop
        $is_multishop = Shop::isFeatureActive();

        // Quick access
        if ((int) $controller->context->employee->id) {
            $quick_access = QuickAccess::getQuickAccessesWithToken($controller->context->language->id, (int) $controller->context->employee->id);
        }

        $tabs = $this->getTabs($controller);
        $currentTabLevel = 0;
        foreach ($tabs as $tab) {
            $currentTabLevel = isset($tab['current_level']) ? $tab['current_level'] : $currentTabLevel;
        }

        if (Validate::isLoadedObject($controller->context->employee)) {
            $accesses = Profile::getProfileAccesses($controller->context->employee->id_profile, 'class_name');
            $helperShop = new HelperShop();
            /* Hooks are voluntary out the initialize array (need those variables already assigned) */
            $bo_color = empty($controller->context->employee->bo_color) ? '#FFFFFF' : $controller->context->employee->bo_color;
            $controller->context->smarty->assign([
                'help_box' => Configuration::get('PS_HELPBOX'),
                'round_mode' => Configuration::get('PS_PRICE_ROUND_MODE'),
                'brightness' => Tools::getBrightness($bo_color) < 128 ? 'white' : '#383838',
                'bo_width' => (int) $controller->context->employee->bo_width,
                'bo_color' => isset($controller->context->employee->bo_color) ? Tools::htmlentitiesUTF8($controller->context->employee->bo_color) : null,
                'show_new_orders' => Configuration::get('PS_SHOW_NEW_ORDERS') && isset($accesses['AdminOrders']) && $accesses['AdminOrders']['view'],
                'show_new_customers' => Configuration::get('PS_SHOW_NEW_CUSTOMERS') && isset($accesses['AdminCustomers']) && $accesses['AdminCustomers']['view'],
                'show_new_messages' => Configuration::get('PS_SHOW_NEW_MESSAGES') && isset($accesses['AdminCustomerThreads']) && $accesses['AdminCustomerThreads']['view'],
                'employee' => $controller->context->employee,
                'search_type' => Tools::getValue('bo_search_type'),
                'bo_query' => Tools::safeOutput(Tools::stripslashes(Tools::getValue('bo_query'))),
                'quick_access' => empty($quick_access) ? false : $quick_access,
                'multi_shop' => Shop::isFeatureActive(),
                'shop_list' => $helperShop->getRenderedShopList(),
                'current_shop_name' => $helperShop->getCurrentShopName(),
                'shop' => $controller->context->shop,
                'shop_group' => new ShopGroup((int) Shop::getContextShopGroupID()),
                'is_multishop' => $is_multishop,
                'multishop_context' => $controller->multishop_context,
                'default_tab_link' => $controller->context->link->getAdminLink(Tab::getClassNameById((int) Context::getContext()->employee->default_tab)),
                'login_link' => $controller->context->link->getAdminLink('AdminLogin'),
                'logout_link' => $controller->context->link->getAdminLink('AdminLogin', true, [], ['logout' => 1]),
                'collapse_menu' => isset($controller->context->cookie->collapse_menu) ? (int) $controller->context->cookie->collapse_menu : 0,
            ]);
        } else {
            $controller->context->smarty->assign('default_tab_link', $controller->context->link->getAdminLink('AdminDashboard'));
        }

        // Shop::initialize() in config.php may empty $this->context->shop->virtual_uri so using a new shop instance for getBaseUrl()
        $controller->context->shop = new Shop((int) $controller->context->shop->id);

        $controller->context->smarty->assign([
            'img_dir' => _PS_IMG_,
            'iso' => $controller->context->language->iso_code,
            'class_name' => $controller->className,
            'iso_user' => $controller->context->language->iso_code,
            'lang_is_rtl' => $controller->context->language->is_rtl,
            'country_iso_code' => $controller->context->country->iso_code,
            'version' => _PS_VERSION_,
            'lang_iso' => $controller->context->language->iso_code,
            'full_language_code' => $controller->context->language->language_code,
            'full_cldr_language_code' => $controller->context->getCurrentLocale()->getCode(),
            'link' => $controller->context->link,
            'shop_name' => Configuration::get('PS_SHOP_NAME'),
            'base_url' => $controller->context->shop->getBaseURL(true),
            'current_parent_id' => (int) Tab::getCurrentParentId(),
            'tabs' => $tabs,
            'current_tab_level' => $currentTabLevel,
            'install_dir_exists' => file_exists(_PS_ADMIN_DIR_ . '/../install'),
            'pic_dir' => _THEME_PROD_PIC_DIR_,
            'controller_name' => htmlentities(Tools::getValue('controller')),
            'currentIndex' => $controller->currentIndex,
            'bootstrap' => $controller->bootstrap,
            'default_language' => (int) Configuration::get('PS_LANG_DEFAULT'),
        ]);
    }

    private function getNotificationTip(LegacyAdminController $controller, $type)
    {
        $tips = [
            'order' => [
                $this->translator->trans(
                    'Have you checked your [1][2]abandoned carts[/2][/1]?[3]Your next order could be hiding there!',
                    [
                        '[1]' => '<strong>',
                        '[/1]' => '</strong>',
                        '[2]' => '<a href="' . $controller->context->link->getAdminLink('AdminCarts', true, [], ['action' => 'filterOnlyAbandonedCarts']) . '">',
                        '[/2]' => '</a>',
                        '[3]' => '<br>',
                    ],
                    'Admin.Navigation.Notification'
                ),
            ],
            'customer' => [
                $this->translator->trans('Are you active on social media these days?', [], 'Admin.Navigation.Notification'),
            ],
            'customer_message' => [
                $this->translator->trans('Seems like all your customers are happy :)', [], 'Admin.Navigation.Notification'),
            ],
        ];

        if (!isset($tips[$type])) {
            return '';
        }

        return $tips[$type][array_rand($tips[$type])];
    }

    private function getTabs(LegacyAdminController $controller, $parentId = 0, $level = 0)
    {
        $tabs = Tab::getTabs($controller->context->language->id, $parentId);
        $current_id = Tab::getCurrentParentId();

        foreach ($tabs as $index => $tab) {
            if (!Tab::checkTabRights($tab['id_tab'])
                || !$tab['enabled']
                || ($tab['class_name'] == 'AdminStock' && Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT') == 0)
                || $tab['class_name'] == 'AdminCarrierWizard') {
                unset($tabs[$index]);

                continue;
            }

            // tab[class_name] does not contains the "Controller" suffix
            if (($tab['class_name'] . 'Controller' == get_class($controller)) || ($current_id == $tab['id_tab']) || $tab['class_name'] == $controller->getLegacyController()) {
                $tabs[$index]['current'] = true;
                $tabs[$index]['current_level'] = $level;
            } else {
                $tabs[$index]['current'] = false;
            }
            $tabs[$index]['img'] = null;
            $tabs[$index]['href'] = $controller->context->link->getTabLink($tab);
            $tabs[$index]['sub_tabs'] = array_values($this->getTabs($tab['id_tab'], $level + 1));

            $subTabHref = $this->getTabLinkFromSubTabs($tabs[$index]['sub_tabs']);
            if (!empty($subTabHref)) {
                $tabs[$index]['href'] = $subTabHref;
            } elseif (0 == $tabs[$index]['id_parent'] && '' == $tabs[$index]['icon']) {
                unset($tabs[$index]);
            } elseif (empty($tabs[$index]['icon'])) {
                $tabs[$index]['icon'] = 'extension';
            }

            if (array_key_exists($index, $tabs) && array_key_exists('sub_tabs', $tabs[$index])) {
                foreach ($tabs[$index]['sub_tabs'] as $sub_tab) {
                    if ((int) $sub_tab['current'] == true) {
                        $tabs[$index]['current'] = true;
                        $tabs[$index]['current_level'] = $sub_tab['current_level'];
                    }
                }
            }
        }

        return $tabs;
    }

    /**
     * Get the url of the first active sub-tab.
     *
     * @param array[] $subtabs
     *
     * @return string Url, or empty if no active sub-tab
     */
    private function getTabLinkFromSubTabs(array $subtabs)
    {
        foreach ($subtabs as $tab) {
            if ($tab['active'] && $tab['enabled']) {
                return $tab['href'];
            }
        }

        return '';
    }
}
