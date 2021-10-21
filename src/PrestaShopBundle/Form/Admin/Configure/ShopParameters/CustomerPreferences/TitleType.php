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

namespace PrestaShopBundle\Form\Admin\Configure\ShopParameters\CustomerPreferences;

use PrestaShop\PrestaShop\Core\ConstraintValidator\Constraints\DefaultLanguage;
use PrestaShop\PrestaShop\Core\Form\ChoiceProvider\GenderChoiceProvider;
use PrestaShopBundle\Form\Admin\Type\TranslatableType;
use PrestaShopBundle\Form\Admin\Type\TranslatorAwareType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * Class generates "Title" form
 * in "Configure > Shop Parameters > Customer Settings > Titles" page.
 */
class TitleType extends TranslatorAwareType
{
    /**
     * @var GenderChoiceProvider
     */
    private $genderChoiceProvider;

    /**
     * @param TranslatorInterface $translator
     * @param array $locales
     * @param GenderChoiceProvider $genderChoiceProvider
     */
    public function __construct(
        TranslatorInterface $translator,
        array $locales,
        GenderChoiceProvider $genderChoiceProvider
    ) {
        parent::__construct($translator, $locales);
        $this->genderChoiceProvider = $genderChoiceProvider;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TranslatableType::class,
                [
                    'label' => $this->trans('Social title', 'Admin.Shopparameters.Feature'),
                    'type' => TextType::class,
                    'constraints' => [
                        new DefaultLanguage(),
                    ],
                    'help' => $this->trans('Invalid characters:', 'Admin.Shopparameters.Help') . ' 0-9!&lt;&gt;,;?=+()@#"�{}_$%:',
                    'options' => [
                        'constraints' => [
                            new Regex([
                                'pattern' => '/[0-9!&lt;&gt;,;?=+()@#"�{}_$%:]/u',
                                'message' => $this->trans(
                                    '%s is invalid.',
                                    'Admin.Notifications.Error'
                                ),
                                'match' => false,
                            ]),
                            new Length([
                                'max' => 20,
                                'maxMessage' => $this->trans(
                                    'This field cannot be longer than %limit% characters',
                                    'Admin.Notifications.Error',
                                    ['%limit%' => 20]
                                ),
                            ]),
                        ],
                    ],
                ],
            )
            ->add('gender', ChoiceType::class, [
                'label' => $this->trans('Gender', 'Admin.Global'),
                'choices' => [
                    $this->genderChoiceProvider->getChoices(false),
                ],
                'expanded' => true,
            ])
            ->add('image', FileType::class, [
                'attr' => [
                    'class' => 'type-file',
                ],
                'label' => $this->trans('Image', 'Admin.Global'),
                'constraints' => [
                    new Image([
                        'mimeTypesMessage' => $this->trans('This field is invalid.', 'Admin.Notifications.Error'),
                    ]),
                ],
                'help' => 'epedro',
                'required' => false,
            ])
            ->add('picture_width', NumberType::class, [
                'label' => $this->trans('Image width', 'Admin.Shopparameters.Feature'),
                'required' => false,
                'help' => $this->trans('Image width in pixels. Enter "0" to use the original size.', 'Admin.Shopparameters.Help'),
                'scale' => 0,
            ])
            ->add('picture_height', NumberType::class, [
                'label' => $this->trans('Image height', 'Admin.Shopparameters.Feature'),
                'required' => false,
                'help' => $this->trans('Image height in pixels. Enter "0" to use the original size.', 'Admin.Shopparameters.Help'),
                'scale' => 0,
            ])
        ;
    }
}
