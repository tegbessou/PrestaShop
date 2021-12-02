<?php

namespace PrestaShopBundle\Service\LegacyController;

use ObjectModel;
use PrestaShop\PrestaShop\Core\Foundation\Database\EntityNotFoundException;
use PrestaShopBundle\Controller\Admin\LegacyAdminController;
use PrestaShopBundle\Translation\TranslatorInterface;
use Tools;
use Validate;

class LoadObject
{
    /**
     * @var TranslatorInterface
     */
    private $translator;

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    /**
     * Load class object using identifier in $_GET (if possible)
     * otherwise return an empty object, or die.
     *
     * @param bool $opt Return an empty object if load fail
     *
     * @return ObjectModel|bool
     *
     * @Todo Never be merged
     */
    //@Question do a proxy method
    public function loadObject(LegacyAdminController $controller, bool $opt = false)
    {
        if (!isset($controller->className) || empty($controller->className)) {
            return true;
        }

        $id = (int) Tools::getValue($controller->identifier);
        if ($id && Validate::isUnsignedId($id)) {
            if (!$controller->object) {
                $controller->object = new $controller->className($id);
            }
            if (Validate::isLoadedObject($controller->object)) {
                return $controller->object;
            }

            throw new EntityNotFoundException($this->translator->trans('The object cannot be loaded (or found)', [], 'Admin.Notifications.Error'));
        } elseif ($opt) {
            if (!$controller->object) {
                $controller->object = new $controller->className();
            }

            return $controller->object;
        } else {
            throw new EntityNotFoundException($this->translator->trans('The object cannot be loaded (the identifier is missing or invalid)', [], 'Admin.Notifications.Error'));
        }
    }
}
