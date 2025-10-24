<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Service;

use JosephLeedy\CustomFees\Api\CustomOrderFeesRepositoryInterface;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFee\InvoicedInterfaceFactory as InvoicedCustomFeeFactory;
use JosephLeedy\CustomFees\Model\CustomOrderFees;
use JosephLeedy\CustomFees\Model\ResourceModel\CustomOrderFees\CollectionFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Model\Order\Invoice;
use Zend_Db_Expr;

use function array_filter;
use function array_map;
use function array_values;
use function round;

/**
 * @api
 */
class InvoicedCustomFeesRecorder
{
    /**
     * @var InvoiceInterface[]
     */
    private array $invoices = [];

    public function __construct(
        private readonly CollectionFactory $customOrderFeesCollectionFactory,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly InvoiceRepositoryInterface $invoiceRepository,
        private readonly InvoicedCustomFeeFactory $invoicedCustomFeeFactory,
        private readonly CustomOrderFeesRepositoryInterface $customOrderFeesRepository,
    ) {}

    public function recordForExistingInvoices(): void
    {
        $customOrderFeesCollection = $this->customOrderFeesCollectionFactory->create();

        $customOrderFeesCollection->addFieldToFilter(
            [
                'custom_fees_invoiced',
                new Zend_Db_Expr('JSON_LENGTH(custom_fees_invoiced)'),
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
        $invoiceSearchCriteria = $this->searchCriteriaBuilder
            ->addFilter('order_id', array_values($orderIds), 'in')
            ->create();
        $invoiceSearchResults = $this->invoiceRepository
            ->getList($invoiceSearchCriteria);

        if ($invoiceSearchResults->getTotalCount() === 0) {
            return;
        }

        $this->invoices = $invoiceSearchResults->getItems();

        $customOrderFeesCollection->walk($this->recordInvoicedCustomFees(...));
    }

    private function recordInvoicedCustomFees(CustomOrderFees $customOrderFees): void
    {
        /** @var Invoice[] $invoices */
        $invoices = array_filter(
            $this->invoices,
            static fn(InvoiceInterface $invoice): bool
                => (int) $invoice->getOrderId() === (int) $customOrderFees->getOrderId(),
        );
        $customFeesOrdered = $customOrderFees->getCustomFeesOrdered();
        $customFeesInvoiced = [];

        foreach ($invoices as $invoice) {
            $baseDelta = (float) $invoice->getBaseSubtotal() / (float) $invoice->getOrder()->getBaseSubtotal();
            $delta = (float) $invoice->getSubtotal() / (float) $invoice->getOrder()->getSubtotal();
            $invoiceId = (int) $invoice->getId();

            foreach ($customFeesOrdered as $feeCode => $customFeeOrdered) {
                $customFeesInvoiced[$invoiceId][$feeCode] = $this->invoicedCustomFeeFactory->create(
                    [
                        'data' => [
                            'invoice_id' => $invoiceId,
                            'code' => $customFeeOrdered->getCode(),
                            'title' => $customFeeOrdered->getTitle(),
                            'type' => $customFeeOrdered->getType(),
                            'percent' => $customFeeOrdered->getPercent(),
                            'show_percentage' => $customFeeOrdered->getShowPercentage(),
                            'base_value' => round($customFeeOrdered->getBaseValue() * $baseDelta, 2),
                            'value' => round($customFeeOrdered->getValue() * $delta, 2),
                            'base_tax_amount' => $customFeeOrdered->getBaseTaxAmount(),
                            'tax_amount' => $customFeeOrdered->getTaxAmount(),
                        ],
                    ],
                );
            }
        }

        $customOrderFees->setCustomFeesInvoiced($customFeesInvoiced);

        $this->customOrderFeesRepository->save($customOrderFees);
    }
}
