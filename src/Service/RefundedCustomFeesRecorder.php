<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Service;

use JosephLeedy\CustomFees\Api\CustomOrderFeesRepositoryInterface;
use JosephLeedy\CustomFees\Model\CustomOrderFees;
use JosephLeedy\CustomFees\Model\ResourceModel\CustomOrderFees\CollectionFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Model\Order\Creditmemo;

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
        private readonly CustomOrderFeesRepositoryInterface $customOrderFeesRepository,
    ) {}

    public function recordForExistingCreditMemos(): void
    {
        $customOrderFeesCollection = $this->customOrderFeesCollectionFactory->create();

        $customOrderFeesCollection->addFieldToFilter(
            [
                'custom_fees_refunded',
                'custom_fees_refunded',
            ],
            [
                [
                    'null' => true,
                ],
                [
                    'eq' => '[]',
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

            foreach ($customFeesOrdered as $customFeeOrdered) {
                $customFeesRefunded[$customFeeOrdered['code']] = [
                    'credit_memo_id' => (int) $creditMemo->getId(),
                    'code' => $customFeeOrdered['code'],
                    'title' => $customFeeOrdered['title'],
                    'type' => $customFeeOrdered['type'],
                    'percent' => $customFeeOrdered['percent'],
                    'show_percentage' => $customFeeOrdered['show_percentage'],
                    'base_value' => round((float) $customFeeOrdered['base_value'] * $baseDelta, 2),
                    'value' => round((float) $customFeeOrdered['value'] * $delta, 2),
                ];
            }
        }

        $customOrderFees->setCustomFeesRefunded($customFeesRefunded);

        $this->customOrderFeesRepository->save($customOrderFees);
    }
}
