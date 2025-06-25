<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Setup\Patch\Data;

use JosephLeedy\CustomFees\Api\CustomOrderFeesRepositoryInterface;
use JosephLeedy\CustomFees\Model\CustomOrderFees;
use JosephLeedy\CustomFees\Setup\Patch\Data\AddFieldsToCustomOrderFeesPatch;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

use function array_map;
use function array_values;

final class AddFieldsToCustomOrderFeesPatchTest extends TestCase
{
    #[DataFixture('JosephLeedy_CustomFees::../test/Integration/_files/order_with_custom_fees_missing_fields.php')]
    public function testAddFieldsToCustomOrderFeesPatch(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var AddFieldsToCustomOrderFeesPatch $addFieldsToCustomOrderFeesPatch */
        $addFieldsToCustomOrderFeesPatch = $objectManager->create(AddFieldsToCustomOrderFeesPatch::class);

        $addFieldsToCustomOrderFeesPatch->apply();

        /** @var SearchCriteriaBuilder $searchCriteriaBuilder */
        $searchCriteriaBuilder = $objectManager->create(SearchCriteriaBuilder::class);
        $searchCriteria = $searchCriteriaBuilder->create();
        /** @var CustomOrderFeesRepositoryInterface $customOrderFeesRepository */
        $customOrderFeesRepository = $objectManager->create(CustomOrderFeesRepositoryInterface::class);
        $searchResults = $customOrderFeesRepository->getList($searchCriteria);
        /** @var CustomOrderFees[] $allCustomOrderFees */
        $allCustomOrderFees = $searchResults->getItems();
        $expectedCustomOrderFees = [
            [
                '_1750885400610_610' => [
                    'code' => 'test_fee_0',
                    'title' => 'Test Fee',
                    'type' => 'fixed',
                    'percent' => null,
                    'show_percentage' => false,
                    'base_value' => 5.00,
                    'value' => 5.00,
                ],
                '_1750886019048_048' => [
                    'code' => 'test_fee_1',
                    'title' => 'Another Test Fee',
                    'type' => 'fixed',
                    'percent' => null,
                    'show_percentage' => false,
                    'base_value' => 1.50,
                    'value' => 1.50,
                ],
            ],
        ];
        $actualCustomOrderFees = array_map(
            static fn(CustomOrderFees $customOrderFees): array => $customOrderFees->getCustomFees(),
            array_values($allCustomOrderFees),
        );

        self::assertEquals($expectedCustomOrderFees, $actualCustomOrderFees);
    }
}
