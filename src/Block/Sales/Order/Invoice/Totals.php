<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Block\Sales\Order\Invoice;

use JosephLeedy\CustomFees\Api\ConfigInterface;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFee\InvoicedInterface as InvoicedCustomFee;
use JosephLeedy\CustomFees\Model\DisplayType;
use JosephLeedy\CustomFees\Service\CustomFeesRetriever;
use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Block\Order\Invoice\Totals as InvoiceTotals;
use Magento\Sales\Model\Order\Invoice;

use function __;
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
        private readonly ConfigInterface $config,
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

        /** @var string $firstInvoicedFeeKey */
        $firstInvoicedFeeKey = array_key_first($invoicedCustomFees) ?? '';

        array_walk(
            $invoicedCustomFees,
            function (InvoicedCustomFee $invoicedCustomFee) use ($firstInvoicedFeeKey): void {
                $displayType = $this->config->getSalesDisplayType($this->getParentBlock()->getOrder()->getStoreId());
                $totalExcludingTax = null;
                $totalIncludingTax = null;

                if ($displayType === DisplayType::ExcludingTax || $displayType === DisplayType::Both) {
                    $totalExcludingTax = $this->buildTotal($invoicedCustomFee, false);
                }

                if ($displayType === DisplayType::IncludingTax || $displayType === DisplayType::Both) {
                    $totalIncludingTax = $this->buildTotal($invoicedCustomFee, true);
                }

                if ($displayType === DisplayType::Both) {
                    $totalExcludingTax->setLabel(__('%1 Excl. Tax', $totalExcludingTax->getLabel()));

                    $totalIncludingTax->setLabel(__('%1 Incl. Tax', $totalIncludingTax->getLabel()));
                    $totalIncludingTax->setCode($totalIncludingTax->getCode() . '_with_tax');
                }

                if ($totalExcludingTax !== null) {
                    $this->addTotal($totalExcludingTax, $firstInvoicedFeeKey);
                }

                if ($totalIncludingTax !== null) {
                    $this->addTotal($totalIncludingTax, $firstInvoicedFeeKey);
                }
            },
        );

        return $this;
    }

    private function buildTotal(InvoicedCustomFee $invoicedCustomFee, bool $includeTax): DataObject
    {
        $totalData = [
            'code' => $invoicedCustomFee->getCode(),
            'label' => $invoicedCustomFee->formatLabel(),
            'base_value' => $invoicedCustomFee->getBaseValue(),
            'value' => $invoicedCustomFee->getValue(),
        ];

        if ($includeTax) {
            $totalData['base_value'] = $invoicedCustomFee->getBaseValueWithTax();
            $totalData['value'] = $invoicedCustomFee->getValueWithTax();
        }

        return $this->dataObjectFactory->create(['data' => $totalData]);
    }

    private function addTotal(DataObject $total, string $firstTotalKey): void
    {
        if ($total->getCode() === $firstTotalKey) {
            if ($this->getBeforeCondition() !== null) {
                $this->getParentBlock()->addTotalBefore($total, $this->getBeforeCondition());
            } else {
                $this->getParentBlock()->addTotal($total, $this->getAfterCondition());
            }
        } else {
            $this->getParentBlock()->addTotal($total);
        }
    }
}
