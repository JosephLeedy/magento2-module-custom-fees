<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Model\Total\Creditmemo;

use JosephLeedy\CustomFees\Api\Data\CustomOrderFee\RefundedInterface as RefundedCustomFee;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFee\RefundedInterfaceFactory as RefundedCustomFeeFactory;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFeeInterface;
use JosephLeedy\CustomFees\Service\CustomFeesRetriever;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Creditmemo\Total\AbstractTotal;

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
        private readonly PriceCurrencyInterface $priceCurrency,
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

        array_walk(
            $refundedCustomFees,
            static function (RefundedCustomFee $refundedCustomFee) use (
                &$baseTotalCustomFees,
                &$totalCustomFees,
            ): void {
                $baseTotalCustomFees += $refundedCustomFee->getBaseValue();
                $totalCustomFees += $refundedCustomFee->getValue();
            },
        );

        $baseRefundedCustomFeeAmount = $baseTotalCustomFees;
        $totalRefundedCustomFeeAmount = $totalCustomFees;

        if ($refundedCustomFeeCount === 0) {
            [
                'baseRefundedCustomFeeAmount' => $baseRefundedCustomFeeAmount,
                'totalRefundedCustomFeeAmount' => $totalRefundedCustomFeeAmount,
            ] = $this->calculateRefundedCustomFees($creditmemo, $refundedCustomFees);
        }

        $creditmemo->getExtensionAttributes()?->setRefundedCustomFees($refundedCustomFees);
        $creditmemo->setBaseGrandTotal($creditmemo->getBaseGrandTotal() + $baseRefundedCustomFeeAmount);
        $creditmemo->setGrandTotal($creditmemo->getGrandTotal() + $totalRefundedCustomFeeAmount);

        return $this;
    }

    /**
     * @param array<string, RefundedCustomFee> $customFees
     */
    private function processRefundedCustomFees(Creditmemo $creditmemo, array &$customFees): int
    {
        $refundedCustomFeeCount = 0;
        $creditmemoExtensionAttributes = $creditmemo->getExtensionAttributes();
        /** @var array<string, float>|array{} $refundedCustomFees */
        $refundedCustomFees = $creditmemoExtensionAttributes?->getRefundedCustomFees() ?? [];
        $store = $creditmemo->getStore();
        $existingRefundedCustomFees = $this->customFeesRetriever->retrieveRefundedCustomFees($creditmemo->getOrder());
        $refundedCustomFeeValues = [
            'base_value' => [],
            'value' => [],
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
            }
        }

        array_walk(
            $customFees,
            function (RefundedCustomFee $customFee) use (
                $refundedCustomFees,
                $store,
                $creditmemo,
                &$refundedCustomFeeCount,
                $refundedCustomFeeValues,
            ): void {
                $customFeeCode = $customFee->getCode();

                if (array_key_exists($customFeeCode, $refundedCustomFees)) {
                    $customFee->setBaseValue($refundedCustomFees[$customFeeCode]);
                    $customFee->setValue(
                        $this->priceCurrency->convert(
                            $refundedCustomFees[$customFeeCode],
                            $store,
                            $creditmemo->getOrderCurrencyCode(),
                        ),
                    );

                    $refundedCustomFeeCount++;

                    return;
                }

                if ($refundedCustomFeeValues['base_value'] === []) {
                    return;
                }

                $customFee->setBaseValue(
                    round(
                        $customFee->getBaseValue()
                        - (float) ($refundedCustomFeeValues['base_value'][$customFeeCode] ?? 0),
                        2,
                    ),
                );
                $customFee->setValue(
                    round(
                        $customFee->getValue()
                        - (float) ($refundedCustomFeeValues['value'][$customFeeCode] ?? 0),
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
     * @return array{
     *     baseRefundedCustomFeeAmount: float,
     *     totalRefundedCustomFeeAmount: float,
     * }
     */
    private function calculateRefundedCustomFees(Creditmemo $creditmemo, array &$refundedCustomFees): array
    {
        $baseDelta = (float) $creditmemo->getBaseSubtotal() / (float) $creditmemo->getOrder()->getBaseSubtotal();
        $delta = (float) $creditmemo->getSubtotal() / (float) $creditmemo->getOrder()->getSubtotal();
        $baseRefundedCustomFeeAmount = 0;
        $totalRefundedCustomFeeAmount = 0;

        array_walk(
            $refundedCustomFees,
            static function (RefundedCustomFee $refundedCustomFee) use (
                $baseDelta,
                $delta,
                &$baseRefundedCustomFeeAmount,
                &$totalRefundedCustomFeeAmount,
            ): void {
                $refundedCustomFee->setBaseValue(round($refundedCustomFee->getBaseValue() * $baseDelta, 2));
                $refundedCustomFee->setValue(round($refundedCustomFee->getValue() * $delta, 2));

                $baseRefundedCustomFeeAmount += $refundedCustomFee->getBaseValue();
                $totalRefundedCustomFeeAmount += $refundedCustomFee->getValue();
            },
        );

        return [
            'baseRefundedCustomFeeAmount' => $baseRefundedCustomFeeAmount,
            'totalRefundedCustomFeeAmount' => $totalRefundedCustomFeeAmount,
        ];
    }
}
