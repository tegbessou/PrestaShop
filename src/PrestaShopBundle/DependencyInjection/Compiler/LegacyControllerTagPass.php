<?php

namespace PrestaShopBundle\DependencyInjection\Compiler;

use PrestaShopBundle\Controller\Admin\LegacyAdminController;
use PrestaShopBundle\Controller\Admin\LegacyAdminControllerInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tagged all legacy controller
 */
class LegacyControllerTagPass implements CompilerPassInterface
{
    public const LEGACY_CONTROLLER_TAG = 'prestashop.core.legacy_controller';

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        foreach ($container->getDefinitions() as $definition) {
            $reflectClass = $container->getReflectionClass($definition->getClass());

            if ($reflectClass === null){
                continue;
            }

            if (!$reflectClass->implementsInterface(LegacyAdminControllerInterface::class)) {
                continue;
            }

            if ($reflectClass->getName() === LegacyAdminController::class) {
                continue;
            }

            $definition->addTag(self::LEGACY_CONTROLLER_TAG);
        }
    }
}
