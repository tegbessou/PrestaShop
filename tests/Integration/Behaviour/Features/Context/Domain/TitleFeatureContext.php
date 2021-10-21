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

namespace Tests\Integration\Behaviour\Features\Context\Domain;

use Behat\Gherkin\Node\TableNode;
use PHPUnit\Framework\Assert;
use PrestaShop\PrestaShop\Core\Domain\Title\Command\AddTitleCommand;
use PrestaShop\PrestaShop\Core\Domain\Title\Command\EditTitleCommand;
use PrestaShop\PrestaShop\Core\Domain\Title\Exception\TitleException;
use PrestaShop\PrestaShop\Core\Domain\Title\Query\GetTitleForEditing;
use PrestaShop\PrestaShop\Core\Domain\Title\QueryResult\EditableTitle;
use PrestaShop\PrestaShop\Core\Domain\Title\ValueObject\TitleId;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Tests\Integration\Behaviour\Features\Context\SharedStorage;

class TitleFeatureContext extends AbstractDomainFeatureContext
{
    /**
     * @var EditableTitle
     */
    private $titleData;

    /**
     * @When I add new title :reference with following properties:
     *
     * @param string $reference
     * @param TableNode $tableNode
     */
    public function addTitle(string $reference, TableNode $tableNode): void
    {
        $data = $this->localizeByRows($tableNode);
        $uploadedFile = null;
        if (isset($data['image'])) {
            $uploadedFile = new UploadedFile(
                'tests/Resources/dummyFile/' . $data['image'],
                $data['image']
            );
        }
        $addCommand = new AddTitleCommand(
            $data['localised_names'],
            (int) $data['gender'],
            $uploadedFile === null ? null : $uploadedFile->getPathname(),
            isset($data['picture_width']) ? (int) $data['picture_width'] : 16,
            isset($data['picture_height']) ? (int) $data['picture_height'] : 16
        );

        /** @var TitleId $titleId */
        $titleId = $this->getCommandBus()->handle($addCommand);
        $this->getSharedStorage()->set($reference, $titleId->getValue());
    }

    /**
     * @When title :reference should have following properties:
     *
     * @param string $reference
     * @param TableNode $tableNode
     */
    public function titleShouldHaveFollowingProperties(string $reference, TableNode $tableNode)
    {
        $data = $this->localizeByRows($tableNode);

        /** @var int $titleId */
        $titleId = SharedStorage::getStorage()->get($reference);
        $expectedEditableTitle = $this->mapToEditableTitle($titleId, $data);

        /** @var EditableTitle $editableTitle */
        $editableTitle = $this->getQueryBus()->handle(new GetTitleForEditing($titleId));

        Assert::assertEquals($expectedEditableTitle, $editableTitle);
    }

    /**
     * @When I update title :reference with following properties:
     *
     * @param string $reference
     * @param TableNode $tableNode
     */
    public function updateContactWithFollowingProperties(string $reference, TableNode $tableNode)
    {
        $data = $this->localizeByRows($tableNode);
        $uploadedFile = null;
        if (isset($data['image'])) {
            $uploadedFile = new UploadedFile(
                'tests/Resources/dummyFile/' . $data['image'],
                $data['image']
            );
        }

        /** @var int $titleId */
        $titleId = SharedStorage::getStorage()->get($reference);

        $editTitleCommand = new EditTitleCommand(
            $titleId,
            $data['localised_names'],
            (int) $data['gender'],
            $uploadedFile === null ? null : $uploadedFile->getPathname(),
            isset($data['picture_width']) ? (int) $data['picture_width'] : 16,
            isset($data['picture_height']) ? (int) $data['picture_height'] : 16
        );

        $this->getCommandBus()->handle($editTitleCommand);
    }

    /**
     * @When I request reference data for :titleId
     */
    public function getCurrencyReferenceData($titleId)
    {
        try {
            $this->titleData = $this->getCommandBus()->handle(new GetTitleForEditing((int) $titleId));
        } catch (TitleException $exception) {
            $this->setLastException($exception);
        }
    }

    /**
     * @Then I should get title data:
     */
    public function checkTitleData(TableNode $node)
    {
        $apiData = [
            'title_id' => $this->titleData->getTitleId()->getValue(),
            'localised_names' => $this->titleData->getLocalisedNames(),
            'gender' => $this->titleData->getGender(),
        ];
        $expectedData = $this->localizeByRows($node);

        foreach ($expectedData as $key => $expectedValue) {
            if ($expectedValue === 'null') {
                $expectedValue = null;
            }

            if ($expectedValue != $apiData[$key]) {
                throw new RuntimeException(sprintf('Invalid title data field %s: %s expected %s', $key, json_encode($apiData[$key]), json_encode($expectedValue)));
            }
        }
    }

    /**
     * @param int $titleId
     * @param array $data
     *
     * @return EditableTitle
     */
    private function mapToEditableTitle(int $titleId, array $data): EditableTitle
    {
        return new EditableTitle(
            $titleId,
            $data['localised_names'],
            $data['gender']
        );
    }
}
