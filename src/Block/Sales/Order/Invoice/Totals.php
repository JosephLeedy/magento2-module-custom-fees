<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Block\Sales\Order\Invoice;

use JosephLeedy\CustomFees\Api\Data\CustomOrderFee\InvoicedInterface as InvoicedCustomFee;
use JosephLeedy\CustomFees\Service\CustomFeesRetriever;
use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Block\Order\Invoice\Totals as InvoiceTotals;
use Magento\Sales\Model\Order\Invoice;

use function array_key_exists;
use function array_key_first;
use function array_walk;

/**
 * Initializes and renders Custom Fees invoice total columns
 *
 * @api
 * @method InvoiceTotals getParentBlock()
 * @method string|null getBeforeCondition()
 * @method string|null getAfterCondition()
 */
class Totals extends Template
{
    /**
     * @param array{} $data
     */
    public function __construct(
        Context $context,
        private readonly CustomFeesRetriever $customFeesRetriever,
        private readonly DataObjectFactory $dataObjectFactory,
        array $data = [],
    ) {
        parent::__construct($context, $data);
    }

    public function getSource(): Invoice
    {
        /** @var Invoice $invoice */
        $invoice = $this->getParentBlock()->getSource();

        return $invoice;
    }

    public function initTotals(): self
    {
        $invoice = $this->getSource();
        $order = $invoice->getOrder();
        /** @var array<string, InvoicedCustomFee> $invoicedCustomFees */
        $invoicedCustomFees = $invoice->getExtensionAttributes()?->getInvoicedCustomFees() ?? [];
        /** @var int|string|null $invoiceId */
        $invoiceId = $invoice->getId();

        if ($invoicedCustomFees === [] && $invoiceId !== null) {
            $existingInvoicedCustomFees = $this->customFeesRetriever->retrieveInvoicedCustomFees($order);

            if (array_key_exists($invoiceId, $existingInvoicedCustomFees)) {
                $invoicedCustomFees = $existingInvoicedCustomFees[$invoiceId];
            } else {
                return $this;
            }
        }

        $firstFeeKey = array_key_first($invoicedCustomFees);
        $previousFeeCode = '';

        array_walk(
            $invoicedCustomFees,
            function (InvoicedCustomFee $invoicedCustomFee, string $key) use ($firstFeeKey, &$previousFeeCode): void {
                $customFeeCode = $invoicedCustomFee->getCode();
                /** @var DataObject $total */
                $total = $this->dataObjectFactory->create(
                    [
                        'data' => [
                            'code' => $customFeeCode,
                            'label' => $invoicedCustomFee->formatLabel(),
                            'base_value' => $invoicedCustomFee->getBaseValue(),
                            'value' => $invoicedCustomFee->getValue(),
                        ],
                    ],
                );

                if ($key === $firstFeeKey) {
                    if ($this->getBeforeCondition() !== null) {
                        $this->getParentBlock()->addTotalBefore($total, $this->getBeforeCondition());
                    } else {
                        $this->getParentBlock()->addTotal($total, $this->getAfterCondition());
                    }
                } else {
                    $this->getParentBlock()->addTotal($total, $previousFeeCode);
                }

                $previousFeeCode = $customFeeCode;
            },
        );

        return $this;
    }
}
