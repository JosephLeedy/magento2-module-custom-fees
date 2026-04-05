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
use function max;
use function min;
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
        $previouslyInvoicedCustomFees = $this->customFeesRetriever->retrieveInvoicedCustomFees($invoice->getOrder());
        $baseSubtotalDelta = (float) $invoice->getBaseSubtotal() / (float) $invoice->getOrder()->getBaseSubtotal();
        $subtotalDelta = (float) $invoice->getSubtotal() / (float) $invoice->getOrder()->getSubtotal();
        $baseTotalCustomFees = 0;
        $totalCustomFees = 0;
        $baseTotalCustomFeeDiscount = 0;
        $totalCustomFeeDiscount = 0;
        $baseTotalCustomFeeTax = 0;
        $totalCustomFeeTax = 0;
        $baseTotalCustomFeeDiscountTaxCompensation = 0.00;
        $totalCustomFeeDiscountTaxCompensation = 0.00;

        array_walk(
            $invoicedCustomFees,
            function (InvoicedCustomFee $invoicedCustomFee) use (
                $invoice,
                $orderedCustomFees,
                $previouslyInvoicedCustomFees,
                $baseSubtotalDelta,
                $subtotalDelta,
                &$baseTotalCustomFees,
                &$totalCustomFees,
                &$baseTotalCustomFeeDiscount,
                &$totalCustomFeeDiscount,
                &$baseTotalCustomFeeTax,
                &$totalCustomFeeTax,
                &$baseTotalCustomFeeDiscountTaxCompensation,
                &$totalCustomFeeDiscountTaxCompensation,
            ): void {
                $invoicedCustomFeeCode = $invoicedCustomFee->getCode();
                [
                    $baseAllowedCustomFeeTaxAmount,
                    $allowedCustomFeeTaxAmount,
                    $baseAllowedCustomFeeDiscountTaxCompensationAmount,
                    $allowedCustomFeeDiscountTaxCompensationAmount,
                ] = $this->calculateAllowedAmounts(
                    $invoicedCustomFeeCode,
                    $orderedCustomFees,
                    $previouslyInvoicedCustomFees,
                );
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
                $baseDiscountTaxCompensationAmount = 0.00;
                $discountTaxCompensationAmount = 0.00;
                $baseTaxAmount = min($baseTaxAmount, $baseAllowedCustomFeeTaxAmount);
                $taxAmount = min($taxAmount, $allowedCustomFeeTaxAmount);

                if ($invoicedCustomFee->getDiscountTaxCompensation() !== 0.00) {
                    $baseDiscountTaxCompensationAmount = min(
                        $invoicedCustomFee->getBaseDiscountTaxCompensation() * $baseSubtotalDelta,
                        $baseAllowedCustomFeeDiscountTaxCompensationAmount,
                    );
                    $discountTaxCompensationAmount = min(
                        $invoicedCustomFee->getDiscountTaxCompensation() * $subtotalDelta,
                        $allowedCustomFeeDiscountTaxCompensationAmount,
                    );

                    $invoicedCustomFee->setBaseDiscountTaxCompensation(round($baseDiscountTaxCompensationAmount, 2));
                    $invoicedCustomFee->setDiscountTaxCompensation(round($discountTaxCompensationAmount, 2));
                }

                $invoicedCustomFee->setBaseValue(round($baseValue, 2));
                $invoicedCustomFee->setValue(round($value, 2));
                $invoicedCustomFee->setBaseValueWithTax(round($baseValueWithTax, 2));
                $invoicedCustomFee->setValueWithTax(round($valueWithTax, 2));
                $invoicedCustomFee->setBaseTaxAmount(round($baseTaxAmount - $baseDiscountTaxCompensationAmount, 2));
                $invoicedCustomFee->setTaxAmount(round($taxAmount - $discountTaxCompensationAmount, 2));
                // Applied taxes are only stored for the ordered custom fees
                $invoicedCustomFee->setBaseAppliedTaxes(null);
                $invoicedCustomFee->setAppliedTaxes(null);

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
                $baseTotalCustomFeeTax += $baseTaxAmount;
                $totalCustomFeeTax += $taxAmount;
                $baseTotalCustomFeeDiscountTaxCompensation += $invoicedCustomFee->getBaseDiscountTaxCompensation();
                $totalCustomFeeDiscountTaxCompensation += $invoicedCustomFee->getDiscountTaxCompensation();
            },
        );

        if ($baseSubtotalDelta !== 1.0) {
            $baseTaxAmountToInvoice = $invoice->getBaseTaxAmount() + $baseTotalCustomFeeTax;
            $taxAmountToInvoice = $invoice->getTaxAmount() + $totalCustomFeeTax;

            if ($invoice->isLast()) {
                $orderedCustomFeeAmounts = $this->calculateOrderedFeeAmounts($orderedCustomFees);
                $baseTaxAmountToInvoice
                    -= $orderedCustomFeeAmounts['base_ordered_custom_fee_discount_tax_compensation_amount']
                    - $baseTotalCustomFeeDiscountTaxCompensation;
                $taxAmountToInvoice -= $orderedCustomFeeAmounts['ordered_custom_fee_discount_tax_compensation_amount']
                    - $totalCustomFeeDiscountTaxCompensation;
            }

            $invoice->setBaseTaxAmount(max($baseTaxAmountToInvoice, 0.00));
            $invoice->setTaxAmount(max($taxAmountToInvoice, 0.00));

            $baseTotalCustomFees += $baseTotalCustomFeeTax;
            $totalCustomFees += $totalCustomFeeTax;
        }

        /* Existing discount amounts are negative, so we need to subtract the custom fee discount amounts rather than
           add them. */
        $invoice->setBaseDiscountAmount($invoice->getBaseDiscountAmount() - $baseTotalCustomFeeDiscount);
        $invoice->setDiscountAmount($invoice->getDiscountAmount() - $totalCustomFeeDiscount);
        $invoice->setBaseDiscountTaxCompensationAmount(
            $invoice->getBaseDiscountTaxCompensationAmount() + $baseTotalCustomFeeDiscountTaxCompensation,
        );
        $invoice->setDiscountTaxCompensationAmount(
            $invoice->getDiscountTaxCompensationAmount() + $totalCustomFeeDiscountTaxCompensation,
        );
        $invoice->setBaseGrandTotal(
            $invoice->getBaseGrandTotal()
            + ($baseTotalCustomFees - $baseTotalCustomFeeDiscount)
            + $baseTotalCustomFeeDiscountTaxCompensation,
        );
        $invoice->setGrandTotal(
            $invoice->getGrandTotal()
            + ($totalCustomFees - $totalCustomFeeDiscount)
            + $totalCustomFeeDiscountTaxCompensation,
        );

        /** @var InvoiceExtensionInterface $invoiceExtensionAttributes */
        $invoiceExtensionAttributes = $invoice->getExtensionAttributes();

        $invoiceExtensionAttributes->setInvoicedCustomFees($invoicedCustomFees);

        return $this;
    }

    /**
     * @phpstan-param array<string, CustomOrderFeeInterface> $orderedCustomFees
     * @phpstan-param array<int, array<string, InvoicedCustomFee>> $previouslyInvoicedCustomFeesByInvoice
     * @return float[]
     */
    private function calculateAllowedAmounts(
        string $customFeeCode,
        array $orderedCustomFees,
        array $previouslyInvoicedCustomFeesByInvoice,
    ): array {
        $baseOrderedCustomFeeTaxAmount = $orderedCustomFees[$customFeeCode]->getBaseTaxAmount();
        $orderedCustomFeeTaxAmount = $orderedCustomFees[$customFeeCode]->getTaxAmount();
        $baseOrderedCustomFeeDiscountTaxCompensationAmount = $orderedCustomFees[$customFeeCode]
            ->getBaseDiscountTaxCompensation();
        $orderedCustomFeeDiscountTaxCompensationAmount = $orderedCustomFees[$customFeeCode]
            ->getDiscountTaxCompensation();
        $baseInvoicedCustomFeeTaxAmount = 0.00;
        $invoicedCustomFeeTaxAmount = 0.00;
        $baseInvoicedCustomFeeDiscountTaxCompensationAmount = 0.00;
        $invoicedCustomFeeDiscountTaxCompensationAmount = 0.00;

        foreach ($previouslyInvoicedCustomFeesByInvoice as $previouslyInvoicedCustomFees) {
            foreach ($previouslyInvoicedCustomFees as $invoicedCustomFeeCode => $previouslyInvoicedCustomFee) {
                if ($invoicedCustomFeeCode !== $customFeeCode) {
                    continue;
                }

                $baseInvoicedCustomFeeTaxAmount += $previouslyInvoicedCustomFee->getBaseTaxAmount();
                $invoicedCustomFeeTaxAmount += $previouslyInvoicedCustomFee->getTaxAmount();
                $baseInvoicedCustomFeeDiscountTaxCompensationAmount += $previouslyInvoicedCustomFee
                    ->getBaseDiscountTaxCompensation();
                $invoicedCustomFeeDiscountTaxCompensationAmount += $previouslyInvoicedCustomFee
                    ->getDiscountTaxCompensation();

                break;
            }
        }

        $baseAllowedCustomFeeDiscountTaxCompensationAmount = max(
            $baseOrderedCustomFeeDiscountTaxCompensationAmount - $baseInvoicedCustomFeeDiscountTaxCompensationAmount,
            0.00,
        );
        $allowedCustomFeeDiscountTaxCompensationAmount = max(
            $orderedCustomFeeDiscountTaxCompensationAmount - $invoicedCustomFeeDiscountTaxCompensationAmount,
            0.00,
        );
        $baseAllowedCustomFeeTaxAmount = max(
            ($baseOrderedCustomFeeTaxAmount - $baseInvoicedCustomFeeTaxAmount)
            + $baseAllowedCustomFeeDiscountTaxCompensationAmount,
            0.00,
        );
        $allowedCustomFeeTaxAmount = max(
            ($orderedCustomFeeTaxAmount - $invoicedCustomFeeTaxAmount) + $allowedCustomFeeDiscountTaxCompensationAmount,
            0.00,
        );

        return [
            $baseAllowedCustomFeeTaxAmount,
            $allowedCustomFeeTaxAmount,
            $baseAllowedCustomFeeDiscountTaxCompensationAmount,
            $allowedCustomFeeDiscountTaxCompensationAmount,
        ];
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

    /**
     * @param array<string, CustomOrderFeeInterface> $orderedCustomFees
     * @return array{
     *     base_ordered_custom_fee_discount_tax_compensation_amount: float,
     *     ordered_custom_fee_discount_tax_compensation_amount: float,
     * }
     */
    private function calculateOrderedFeeAmounts(array $orderedCustomFees): array
    {
        $baseOrderedCustomFeeDiscountTaxCompensationAmount = 0.00;
        $orderedCustomFeeDiscountTaxCompensationAmount = 0.00;

        foreach ($orderedCustomFees as $orderedCustomFeeCode => $orderedCustomFee) {
            $baseOrderedCustomFeeDiscountTaxCompensationAmount += $orderedCustomFee
                ->getBaseDiscountTaxCompensation();
            $orderedCustomFeeDiscountTaxCompensationAmount += $orderedCustomFee
                ->getDiscountTaxCompensation();
        }

        return [
            'base_ordered_custom_fee_discount_tax_compensation_amount'
                => $baseOrderedCustomFeeDiscountTaxCompensationAmount,
            'ordered_custom_fee_discount_tax_compensation_amount' => $orderedCustomFeeDiscountTaxCompensationAmount,
        ];
    }
}
