<?php

namespace PrestaShopBundle\Service\LegacyController;

use PrestaShopBundle\Controller\Admin\LegacyAdminController;

class InitFooter
{
    /**
     * Assign smarty variables for the footer.
     */
    public function initFooter(LegacyAdminController $controller)
    {
        //RTL Support
        //rtl.js overrides inline styles
        //iso_code.css overrides default fonts for every language (optional)
        if ($controller->context->language->is_rtl) {
            $controller->addJS(_PS_JS_DIR_ . 'rtl.js');
            $controller->addCSS(__PS_BASE_URI__ . $controller->admin_webpath . '/themes/' . $controller->bo_theme . '/css/' . $controller->context->language->iso_code . '.css', 'all', false);
        }

        // We assign js and css files on the last step before display template, because controller can add many js and css files
        $controller->context->smarty->assign('css_files', $controller->css_files);
        $controller->context->smarty->assign('js_files', array_unique($controller->js_files));

        $controller->context->smarty->assign([
            'ps_version' => _PS_VERSION_,
            'iso_is_fr' => strtoupper($controller->context->language->iso_code) == 'FR',
            'modals' => $controller->renderModal(),
        ]);
    }
}
