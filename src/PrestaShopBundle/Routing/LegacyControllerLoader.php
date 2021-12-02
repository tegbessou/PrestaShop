<?php

namespace PrestaShopBundle\Routing;

use PrestaShopBundle\Controller\Admin\LegacyAdminControllerInterface;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class LegacyControllerLoader extends Loader
{
    private const ACTIONS = [
        'indexAction',
        'deleteImageAction',
        'deleteAction',
        'editAction',
        'positionAction',
        'saveAction',
        'newAction',
        'viewAction',
        'detailsAction',
        'exportAction',
        'updateOptionsAction',
        'updateFieldsAction',
        'enableSelectionAction',
        'disableSelectionAction',
        'bulkDeleteAction',
        'bulkEditAction',
        'optionsAction',
    ];

    private $controllers;

    public function __construct(iterable $controllers)
    {
        $this->controllers = $controllers;
    }

    public function load($resource, $type = null)
    {
        $routeCollection = new RouteCollection();

        foreach ($this->controllers as $controller) {
            if (!$this->matchControllerPath($controller::class)) {
                continue;
            }

            $basePath = $this->buildBasePath($controller::class);

            $routes = $this->buildRoutes($basePath, $controller);

            $this->addRoutes($routeCollection, $routes);
        }

        return $routeCollection;
    }

    public function supports($resource, $type = null)
    {
        return 'legacy' === $type;
    }

    private function matchControllerPath(string $namespace): bool
    {
        return preg_match('/^PrestaShopBundle\\Controller\\Admin\\.*\\*.Controller$/', $namespace) !== false;
    }

    private function buildBasePath(string $namespace): string
    {
        $routeFragments = $this->getRouteFragments($namespace);

        foreach ($routeFragments as $key => $fragment) {
            $routeFragments[$key] = strtolower($this->replaceCaps(
                $fragment,
                '-'
            ));
        }

        return '/'.implode('/', $routeFragments);
    }

    private function buildRoutes(string $basePath, LegacyAdminControllerInterface $controller): array
    {
        $routes = [];

        foreach (self::ACTIONS as $action) {
            $route = new Route(
                $basePath.'/'.$this->slugifyActionName($action, '-'),
                [
                    '_controller' => $controller::class.'::'.$action,
                    '_legacy_controller' => $controller->getLegacyController(),
                    //'_legacy_link' => $controller->getLegacyController().':'.strtolower($action),
                    '_legacy_link' => $controller->getLegacyController(),
                ]
            );

            $routes[$this->buildRouteName($controller::class, $action)] = $route;
        }

        return $routes;
    }

    private function buildRouteName(string $namespace, string $action): string
    {
        $routeFragments = $this->getRouteFragments($namespace);

        foreach ($routeFragments as $key => $fragment) {
            $routeFragments[$key] = strtolower($this->replaceCaps(
                $fragment,
                '_'
            ));
        }

        return 'admin_'.implode('_', $routeFragments).'_'.$this->slugifyActionName($action, '_');
    }

    private function slugifyActionName(string $action, string $separator): string
    {
        return strtolower($this->replaceCaps(
            str_replace('Action', '', $action),
            $separator
        ));
    }

    private function addRoutes(RouteCollection $routeCollection, array $routes): void
    {
        foreach ($routes as $name => $route) {
            $routeCollection->add($name, $route);
        }
    }

    private function replaceCaps(string $slug, string $replace): string
    {
        return preg_replace(
            '/(?<=[a-z])([A-Z]+)/',
            $replace.'$1',
            $slug
        );
    }

    private function getRouteFragments(string $namespace): array
    {
        $controllerPath = str_replace(
            [
                'PrestaShopBundle\Controller\Admin\\',
                'Controller',
            ],
            '',
            $namespace
        );

        return explode('\\', $controllerPath);
    }
}
