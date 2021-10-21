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

use Context;
use Gender;
use ImageManager;
use PrestaShop\PrestaShop\Adapter\Image\ImageValidator;
use PrestaShop\PrestaShop\Core\ConstraintValidator\Constraints\DefaultLanguage;
use PrestaShop\PrestaShop\Core\Domain\Title\Exception\TitleConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Title\Exception\TitleImageUploadingException;
use PrestaShop\PrestaShop\Core\Domain\Title\Exception\TitleNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Title\ValueObject\TitleId;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Encapsulates common legacy behavior for adding/editing title
 */
abstract class AbstractTitleHandler
{
    /**
     * @var ValidatorInterface
     */
    protected $validator;

    /**
     * @var ImageValidator
     */
    protected $imageValidator;

    /**
     * @param ValidatorInterface $validator
     * @param ImageValidator $imageValidator
     */
    public function __construct(ValidatorInterface $validator, ImageValidator $imageValidator)
    {
        $this->validator = $validator;
        $this->imageValidator = $imageValidator;
    }

    /**
     * @param TitleId $titleId
     * @param Gender|null $title
     */
    protected function assertTitleWasFound(TitleId $titleId, ?Gender $title): void
    {
        if (empty($title)) {
            throw new TitleNotFoundException(sprintf('Title with id "%d" was not found.', $titleId->getValue()));
        }
    }

    /**
     * Checks if the localised names array contains value for the default language.
     *
     * @param array $localisedTitle
     */
    protected function assertLocalisedTitleContainsDefaultLanguage(array $localisedTitle): void
    {
        $errors = $this->validator->validate($localisedTitle, new DefaultLanguage());

        if (0 !== count($errors)) {
            throw new TitleConstraintException('Title field is not found for default language', TitleConstraintException::MISSING_TITLE_FOR_DEFAULT_LANGUAGE);
        }
    }

    /**
     * @param int $titleId
     * @param string $newPath
     * @param string $imageDir
     * @param int $width
     * @param int $height
     */
    protected function uploadImage(int $titleId, string $newPath, string $imageDir, int $width, int $height): void
    {
        $temporaryImage = tempnam(_PS_TMP_IMG_DIR_, 'PS');
        if (!$temporaryImage) {
            return;
        }

        if (!copy($newPath, $temporaryImage)) {
            return;
        }

        // Evaluate the memory required to resize the image: if it's too much, you can't resize it.
        if (!ImageManager::checkImageMemoryLimit($temporaryImage)) {
            throw new TitleImageUploadingException('Due to memory limit restrictions, this image cannot be loaded. Increase your memory_limit value.', TitleImageUploadingException::MEMORY_LIMIT_RESTRICTION);
        }

        // Copy new image
        if (!ImageManager::resize($temporaryImage, _PS_IMG_DIR_ . $imageDir . $titleId . '.jpg', $width, $height)) {
            throw new TitleImageUploadingException('An error occurred while uploading the image. Check your directory permissions.', TitleImageUploadingException::UNEXPECTED_ERROR);
        }

        if (file_exists(_PS_GENDERS_DIR_ . $titleId . '.jpg')) {
            $shopId = Context::getContext()->shop->id;
            $currentFile = _PS_TMP_IMG_DIR_ . 'gender_mini_' . $titleId . '_' . $shopId . '.jpg';

            if (file_exists($currentFile)) {
                unlink($currentFile);
            }
        }

        unlink($newPath);
        unlink($temporaryImage);
    }
}
