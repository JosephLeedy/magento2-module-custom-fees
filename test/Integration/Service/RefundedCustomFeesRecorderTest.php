<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Service;

use JosephLeedy\CustomFees\Api\CustomOrderFeesRepositoryInterface;
use JosephLeedy\CustomFees\Model\CustomOrderFees;
use JosephLeedy\CustomFees\Model\FeeType;
use JosephLeedy\CustomFees\Service\RefundedCustomFeesRecorder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

final class RefundedCustomFeesRecorderTest extends TestCase
{
    #[DataFixture('JosephLeedy_CustomFees::../test/Integration/_files/multiple_creditmemos_with_custom_fees.php')]
    public function testRecordsRefundedCustomFeesForExistingCreditMemos(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var RefundedCustomFeesRecorder $refundedCustomFeesRecorder */
        $refundedCustomFeesRecorder = $objectManager->create(RefundedCustomFeesRecorder::class);
        /** @var SearchCriteriaBuilder $searchCriteriaBuilder */
        $searchCriteriaBuilder = $objectManager->create(SearchCriteriaBuilder::class);
        $customOrderFeesSearchCriteria = $searchCriteriaBuilder
            ->addFilter('custom_fees_refunded', '[]', 'neq')
            ->create();
        /** @var CustomOrderFeesRepositoryInterface $customOrderFeesRepository */
        $customOrderFeesRepository = $objectManager->create(CustomOrderFeesRepositoryInterface::class);

        $refundedCustomFeesRecorder->recordForExistingCreditMemos();

        /** @var CustomOrderFees[] $customOrderFeesItems */
        $customOrderFeesItems = $customOrderFeesRepository
            ->getList($customOrderFeesSearchCriteria)
            ->getItems();

        $expectedRefundedCustomOrderFees = [
            1 => [
                'test_fee_0' => [
                    'credit_memo_id' => 1,
                    'code' => 'test_fee_0',
                    'title' => 'Test Fee',
                    'type' => FeeType::Fixed->value,
                    'percent' => null,
                    'show_percentage' => false,
                    'base_value' => 5.00,
                    'value' => 5.00,
                ],
                'test_fee_1' => [
                    'credit_memo_id' => 1,
                    'code' => 'test_fee_1',
                    'title' => 'Another Test Fee',
                    'type' => FeeType::Fixed->value,
                    'percent' => null,
                    'show_percentage' => false,
                    'base_value' => 1.50,
                    'value' => 1.50,
                ],
            ],
            2 => [
                'test_fee_0' => [
                    'credit_memo_id' => 2,
                    'code' => 'test_fee_0',
                    'title' => 'Test Fee',
                    'type' => FeeType::Fixed->value,
                    'percent' => null,
                    'show_percentage' => false,
                    'base_value' => 5.00,
                    'value' => 5.00,
                ],
                'test_fee_1' => [
                    'credit_memo_id' => 2,
                    'code' => 'test_fee_1',
                    'title' => 'Another Test Fee',
                    'type' => FeeType::Fixed->value,
                    'percent' => null,
                    'show_percentage' => false,
                    'base_value' => 1.50,
                    'value' => 1.50,
                ],
            ],
            3 => [
                'test_fee_0' => [
                    'credit_memo_id' => 3,
                    'code' => 'test_fee_0',
                    'title' => 'Test Fee',
                    'type' => FeeType::Fixed->value,
                    'percent' => null,
                    'show_percentage' => false,
                    'base_value' => 5.00,
                    'value' => 5.00,
                ],
                'test_fee_1' => [
                    'credit_memo_id' => 3,
                    'code' => 'test_fee_1',
                    'title' => 'Another Test Fee',
                    'type' => FeeType::Fixed->value,
                    'percent' => null,
                    'show_percentage' => false,
                    'base_value' => 1.50,
                    'value' => 1.50,
                ],
            ],
            4 => [
                'test_fee_0' => [
                    'credit_memo_id' => 4,
                    'code' => 'test_fee_0',
                    'title' => 'Test Fee',
                    'type' => FeeType::Fixed->value,
                    'percent' => null,
                    'show_percentage' => false,
                    'base_value' => 5.00,
                    'value' => 5.00,
                ],
                'test_fee_1' => [
                    'credit_memo_id' => 4,
                    'code' => 'test_fee_1',
                    'title' => 'Another Test Fee',
                    'type' => FeeType::Fixed->value,
                    'percent' => null,
                    'show_percentage' => false,
                    'base_value' => 1.50,
                    'value' => 1.50,
                ],
            ],
            5 => [
                'test_fee_0' => [
                    'credit_memo_id' => 5,
                    'code' => 'test_fee_0',
                    'title' => 'Test Fee',
                    'type' => FeeType::Fixed->value,
                    'percent' => null,
                    'show_percentage' => false,
                    'base_value' => 5.00,
                    'value' => 5.00,
                ],
                'test_fee_1' => [
                    'credit_memo_id' => 5,
                    'code' => 'test_fee_1',
                    'title' => 'Another Test Fee',
                    'type' => FeeType::Fixed->value,
                    'percent' => null,
                    'show_percentage' => false,
                    'base_value' => 1.50,
                    'value' => 1.50,
                ],
            ],
            6 => [
                'test_fee_0' => [
                    'credit_memo_id' => 6,
                    'code' => 'test_fee_0',
                    'title' => 'Test Fee',
                    'type' => FeeType::Fixed->value,
                    'percent' => null,
                    'show_percentage' => false,
                    'base_value' => 5.00,
                    'value' => 5.00,
                ],
                'test_fee_1' => [
                    'credit_memo_id' => 6,
                    'code' => 'test_fee_1',
                    'title' => 'Another Test Fee',
                    'type' => FeeType::Fixed->value,
                    'percent' => null,
                    'show_percentage' => false,
                    'base_value' => 1.50,
                    'value' => 1.50,
                ],
            ],
        ];
        $actualRefundedCustomOrderFees = [];

        foreach ($customOrderFeesItems as $customOrderFeesItem) {
            $actualRefundedCustomOrderFees += $customOrderFeesItem->getCustomFeesRefunded();
        }

        self::assertEquals($expectedRefundedCustomOrderFees, $actualRefundedCustomOrderFees);
    }

    #[DataFixture('JosephLeedy_CustomFees::../test/Integration/_files/refunded_custom_order_fees.php')]
    public function testDoesNotRecordRefundedCustomFeesForCreditMemosWithRecordedRefundedCustomFees(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var RefundedCustomFeesRecorder $refundedCustomFeesRecorder */
        $refundedCustomFeesRecorder = $objectManager->create(RefundedCustomFeesRecorder::class);
        /** @var SearchCriteriaBuilder $searchCriteriaBuilder */
        $searchCriteriaBuilder = $objectManager->create(SearchCriteriaBuilder::class);
        $customOrderFeesSearchCriteria = $searchCriteriaBuilder
            ->addFilter('custom_fees_refunded', '[]', 'neq')
            ->create();
        /** @var CustomOrderFeesRepositoryInterface $customOrderFeesRepository */
        $customOrderFeesRepository = $objectManager->create(CustomOrderFeesRepositoryInterface::class);

        $refundedCustomFeesRecorder->recordForExistingCreditMemos();

        $customOrderFeesSearchResults = $customOrderFeesRepository->getList($customOrderFeesSearchCriteria);

        // The fixture records six refunded custom order fees, and the service should not record them again.
        self::assertSame(6, $customOrderFeesSearchResults->getTotalCount());
    }

    #[DataFixture('JosephLeedy_CustomFees::../test/Integration/_files/order_list_with_invoice_and_custom_fees.php')]
    public function testDoesRecordRefundedCustomFeesIfNoCreditMemosExist(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var RefundedCustomFeesRecorder $refundedCustomFeesRecorder */
        $refundedCustomFeesRecorder = $objectManager->create(RefundedCustomFeesRecorder::class);
        /** @var SearchCriteriaBuilder $searchCriteriaBuilder */
        $searchCriteriaBuilder = $objectManager->create(SearchCriteriaBuilder::class);
        $customOrderFeesSearchCriteria = $searchCriteriaBuilder
            ->addFilter('custom_fees_refunded', '[]', 'neq')
            ->create();
        /** @var CustomOrderFeesRepositoryInterface $customOrderFeesRepository */
        $customOrderFeesRepository = $objectManager->create(CustomOrderFeesRepositoryInterface::class);

        $refundedCustomFeesRecorder->recordForExistingCreditMemos();

        $customOrderFeesSearchResults = $customOrderFeesRepository->getList($customOrderFeesSearchCriteria);

        self::assertSame(0, $customOrderFeesSearchResults->getTotalCount());
    }
}
