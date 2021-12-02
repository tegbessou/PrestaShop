<?php

namespace PrestaShopBundle\Service\LegacyController;

use Configuration;
use Media;
use PrestaShopBundle\Controller\Admin\LegacyAdminController;
use Smarty_Internal_Template;
use Tools;

class Rendering
{
    /**
     * @throws Exception
     * @throws SmartyException
     */
    public function display(LegacyAdminController $controller)
    {
        $controller->context->smarty->assign([
            'display_header' => $controller->display_header,
            'display_header_javascript' => $controller->display_header_javascript,
            'display_footer' => $controller->display_footer,
            'js_def' => Media::getJsDef(),
            'toggle_navigation_url' => $controller->context->link->getAdminLink('AdminEmployees', true, [], [
                'action' => 'toggleMenu',
            ]),
        ]);

        // Use page title from meta_title if it has been set else from the breadcrumbs array
        if (!$controller->meta_title) {
            $controller->meta_title = $controller->toolbar_title;
        }
        if (is_array($controller->meta_title)) {
            $controller->meta_title = strip_tags(implode(' ' . Configuration::get('PS_NAVIGATION_PIPE') . ' ', $controller->meta_title));
        }
        $controller->context->smarty->assign('meta_title', $controller->meta_title);

        $template_dirs = $controller->context->smarty->getTemplateDir() ?: [];

        // Check if header/footer have been overridden
        $dir = $controller->context->smarty->getTemplateDir(0) . 'controllers' . DIRECTORY_SEPARATOR . trim($controller->override_folder, '\\/') . DIRECTORY_SEPARATOR;
        $module_list_dir = $controller->context->smarty->getTemplateDir(0) . 'helpers' . DIRECTORY_SEPARATOR . 'modules_list' . DIRECTORY_SEPARATOR;

        $header_tpl = file_exists($dir . 'header.tpl') ? $dir . 'header.tpl' : 'header.tpl';
        $page_header_toolbar = file_exists($dir . 'page_header_toolbar.tpl') ? $dir . 'page_header_toolbar.tpl' : 'page_header_toolbar.tpl';
        $footer_tpl = file_exists($dir . 'footer.tpl') ? $dir . 'footer.tpl' : 'footer.tpl';
        $modal_module_list = file_exists($module_list_dir . 'modal.tpl') ? $module_list_dir . 'modal.tpl' : '';
        //Where it is ?
        $tpl_action = $controller->tpl_folder . $controller->getDisplayFromAction() . '.tpl';

        // Check if action template has been overridden
        foreach ($template_dirs as $template_dir) {
            if (file_exists($template_dir . DIRECTORY_SEPARATOR . $tpl_action) && $controller->getDisplayFromAction() != 'view' && $controller->getDisplayFromAction() != 'options') {
                if (method_exists($this, $controller->getDisplayFromAction() . Tools::toCamelCase($controller->className))) {
                    $this->{$controller->getDisplayFromAction() . Tools::toCamelCase($controller->className)}();
                }
                $controller->context->smarty->assign('content', $controller->context->smarty->fetch($tpl_action));

                break;
            }
        }

        if (!$controller->ajax) {
            $template = $controller->createTemplate($controller->template);
            $page = $template->fetch();
        } else {
            $page = $controller->content;
        }

        if ($conf = Tools::getValue('conf')) {
            $controller->context->smarty->assign('conf', $controller->json ? json_encode($controller->_conf[(int) $conf]) : $controller->_conf[(int) $conf]);
        }

        if ($error = Tools::getValue('error')) {
            $controller->context->smarty->assign('error', $controller->json ? json_encode($controller->_error[(int) $error]) : $controller->_error[(int) $error]);
        }

        foreach (['errors', 'warnings', 'informations', 'confirmations'] as $type) {
            if (!is_array($this->$type)) {
                $this->$type = (array) $this->$type;
            }
            $controller->context->smarty->assign($type, $controller->json ? json_encode(array_unique($controller->$type)) : array_unique($controller->$type));
        }

        if ($controller->show_page_header_toolbar && !$controller->lite_display) {
            $controller->context->smarty->assign(
                [
                    'page_header_toolbar' => $controller->context->smarty->fetch($page_header_toolbar),
                ]
            );
            if (!empty($modal_module_list)) {
                $controller->context->smarty->assign(
                    [
                        'modal_module_list' => $controller->context->smarty->fetch($modal_module_list),
                    ]
                );
            }
        }

        $controller->context->smarty->assign('baseAdminUrl', __PS_BASE_URI__ . basename(_PS_ADMIN_DIR_) . '/');

        $controller->context->smarty->assign(
            [
                'page' => $controller->json ? json_encode($page) : $page,
                'header' => $controller->context->smarty->fetch($header_tpl),
                'footer' => $controller->context->smarty->fetch($footer_tpl),
            ]
        );

        $controller->smartyOutputContent($controller->layout);
    }

    /**
     * Create a template from the override file, else from the base file.
     *
     * @param string $tpl_name filename
     *
     * @return Smarty_Internal_Template
     */
    public function createTemplate(LegacyAdminController $controller, string $tpl_name)
    {
        // Use override tpl if it exists
        // If view access is denied, we want to use the default template that will be used to display an error
        //if ($controller->viewAccess() && $controller->override_folder) {
        if ($controller->override_folder) {
            if (!Configuration::get('PS_DISABLE_OVERRIDES') && file_exists($controller->context->smarty->getTemplateDir(1) . DIRECTORY_SEPARATOR . $controller->override_folder . $tpl_name)) {
                return $controller->context->smarty->createTemplate($controller->override_folder . $tpl_name, $controller->context->smarty);
            } elseif (file_exists($controller->context->smarty->getTemplateDir(0) . 'controllers' . DIRECTORY_SEPARATOR . $controller->override_folder . $tpl_name)) {
                return $controller->context->smarty->createTemplate('controllers' . DIRECTORY_SEPARATOR . $controller->override_folder . $tpl_name, $controller->context->smarty);
            }
        }

        return $controller->context->smarty->createTemplate($controller->context->smarty->getTemplateDir(0) . $tpl_name, $controller->context->smarty);
    }
}
