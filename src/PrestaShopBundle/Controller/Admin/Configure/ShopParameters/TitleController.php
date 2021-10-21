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

declare(strict_types=1);

namespace PrestaShopBundle\Controller\Admin\Configure\ShopParameters;

use Exception;
use PrestaShop\PrestaShop\Core\Domain\Title\Command\BulkDeleteTitleCommand;
use PrestaShop\PrestaShop\Core\Domain\Title\Command\DeleteTitleCommand;
use PrestaShop\PrestaShop\Core\Domain\Title\Exception\TitleConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Title\Exception\TitleException;
use PrestaShop\PrestaShop\Core\Domain\Title\Exception\TitleImageUploadingException;
use PrestaShop\PrestaShop\Core\Domain\Title\Exception\TitleNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Title\ValueObject\TitleId;
use PrestaShop\PrestaShop\Core\Search\Filters\TitleFilters;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Security\Annotation\AdminSecurity;
use PrestaShopBundle\Security\Annotation\DemoRestricted;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller responsible of "Configure > Shop Parameters > Customer Settings > Titles" page.
 */
class TitleController extends FrameworkBundleAdminController
{
    /**
     * Show customer titles page.
     *
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message="Access denied.")
     *
     * @param Request $request
     *
     * @return Response
     */
    public function indexAction(Request $request, TitleFilters $filters): Response
    {
        $titleGridFactory = $this->get('prestashop.core.grid.factory.title');
        $titleGrid = $titleGridFactory->getGrid($filters);

        return $this->render('@PrestaShop/Admin/Configure/ShopParameters/CustomerSettings/Title/index.html.twig', [
            'titleGrid' => $this->presentGrid($titleGrid),
            'layoutTitle' => $this->trans('Titles', 'Admin.Navigation.Menu'),
            'help_link' => $this->generateSidebarLink($request->attributes->get('_legacy_controller')),
        ]);
    }

    /**
     * Displays and handles titles form.
     *
     * @AdminSecurity(
     *     "is_granted('create', request.get('_legacy_controller'))",
     *     redirectRoute="admin_title_index",
     *     message="You need permission to create this."
     * )
     *
     * @param Request $request
     *
     * @return Response
     */
    public function createAction(Request $request): Response
    {
        $titleFormHandler = $this->get('prestashop.core.form.identifiable_object.handler.title_form_handler');
        $titleFormBuilder = $this->get('prestashop.core.form.identifiable_object.builder.title_form_builder');

        $titleForm = $titleFormBuilder->getForm();
        $titleForm->handleRequest($request);

        try {
            $result = $titleFormHandler->handle($titleForm);

            if (null !== $result->getIdentifiableObjectId()) {
                $this->addFlash('success', $this->trans('Successful creation.', 'Admin.Notifications.Success'));

                return $this->redirectToRoute('admin_title_index');
            }
        } catch (Exception $exception) {
            $this->addFlash('error', $this->getErrorMessageForException($exception, $this->getErrorMessages()));
        }

        return $this->render('@PrestaShop/Admin/Configure/ShopParameters/CustomerSettings/Title/create.html.twig', [
            'titleForm' => $titleForm->createView(),
            'help_link' => $this->generateSidebarLink($request->attributes->get('_legacy_controller')),
            'enableSidebar' => true,
        ]);
    }

    /**
     * Displays title form.
     *
     * @AdminSecurity(
     *     "is_granted('update', request.get('_legacy_controller'))",
     *     redirectRoute="admin_title_index",
     *     message="You need permission to edit this."
     * )
     *
     * @param Request $request
     * @param int $titleId
     *
     * @return Response
     */
    public function editAction(Request $request, int $titleId): Response
    {
        $titleFormBuilder = $this->get('prestashop.core.form.identifiable_object.builder.title_form_builder');
        $titleForm = $titleFormBuilder->getFormFor($titleId);

        $titleForm->handleRequest($request);

        try {
            $titleFormHandler = $this->get('prestashop.core.form.identifiable_object.handler.title_form_handler');
            $result = $titleFormHandler->handleFor($titleId, $titleForm);

            if ($result->isSubmitted() && $result->isValid()) {
                $this->addFlash('success', $this->trans('Successful update.', 'Admin.Notifications.Success'));

                return $this->redirectToRoute('admin_title_index');
            }
        } catch (Exception $exception) {
            $this->addFlash(
                'error',
                $this->getErrorMessageForException($exception, $this->getErrorMessages())
            );
        }

        return $this->render('@PrestaShop/Admin/Configure/ShopParameters/CustomerSettings/Title/edit.html.twig', [
            'help_link' => $this->generateSidebarLink($request->attributes->get('_legacy_controller')),
            'titleForm' => $titleForm->createView(),
            'enableSidebar' => true,
        ]);
    }

    /**
     * Deletes title.
     *
     * @AdminSecurity(
     *     "is_granted('delete', request.get('_legacy_controller'))",
     *     redirectRoute="admin_title_index",
     *     message="You need permission to delete this."
     * )
     * @DemoRestricted(redirectRoute="admin_title_index")
     *
     * @param int $titleId
     *
     * @return RedirectResponse
     */
    public function deleteAction(int $titleId): RedirectResponse
    {
        try {
            $this->getCommandBus()->handle(new DeleteTitleCommand($titleId));
        } catch (TitleException $exception) {
            $this->addFlash('error', $this->getErrorMessageForException($exception, $this->getErrorMessages()));

            return $this->redirectToRoute('admin_title_index');
        }

        $this->addFlash('success', $this->trans('Successful deletion.', 'Admin.Notifications.Success'));

        return $this->redirectToRoute('admin_title_index');
    }

    /**
     * Deletes titles in bulk action
     *
     * @AdminSecurity("is_granted('delete', request.get('_legacy_controller'))", redirectRoute="admin_title_index")
     * @DemoRestricted(redirectRoute="admin_title_index")
     *
     * @param Request $request
     *
     * @return RedirectResponse
     */
    public function bulkDeleteAction(Request $request): RedirectResponse
    {
        $titlesIds = $this->getBulkTitlesFromRequest($request);

        try {
            $this->getCommandBus()->handle(new BulkDeleteTitleCommand($titlesIds));

            $this->addFlash(
                'success',
                $this->trans('The selection has been successfully deleted.', 'Admin.Notifications.Success')
            );
        } catch (TitleException $exception) {
            $this->addFlash('error', $this->getErrorMessageForException($exception, $this->getErrorMessages()));
        }

        return $this->redirectToRoute('admin_title_index');
    }

    /**
     * @return array
     */
    private function getErrorMessages(): array
    {
        return [
            TitleNotFoundException::class => $this->trans(
                'The object cannot be loaded (or found)',
                'Admin.Notifications.Error'
            ),
            TitleConstraintException::class => [
                TitleConstraintException::MISSING_TITLE_FOR_DEFAULT_LANGUAGE => $this->trans(
                    'The field %field_name% is required at least in your default language.',
                    'Admin.Notifications.Error',
                    [
                        '%field_name%' => $this->trans('Title', 'Admin.Global'),
                    ]
                ),
            ],
            TitleImageUploadingException::class => [
                TitleImageUploadingException::MEMORY_LIMIT_RESTRICTION => $this->trans(
                    'Due to memory limit restrictions, this image cannot be loaded. Please increase your memory_limit value via your server\'s configuration settings.',
                    'Admin.Notifications.Error'
                ),
                TitleImageUploadingException::UNEXPECTED_ERROR => $this->trans(
                    'An error occurred while uploading the image.',
                    'Admin.Notifications.Error'
                ),
            ],
        ];
    }

    /**
     * Get titles ids from request for bulk action
     *
     * @param Request $request
     *
     * @return int[]
     */
    private function getBulkTitlesFromRequest(Request $request)
    {
        $titlesIds = $request->request->get('title_title_bulk');

        if (!is_array($titlesIds)) {
            return [];
        }

        foreach ($titlesIds as $i => $titleId) {
            $titlesIds[$i] = new TitleId((int) $titleId);
        }

        return $titlesIds;
    }
}
