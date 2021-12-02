<?php

namespace PrestaShopBundle\Controller\Admin\Sell\Catalog;

use PrestaShopBundle\Controller\Admin\LegacyAdminController;

class AttributeFeaturesController extends LegacyAdminController
{
    public function getTable(): string
    {
        return 'configuration';
    }

    public function getLegacyController(): string
    {
        return 'AdminFeatures';
    }
}
