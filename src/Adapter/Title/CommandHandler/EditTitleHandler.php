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

namespace PrestaShop\PrestaShop\Adapter\Title\CommandHandler;

use Gender;
use PrestaShop\PrestaShop\Core\Domain\Title\Command\EditTitleCommand;
use PrestaShop\PrestaShop\Core\Domain\Title\CommandHandler\EditTitleHandlerInterface;
use PrestaShop\PrestaShop\Core\Domain\Title\Exception\CannotUpdateTitleException;
use PrestaShop\PrestaShop\Core\Domain\Title\Exception\TitleException;
use PrestaShopException;

/**
 * Class EditTitleHandler is used for editing title data.
 *
 * @internal
 */
final class EditTitleHandler extends AbstractTitleHandler implements EditTitleHandlerInterface
{
    public function handle(EditTitleCommand $command): void
    {
        $title = new Gender($command->getTitleId()->getValue());
        $this->assertTitleWasFound($command->getTitleId(), $title);
        $this->assertLocalisedTitleContainsDefaultLanguage($command->getLocalizedNames());

        if ($command->getImage() !== null) {
            $this->imageValidator->assertFileUploadLimits($command->getImage());
            $this->imageValidator->assertIsValidImageType($command->getImage());
        }

        try {
            $title->name = $command->getLocalizedNames();
            $title->type = $command->getGender();

            if (false === $title->update()) {
                throw new CannotUpdateTitleException('Unable to update title');
            }

            if ($command->getImage() !== null) {
                $title->deleteImage();

                $this->uploadImage(
                    (int) $title->id,
                    $command->getImage(),
                    'genders' . DIRECTORY_SEPARATOR,
                    $command->getWidth(),
                    $command->getHeight()
                );
            }
        } catch (PrestaShopException $exception) {
            throw new TitleException('An unexpected error occurred when updating contact', 0, $exception);
        }
    }
}
