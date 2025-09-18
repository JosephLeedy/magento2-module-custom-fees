<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Service;

use JosephLeedy\CustomFees\Model\CustomOrderFees;
use JosephLeedy\CustomFees\Model\FeeType;
use JosephLeedy\CustomFees\Model\ResourceModel\CustomOrderFees\Collection as CustomOrderFeesCollection;
use JosephLeedy\CustomFees\Service\RefundedCustomFeesRecorder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Zend_Db_Expr;

final class RefundedCustomFeesRecorderTest extends TestCase
{
    #[DataFixture('JosephLeedy_CustomFees::../test/Integration/_files/multiple_creditmemos_with_custom_fees.php')]
    public function testRecordsRefundedCustomFeesForExistingCreditMemos(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var RefundedCustomFeesRecorder $refundedCustomFeesRecorder */
        $refundedCustomFeesRecorder = $objectManager->create(RefundedCustomFeesRecorder::class);
        /** @var CustomOrderFeesCollection $customOrderFeesCollection */
        $customOrderFeesCollection = $objectManager->create(CustomOrderFeesCollection::class);
        /** @var SearchCriteriaBuilder $searchCriteriaBuilder */
        $searchCriteriaBuilder = $objectManager->create(SearchCriteriaBuilder::class);
        $creditMemoSearchCriteria = $searchCriteriaBuilder->create();
        /** @var CreditmemoRepositoryInterface $creditMemoRepository */
        $creditMemoRepository = $objectManager->create(CreditmemoRepositoryInterface::class);
        $creditMemos = $creditMemoRepository->getList($creditMemoSearchCriteria)->getItems();

        $refundedCustomFeesRecorder->recordForExistingCreditMemos();

        /** @var CustomOrderFees[] $customOrderFeesItems */
        $customOrderFeesItems = $customOrderFeesCollection
            ->addFieldToFilter([new Zend_Db_Expr('JSON_LENGTH(custom_fees_refunded)')], [['gt' => 0]])
            ->load()
            ->getItems();

        $expectedRefundedCustomOrderFees = [];
        $actualRefundedCustomOrderFees = [];

        foreach ($creditMemos as $creditMemo) {
            $creditMemoId = $creditMemo->getEntityId();
            $expectedRefundedCustomOrderFees[$creditMemoId] = [
                'test_fee_0' => [
                    'credit_memo_id' => $creditMemoId,
                    'code' => 'test_fee_0',
                    'title' => 'Test Fee',
                    'type' => FeeType::Fixed->value,
                    'percent' => null,
                    'show_percentage' => false,
                    'base_value' => 5.00,
                    'value' => 5.00,
                ],
                'test_fee_1' => [
                    'credit_memo_id' => $creditMemoId,
                    'code' => 'test_fee_1',
                    'title' => 'Another Test Fee',
                    'type' => FeeType::Fixed->value,
                    'percent' => null,
                    'show_percentage' => false,
                    'base_value' => 1.50,
                    'value' => 1.50,
                ],
            ];
        }

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
        /** @var CustomOrderFeesCollection $customOrderFeesCollection */
        $customOrderFeesCollection = $objectManager->create(CustomOrderFeesCollection::class);

        $refundedCustomFeesRecorder->recordForExistingCreditMemos();

        $customOrderFeesCollection
            ->addFieldToFilter([new Zend_Db_Expr('JSON_LENGTH(custom_fees_refunded)')], [['gt' => 0]])
            ->load();

        // The fixture records six refunded custom order fees, and the service should not record them again.
        self::assertSame(6, $customOrderFeesCollection->count());
    }

    #[DataFixture('JosephLeedy_CustomFees::../test/Integration/_files/order_list_with_invoice_and_custom_fees.php')]
    public function testDoesRecordRefundedCustomFeesIfNoCreditMemosExist(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var RefundedCustomFeesRecorder $refundedCustomFeesRecorder */
        $refundedCustomFeesRecorder = $objectManager->create(RefundedCustomFeesRecorder::class);
        /** @var CustomOrderFeesCollection $customOrderFeesCollection */
        $customOrderFeesCollection = $objectManager->create(CustomOrderFeesCollection::class);

        $refundedCustomFeesRecorder->recordForExistingCreditMemos();

        $customOrderFeesCollection
            ->addFieldToFilter([new Zend_Db_Expr('JSON_LENGTH(custom_fees_refunded)')], [['gt' => 0]])
            ->load();

        self::assertSame(0, $customOrderFeesCollection->count());
    }
}
