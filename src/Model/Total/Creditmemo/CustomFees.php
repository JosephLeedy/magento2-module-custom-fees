<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Model\Total\Creditmemo;

use JosephLeedy\CustomFees\Api\ConfigInterface;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFee\RefundedInterface as RefundedCustomFee;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFee\RefundedInterfaceFactory as RefundedCustomFeeFactory;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFeeInterface;
use JosephLeedy\CustomFees\Service\CustomFeesRetriever;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Creditmemo\Total\AbstractTotal;
use Magento\Tax\Model\Calculation as TaxCalculation;

use function array_key_exists;
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
        private readonly RefundedCustomFeeFactory $refundedCustomFeeFactory,
        private readonly RequestInterface $request,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly ConfigInterface $config,
        private readonly TaxCalculation $taxCalculation,
        array $data = [],
    ) {
        parent::__construct($data);
    }

    public function collect(Creditmemo $creditmemo): self
    {
        parent::collect($creditmemo);

        $orderedCustomFees = $this->customFeesRetriever->retrieveOrderedCustomFees($creditmemo->getOrder());

        if ($orderedCustomFees === []) {
            return $this;
        }

        $refundedCustomFees = array_map(
            fn(CustomOrderFeeInterface $customOrderFee): RefundedCustomFee
                => $this->refundedCustomFeeFactory->create(['data' => $customOrderFee->__toArray()]),
            $orderedCustomFees,
        );
        $refundedCustomFeeCount = $this->processRefundedCustomFees($creditmemo, $refundedCustomFees);
        $baseTotalCustomFees = 0;
        $totalCustomFees = 0;
        $baseTotalCustomFeeTaxAmount = 0;
        $totalCustomFeeTaxAmount = 0;
        $baseCustomFeeDiscountAmount = 0;
        $totalCustomFeeDiscountAmount = 0;

        array_walk(
            $refundedCustomFees,
            static function (RefundedCustomFee $refundedCustomFee) use (
                &$baseTotalCustomFees,
                &$totalCustomFees,
                &$baseTotalCustomFeeTaxAmount,
                &$totalCustomFeeTaxAmount,
                &$baseCustomFeeDiscountAmount,
                &$totalCustomFeeDiscountAmount,
            ): void {
                $baseTotalCustomFees += $refundedCustomFee->getBaseValue();
                $totalCustomFees += $refundedCustomFee->getValue();
                $baseTotalCustomFeeTaxAmount += $refundedCustomFee->getBaseTaxAmount();
                $totalCustomFeeTaxAmount += $refundedCustomFee->getTaxAmount();
                $baseCustomFeeDiscountAmount += $refundedCustomFee->getBaseDiscountAmount();
                $totalCustomFeeDiscountAmount += $refundedCustomFee->getDiscountAmount();
            },
        );

        $baseRefundedCustomFeeAmount = $baseTotalCustomFees;
        $totalRefundedCustomFeeAmount = $totalCustomFees;
        $baseRefundedCustomFeeTaxAmount = $baseTotalCustomFeeTaxAmount;
        $totalRefundedCustomFeeTaxAmount = $totalCustomFeeTaxAmount;
        $baseRefundedCustomFeeDiscountAmount = $baseCustomFeeDiscountAmount;
        $totalRefundedCustomFeeDiscountAmount = $totalCustomFeeDiscountAmount;

        if ($refundedCustomFeeCount === 0) {
            [
                $baseRefundedCustomFeeAmount,
                $totalRefundedCustomFeeAmount,
                $baseRefundedCustomFeeTaxAmount,
                $totalRefundedCustomFeeTaxAmount,
                $baseRefundedCustomFeeDiscountAmount,
                $totalRefundedCustomFeeDiscountAmount,
            ] = $this->calculateRefundedCustomFees($creditmemo, $refundedCustomFees);
        }

        if (!$creditmemo->isLast()) {
            $creditmemo->setBaseTaxAmount($creditmemo->getBaseTaxAmount() + $baseRefundedCustomFeeTaxAmount);
            $creditmemo->setTaxAmount($creditmemo->getTaxAmount() + $totalRefundedCustomFeeTaxAmount);

            $baseRefundedCustomFeeAmount += $baseRefundedCustomFeeTaxAmount;
            $totalRefundedCustomFeeAmount += $totalRefundedCustomFeeTaxAmount;
        }

        /* Existing discount amounts are negative, so we need to subtract the custom fee discount amounts rather than
           add them. */
        $creditmemo->setBaseDiscountAmount($creditmemo->getBaseDiscountAmount() - $baseRefundedCustomFeeDiscountAmount);
        $creditmemo->setDiscountAmount($creditmemo->getDiscountAmount() - $totalRefundedCustomFeeDiscountAmount);
        $creditmemo->setBaseGrandTotal(
            $creditmemo->getBaseGrandTotal() + ($baseRefundedCustomFeeAmount - $baseRefundedCustomFeeDiscountAmount),
        );
        $creditmemo->setGrandTotal(
            $creditmemo->getGrandTotal() + ($totalRefundedCustomFeeAmount - $totalRefundedCustomFeeDiscountAmount),
        );
        $creditmemo->getExtensionAttributes()?->setRefundedCustomFees($refundedCustomFees);

        return $this;
    }

    /**
     * @param array<string, RefundedCustomFee> $refundedCustomFees
     */
    private function processRefundedCustomFees(Creditmemo $creditmemo, array &$refundedCustomFees): int
    {
        $refundedCustomFeeCount = 0;
        /** @var array{custom_fees?: array<string, float>} $creditMemoRequestData */
        $creditMemoRequestData = $this->request->getParam('creditmemo', []);
        /** @var array<string, float> $requestedCustomFeeRefundValues */
        $requestedCustomFeeRefundValues = array_map('\floatval', $creditMemoRequestData['custom_fees'] ?? []);
        $store = $creditmemo->getStore();
        $existingRefundedCustomFees = $this->customFeesRetriever->retrieveRefundedCustomFees($creditmemo->getOrder());
        $refundedCustomFeeValues = [
            'base_value' => [],
            'value' => [],
            'base_value_with_tax' => [],
            'value_with_tax' => [],
            'base_tax_amount' => [],
            'tax_amount' => [],
            'base_discount_amount' => [],
            'discount_amount' => [],
        ];

        foreach ($existingRefundedCustomFees as $fees) {
            foreach ($fees as $feeCode => $fee) {
                $refundedCustomFeeValues['base_value'][$feeCode] = round(
                    (float) ($refundedCustomFeeValues['base_value'][$feeCode] ?? 0) + $fee->getBaseValue(),
                    2,
                );
                $refundedCustomFeeValues['value'][$feeCode] = round(
                    (float) ($refundedCustomFeeValues['value'][$feeCode] ?? 0) + $fee->getValue(),
                    2,
                );
                $refundedCustomFeeValues['base_value_with_tax'][$feeCode] = round(
                    (float) ($refundedCustomFeeValues['base_value_with_tax'][$feeCode] ?? 0)
                    + $fee->getBaseValueWithTax(),
                    2,
                );
                $refundedCustomFeeValues['value_with_tax'][$feeCode] = round(
                    (float) ($refundedCustomFeeValues['value_with_tax'][$feeCode] ?? 0) + $fee->getValueWithTax(),
                    2,
                );
                $refundedCustomFeeValues['base_tax_amount'][$feeCode] = round(
                    (float) ($refundedCustomFeeValues['base_tax_amount'][$feeCode] ?? 0) + $fee->getBaseTaxAmount(),
                    2,
                );
                $refundedCustomFeeValues['tax_amount'][$feeCode] = round(
                    (float) ($refundedCustomFeeValues['tax_amount'][$feeCode] ?? 0) + $fee->getTaxAmount(),
                    2,
                );
                $refundedCustomFeeValues['base_discount_amount'][$feeCode] = round(
                    (float) ($refundedCustomFeeValues['base_discount_amount'][$feeCode] ?? 0)
                    + $fee->getBaseDiscountAmount(),
                    2,
                );
                $refundedCustomFeeValues['discount_amount'][$feeCode] = round(
                    (float) ($refundedCustomFeeValues['discount_amount'][$feeCode] ?? 0) + $fee->getDiscountAmount(),
                    2,
                );
            }
        }

        array_walk(
            $refundedCustomFees,
            function (RefundedCustomFee $refundedCustomFee) use (
                $requestedCustomFeeRefundValues,
                $store,
                $creditmemo,
                &$refundedCustomFeeCount,
                $refundedCustomFeeValues,
            ): void {
                $customFeeCode = $refundedCustomFee->getCode();

                if (array_key_exists($customFeeCode, $requestedCustomFeeRefundValues)) {
                    if (
                        ($refundedCustomFee->getBaseValue() - $refundedCustomFee->getBaseDiscountAmount())
                            === $requestedCustomFeeRefundValues[$customFeeCode]
                    ) {
                        return;
                    }

                    $refundedCustomFee->setBaseValue($requestedCustomFeeRefundValues[$customFeeCode]);
                    $refundedCustomFee->setValue(
                        $this->priceCurrency->convert(
                            $requestedCustomFeeRefundValues[$customFeeCode],
                            $store,
                            $creditmemo->getOrderCurrencyCode(),
                        ),
                    );

                    $baseTaxAmount = $this->taxCalculation->calcTaxAmount(
                        $refundedCustomFee->getBaseValue(),
                        $refundedCustomFee->getTaxRate(),
                    );
                    $taxAmount = $this->taxCalculation->calcTaxAmount(
                        $refundedCustomFee->getValue(),
                        $refundedCustomFee->getTaxRate(),
                    );

                    $refundedCustomFee->setBaseValueWithTax(
                        round($refundedCustomFee->getBaseValue() + $baseTaxAmount, 2),
                    );
                    $refundedCustomFee->setValueWithTax(round($refundedCustomFee->getValue() + $taxAmount, 2));
                    $refundedCustomFee->setBaseTaxAmount(round($baseTaxAmount, 2));
                    $refundedCustomFee->setTaxAmount(round($taxAmount, 2));
                    $refundedCustomFee->setBaseDiscountAmount(0.00);
                    $refundedCustomFee->setDiscountAmount(0.00);
                    $refundedCustomFee->setDiscountRate(0.00);

                    $refundedCustomFeeCount++;

                    return;
                }

                if ($refundedCustomFeeValues['base_value'] === []) {
                    return;
                }

                $refundedCustomFee->setBaseValue(
                    round(
                        $refundedCustomFee->getBaseValue()
                        - (float) ($refundedCustomFeeValues['base_value'][$customFeeCode] ?? 0),
                        2,
                    ),
                );
                $refundedCustomFee->setValue(
                    round(
                        $refundedCustomFee->getValue()
                        - (float) ($refundedCustomFeeValues['value'][$customFeeCode] ?? 0),
                        2,
                    ),
                );
                $refundedCustomFee->setBaseValueWithTax(
                    round(
                        $refundedCustomFee->getBaseValueWithTax()
                        - (float) ($refundedCustomFeeValues['base_value_with_tax'][$customFeeCode] ?? 0),
                        2,
                    ),
                );
                $refundedCustomFee->setValueWithTax(
                    round(
                        $refundedCustomFee->getValueWithTax()
                        - (float) ($refundedCustomFeeValues['value_with_tax'][$customFeeCode] ?? 0),
                        2,
                    ),
                );
                $refundedCustomFee->setBaseTaxAmount(
                    round(
                        $refundedCustomFee->getBaseTaxAmount()
                        - (float) ($refundedCustomFeeValues['base_tax_amount'][$customFeeCode] ?? 0),
                        2,
                    ),
                );
                $refundedCustomFee->setTaxAmount(
                    round(
                        $refundedCustomFee->getTaxAmount()
                        - (float) ($refundedCustomFeeValues['tax_amount'][$customFeeCode] ?? 0),
                        2,
                    ),
                );
                $refundedCustomFee->setBaseDiscountAmount(
                    round(
                        $refundedCustomFee->getBaseDiscountAmount()
                        - (float) ($refundedCustomFeeValues['base_discount_amount'][$customFeeCode] ?? 0),
                        2,
                    ),
                );
                $refundedCustomFee->setDiscountAmount(
                    round(
                        $refundedCustomFee->getDiscountAmount()
                        - (float) ($refundedCustomFeeValues['discount_amount'][$customFeeCode] ?? 0),
                        2,
                    ),
                );

                $refundedCustomFeeCount++;
            },
        );

        return $refundedCustomFeeCount;
    }

    /**
     * @param array<string, RefundedCustomFee> $refundedCustomFees
     * @return float[]
     */
    private function calculateRefundedCustomFees(Creditmemo $creditmemo, array &$refundedCustomFees): array
    {
        $baseDelta = (float) $creditmemo->getBaseSubtotal() / (float) $creditmemo->getOrder()->getBaseSubtotal();
        $delta = (float) $creditmemo->getSubtotal() / (float) $creditmemo->getOrder()->getSubtotal();
        $baseRefundedCustomFeeAmount = 0;
        $totalRefundedCustomFeeAmount = 0;
        $baseRefundedCustomFeeTaxAmount = 0;
        $totalRefundedCustomFeeTaxAmount = 0;
        $baseRefundedCustomFeeDiscountAmount = 0;
        $totalRefundedCustomFeeDiscountAmount = 0;

        array_walk(
            $refundedCustomFees,
            function (RefundedCustomFee $refundedCustomFee) use (
                $creditmemo,
                $baseDelta,
                $delta,
                &$baseRefundedCustomFeeAmount,
                &$totalRefundedCustomFeeAmount,
                &$baseRefundedCustomFeeTaxAmount,
                &$totalRefundedCustomFeeTaxAmount,
                &$baseRefundedCustomFeeDiscountAmount,
                &$totalRefundedCustomFeeDiscountAmount,
            ): void {
                [
                    $baseValue,
                    $value,
                    $baseValueWithTax,
                    $valueWithTax,
                    $baseTaxAmount,
                    $taxAmount,
                ] = $this->calculateTotalAmounts($creditmemo->getStoreId(), $refundedCustomFee, $baseDelta, $delta);

                $refundedCustomFee->setBaseValue(round($baseValue, 2));
                $refundedCustomFee->setValue(round($value, 2));
                $refundedCustomFee->setBaseValueWithTax(round($baseValueWithTax, 2));
                $refundedCustomFee->setValueWithTax(round($valueWithTax, 2));
                $refundedCustomFee->setBaseTaxAmount(round($baseTaxAmount, 2));
                $refundedCustomFee->setTaxAmount(round($taxAmount, 2));
                $refundedCustomFee->setBaseDiscountAmount(
                    round($refundedCustomFee->getBaseDiscountAmount() * $baseDelta, 2),
                );
                $refundedCustomFee->setDiscountAmount(round($refundedCustomFee->getDiscountAmount() * $delta, 2));

                $baseRefundedCustomFeeAmount += $refundedCustomFee->getBaseValue();
                $totalRefundedCustomFeeAmount += $refundedCustomFee->getValue();
                $baseRefundedCustomFeeTaxAmount += $refundedCustomFee->getBaseTaxAmount();
                $totalRefundedCustomFeeTaxAmount += $refundedCustomFee->getTaxAmount();
                $baseRefundedCustomFeeDiscountAmount += $refundedCustomFee->getBaseDiscountAmount();
                $totalRefundedCustomFeeDiscountAmount += $refundedCustomFee->getDiscountAmount();
            },
        );

        return [
            $baseRefundedCustomFeeAmount,
            $totalRefundedCustomFeeAmount,
            $baseRefundedCustomFeeTaxAmount,
            $totalRefundedCustomFeeTaxAmount,
            $baseRefundedCustomFeeDiscountAmount,
            $totalRefundedCustomFeeDiscountAmount,
        ];
    }

    /**
     * @return float[]
     */
    private function calculateTotalAmounts(
        int|string|null $storeId,
        RefundedCustomFee $refundedCustomFee,
        float $baseSubtotalDelta,
        float $subtotalDelta,
    ): array {
        if ($this->config->isTaxIncluded($storeId)) {
            $baseValueWithTax = $refundedCustomFee->getBaseValueWithTax() * $baseSubtotalDelta;
            $valueWithTax = $refundedCustomFee->getValueWithTax() * $subtotalDelta;
            $baseTaxAmount = $this->taxCalculation->calcTaxAmount(
                $baseValueWithTax,
                $refundedCustomFee->getTaxRate(),
                true,
                false,
            );
            $taxAmount = $this->taxCalculation->calcTaxAmount(
                $valueWithTax,
                $refundedCustomFee->getTaxRate(),
                true,
                false,
            );
            $baseValue = $baseValueWithTax - $baseTaxAmount;
            $value = $valueWithTax - $taxAmount;
        } else {
            $baseValue = $refundedCustomFee->getBaseValue() * $baseSubtotalDelta;
            $value = $refundedCustomFee->getValue() * $subtotalDelta;
            $baseTaxAmount = $this->taxCalculation->calcTaxAmount(
                $baseValue,
                $refundedCustomFee->getTaxRate(),
                round: false,
            );
            $taxAmount = $this->taxCalculation->calcTaxAmount(
                $value,
                $refundedCustomFee->getTaxRate(),
                round: false,
            );
            $baseValueWithTax = $baseValue + $baseTaxAmount;
            $valueWithTax = $value + $taxAmount;
        }

        return [$baseValue, $value, $baseValueWithTax, $valueWithTax, $baseTaxAmount, $taxAmount];
    }
}
