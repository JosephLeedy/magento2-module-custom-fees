<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Service;

use JosephLeedy\CustomFees\Api\CustomOrderFeesRepositoryInterface;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFee\RefundedInterfaceFactory as RefundedCustomFeeFactory;
use JosephLeedy\CustomFees\Model\CustomOrderFees;
use JosephLeedy\CustomFees\Model\ResourceModel\CustomOrderFees\CollectionFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Zend_Db_Expr;

use function array_filter;
use function array_map;
use function array_values;
use function round;

/**
 * @api
 */
class RefundedCustomFeesRecorder
{
    /**
     * @var CreditmemoInterface[]
     */
    private array $creditMemos = [];

    public function __construct(
        private readonly CollectionFactory $customOrderFeesCollectionFactory,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly CreditmemoRepositoryInterface $creditMemoRepository,
        private readonly RefundedCustomFeeFactory $refundedCustomFeeFactory,
        private readonly CustomOrderFeesRepositoryInterface $customOrderFeesRepository,
    ) {}

    public function recordForExistingCreditMemos(): void
    {
        $customOrderFeesCollection = $this->customOrderFeesCollectionFactory->create();

        $customOrderFeesCollection->addFieldToFilter(
            [
                'custom_fees_refunded',
                new Zend_Db_Expr('JSON_LENGTH(custom_fees_refunded)'),
            ],
            [
                [
                    'null' => true,
                ],
                [
                    'eq' => 0,
                ],
            ],
        );
        $customOrderFeesCollection->load();

        if ($customOrderFeesCollection->count() === 0) {
            return;
        }

        /** @var CustomOrderFees[] $customOrderFeesItems */
        $customOrderFeesItems = $customOrderFeesCollection->getItems();
        $orderIds = array_map(
            static fn(CustomOrderFees $customOrderFees): int => (int) $customOrderFees->getOrderId(),
            $customOrderFeesItems,
        );
        $creditMemoSearchCriteria = $this->searchCriteriaBuilder
            ->addFilter('order_id', array_values($orderIds), 'in')
            ->create();
        $creditMemoSearchResults = $this->creditMemoRepository
            ->getList($creditMemoSearchCriteria);

        if ($creditMemoSearchResults->getTotalCount() === 0) {
            return;
        }

        $this->creditMemos = $creditMemoSearchResults->getItems();

        $customOrderFeesCollection->walk($this->recordRefundedCustomFees(...));
    }

    private function recordRefundedCustomFees(CustomOrderFees $customOrderFees): void
    {
        /** @var Creditmemo[] $creditMemos */
        $creditMemos = array_filter(
            $this->creditMemos,
            static fn(CreditmemoInterface $creditMemo): bool
                => (int) $creditMemo->getOrderId() === (int) $customOrderFees->getOrderId(),
        );
        $customFeesOrdered = $customOrderFees->getCustomFeesOrdered();
        $customFeesRefunded = [];

        foreach ($creditMemos as $creditMemo) {
            $baseDelta = (float) $creditMemo->getBaseSubtotal() / (float) $creditMemo->getOrder()->getBaseSubtotal();
            $delta = (float) $creditMemo->getSubtotal() / (float) $creditMemo->getOrder()->getSubtotal();
            $creditMemoId = (int) $creditMemo->getId();

            foreach ($customFeesOrdered as $feeCode => $customFeeOrdered) {
                $customFeesRefunded[$creditMemoId][$feeCode] = $this->refundedCustomFeeFactory->create(
                    [
                        'data' => [
                            'credit_memo_id' => $creditMemoId,
                            'code' => $customFeeOrdered->getCode(),
                            'title' => $customFeeOrdered->getTitle(),
                            'type' => $customFeeOrdered->getType(),
                            'percent' => $customFeeOrdered->getPercent(),
                            'show_percentage' => $customFeeOrdered->getShowPercentage(),
                            'base_value' => round($customFeeOrdered->getBaseValue() * $baseDelta, 2),
                            'value' => round($customFeeOrdered->getValue() * $delta, 2),
                            'base_value_with_tax' => round($customFeeOrdered->getBaseValueWithTax() * $baseDelta, 2),
                            'value_with_tax' => round($customFeeOrdered->getValueWithTax() * $delta, 2),
                            'base_tax_amount' => $customFeeOrdered->getBaseTaxAmount(),
                            'tax_amount' => $customFeeOrdered->getTaxAmount(),
                            'tax_rate' => $customFeeOrdered->getTaxRate(),
                        ],
                    ],
                );
            }
        }

        $customOrderFees->setCustomFeesRefunded($customFeesRefunded);

        $this->customOrderFeesRepository->save($customOrderFees);
    }
}
