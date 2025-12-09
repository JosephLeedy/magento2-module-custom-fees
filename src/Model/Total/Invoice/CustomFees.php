<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Model\Total\Invoice;

use JosephLeedy\CustomFees\Api\ConfigInterface;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFee\InvoicedInterface as InvoicedCustomFee;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFee\InvoicedInterfaceFactory as InvoicedCustomFeeFactory;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFeeInterface;
use JosephLeedy\CustomFees\Service\CustomFeesRetriever;
use Magento\Sales\Api\Data\InvoiceExtensionInterface;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Invoice\Total\AbstractTotal;
use Magento\Tax\Model\Calculation as TaxCalculation;

use function array_map;
use function array_walk;
use function round;

class CustomFees extends AbstractTotal
{
    /**
     * @param mixed[] $data
     */
    public function __construct(
        private readonly CustomFeesRetriever $customFeesRetriever,
        private readonly InvoicedCustomFeeFactory $invoicedCustomFeeFactory,
        private readonly ConfigInterface $config,
        private readonly TaxCalculation $taxCalculation,
        array $data = [],
    ) {
        parent::__construct($data);
    }

    public function collect(Invoice $invoice): self
    {
        parent::collect($invoice);

        $orderedCustomFees = $this->customFeesRetriever->retrieveOrderedCustomFees($invoice->getOrder());

        if ($orderedCustomFees === []) {
            return $this;
        }

        $invoicedCustomFees = array_map(
            fn(CustomOrderFeeInterface $customOrderFee): InvoicedCustomFee
                => $this->invoicedCustomFeeFactory->create(['data' => $customOrderFee->__toArray()]),
            $orderedCustomFees,
        );
        $baseSubtotalDelta = (float) $invoice->getBaseSubtotal() / (float) $invoice->getOrder()->getBaseSubtotal();
        $subtotalDelta = (float) $invoice->getSubtotal() / (float) $invoice->getOrder()->getSubtotal();
        $baseTotalCustomFees = 0;
        $totalCustomFees = 0;
        $baseTotalCustomFeeDiscount = 0;
        $totalCustomFeeDiscount = 0;
        $baseTotalCustomFeeTax = 0;
        $totalCustomFeeTax = 0;

        array_walk(
            $invoicedCustomFees,
            function (InvoicedCustomFee $invoicedCustomFee) use (
                $invoice,
                $baseSubtotalDelta,
                $subtotalDelta,
                &$baseTotalCustomFees,
                &$totalCustomFees,
                &$baseTotalCustomFeeDiscount,
                &$totalCustomFeeDiscount,
                &$baseTotalCustomFeeTax,
                &$totalCustomFeeTax,
            ): void {
                [
                    $baseValue,
                    $value,
                    $baseValueWithTax,
                    $valueWithTax,
                    $baseTaxAmount,
                    $taxAmount,
                ] = $this->calculateTotalAmounts(
                    $invoice->getStoreId(),
                    $invoicedCustomFee,
                    $baseSubtotalDelta,
                    $subtotalDelta,
                );

                $invoicedCustomFee->setBaseValue(round($baseValue, 2));
                $invoicedCustomFee->setValue(round($value, 2));
                $invoicedCustomFee->setBaseValueWithTax(round($baseValueWithTax, 2));
                $invoicedCustomFee->setValueWithTax(round($valueWithTax, 2));
                $invoicedCustomFee->setBaseTaxAmount(round($baseTaxAmount, 2));
                $invoicedCustomFee->setTaxAmount(round($taxAmount, 2));

                $baseDiscountAmount = 0.00;
                $discountAmount = 0.00;

                if ($invoicedCustomFee->getDiscountAmount() !== 0.00) {
                    $baseDiscountAmount = $invoicedCustomFee->getBaseDiscountAmount() * $baseSubtotalDelta;
                    $discountAmount = $invoicedCustomFee->getDiscountAmount() * $subtotalDelta;

                    $invoicedCustomFee->setBaseDiscountAmount(round($baseDiscountAmount, 2));
                    $invoicedCustomFee->setDiscountAmount(round($discountAmount, 2));
                }

                $baseTotalCustomFees += $invoicedCustomFee->getBaseValue();
                $totalCustomFees += $invoicedCustomFee->getValue();
                $baseTotalCustomFeeDiscount += $baseDiscountAmount;
                $totalCustomFeeDiscount += $discountAmount;
                $baseTotalCustomFeeTax += $invoicedCustomFee->getBaseTaxAmount();
                $totalCustomFeeTax += $invoicedCustomFee->getTaxAmount();
            },
        );

        if ($baseSubtotalDelta !== 1.0) {
            $invoice->setBaseTaxAmount($invoice->getBaseTaxAmount() + $baseTotalCustomFeeTax);
            $invoice->setTaxAmount($invoice->getTaxAmount() + $totalCustomFeeTax);

            $baseTotalCustomFees += $baseTotalCustomFeeTax;
            $totalCustomFees += $totalCustomFeeTax;
        }

        $invoice->setBaseGrandTotal(
            $invoice->getBaseGrandTotal() + ($baseTotalCustomFees - $baseTotalCustomFeeDiscount)
        );
        $invoice->setGrandTotal($invoice->getGrandTotal() + ($totalCustomFees - $totalCustomFeeDiscount));

        /** @var InvoiceExtensionInterface $invoiceExtensionAttributes */
        $invoiceExtensionAttributes = $invoice->getExtensionAttributes();

        $invoiceExtensionAttributes->setInvoicedCustomFees($invoicedCustomFees);

        return $this;
    }

    /**
     * @return float[]
     */
    private function calculateTotalAmounts(
        int|string|null $storeId,
        InvoicedCustomFee $invoicedCustomFee,
        float $baseSubtotalDelta,
        float $subtotalDelta,
    ): array {
        if ($this->config->isTaxIncluded($storeId)) {
            $baseValueWithTax = $invoicedCustomFee->getBaseValueWithTax() * $baseSubtotalDelta;
            $valueWithTax = $invoicedCustomFee->getValueWithTax() * $subtotalDelta;
            $baseTaxAmount = $this->taxCalculation->calcTaxAmount(
                $baseValueWithTax,
                $invoicedCustomFee->getTaxRate(),
                true,
                false,
            );
            $taxAmount = $this->taxCalculation->calcTaxAmount(
                $valueWithTax,
                $invoicedCustomFee->getTaxRate(),
                true,
                false,
            );
            $baseValue = $baseValueWithTax - $baseTaxAmount;
            $value = $valueWithTax - $taxAmount;
        } else {
            $baseValue = $invoicedCustomFee->getBaseValue() * $baseSubtotalDelta;
            $value = $invoicedCustomFee->getValue() * $subtotalDelta;
            $baseTaxAmount = $this->taxCalculation->calcTaxAmount(
                $baseValue,
                $invoicedCustomFee->getTaxRate(),
                round: false,
            );
            $taxAmount = $this->taxCalculation->calcTaxAmount(
                $value,
                $invoicedCustomFee->getTaxRate(),
                round: false,
            );
            $baseValueWithTax = $baseValue + $baseTaxAmount;
            $valueWithTax = $value + $taxAmount;
        }

        return [$baseValue, $value, $baseValueWithTax, $valueWithTax, $baseTaxAmount, $taxAmount];
    }
}
