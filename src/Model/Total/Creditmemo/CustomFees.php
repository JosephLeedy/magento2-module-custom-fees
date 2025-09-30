<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Model\Total\Creditmemo;

use JosephLeedy\CustomFees\Model\FeeType;
use JosephLeedy\CustomFees\Service\CustomFeesRetriever;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Creditmemo\Total\AbstractTotal;

use function array_column;
use function array_key_exists;
use function array_sum;
use function array_walk;
use function round;

class CustomFees extends AbstractTotal
{
    /**
     * @param mixed[] $data
     */
    public function __construct(
        private readonly CustomFeesRetriever $customFeesRetriever,
        private readonly PriceCurrencyInterface $priceCurrency,
        array $data = [],
    ) {
        parent::__construct($data);
    }

    public function collect(Creditmemo $creditmemo): self
    {
        parent::collect($creditmemo);

        $customFees = $this->customFeesRetriever->retrieveOrderedCustomFees($creditmemo->getOrder());

        if (count($customFees) === 0) {
            return $this;
        }

        $refundedCustomFeeCount = $this->processRefundedCustomFees($creditmemo, $customFees);
        $baseTotalCustomFees = array_sum(array_column($customFees, 'base_value'));
        $totalCustomFees = array_sum(array_column($customFees, 'value'));
        $baseRefundedCustomFeeAmount = $baseTotalCustomFees;
        $totalRefundedCustomFeeAmount = $totalCustomFees;

        if ($refundedCustomFeeCount === 0) {
            [
                'baseRefundedCustomFeeAmount' => $baseRefundedCustomFeeAmount,
                'totalRefundedCustomFeeAmount' => $totalRefundedCustomFeeAmount,
            ] = $this->calculateRefundedCustomFees($creditmemo, $customFees);
        }

        $creditmemo->getExtensionAttributes()?->setRefundedCustomFees($customFees);
        $creditmemo->setBaseGrandTotal($creditmemo->getBaseGrandTotal() + $baseRefundedCustomFeeAmount);
        $creditmemo->setGrandTotal($creditmemo->getGrandTotal() + $totalRefundedCustomFeeAmount);

        return $this;
    }

    /**
     * @param array<string, array{
     *     code: string,
     *     title: string,
     *     type: value-of<FeeType>,
     *     percent: float|null,
     *     show_percentage: bool,
     *     base_value: float,
     *     value: float,
     * }> $customFees
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
            foreach ($fees as $fee) {
                $refundedCustomFeeValues['base_value'][$fee['code']] = round(
                    (float) ($refundedCustomFeeValues['base_value'][$fee['code']] ?? 0)
                    + (float) $fee['base_value'],
                    2,
                );
                $refundedCustomFeeValues['value'][$fee['code']] = round(
                    (float) ($refundedCustomFeeValues['value'][$fee['code']] ?? 0) + (float) $fee['value'],
                    2,
                );
            }
        }

        array_walk(
            $customFees,
            function (array &$customFee) use (
                $refundedCustomFees,
                $store,
                $creditmemo,
                &$refundedCustomFeeCount,
                $refundedCustomFeeValues,
            ): void {
                $customFeeCode = $customFee['code'];

                if (array_key_exists($customFeeCode, $refundedCustomFees)) {
                    $customFee['base_value'] = $refundedCustomFees[$customFeeCode];
                    $customFee['value'] = $this->priceCurrency->convert(
                        $refundedCustomFees[$customFeeCode],
                        $store,
                        $creditmemo->getOrderCurrencyCode(),
                    );

                    $refundedCustomFeeCount++;

                    return;
                }

                if ($refundedCustomFeeValues['base_value'] === []) {
                    return;
                }

                $customFee['base_value'] = round(
                    (float) $customFee['base_value']
                    - (float) ($refundedCustomFeeValues['base_value'][$customFeeCode] ?? 0),
                    2,
                );
                $customFee['value'] = round(
                    (float) $customFee['value'] - (float) ($refundedCustomFeeValues['value'][$customFeeCode] ?? 0),
                    2,
                );

                $refundedCustomFeeCount++;
            },
        );

        return $refundedCustomFeeCount;
    }

    /**
     * @param array<string, array{
     *     code: string,
     *     title: string,
     *     type: value-of<FeeType>,
     *     percent: float|null,
     *     show_percentage: bool,
     *     base_value: float,
     *     value: float,
     * }> $customFees
     * @return array{
     *     baseRefundedCustomFeeAmount: float,
     *     totalRefundedCustomFeeAmount: float,
     * }
     */
    private function calculateRefundedCustomFees(Creditmemo $creditmemo, array &$customFees): array
    {
        $baseDelta = (float) $creditmemo->getBaseSubtotal() / (float) $creditmemo->getOrder()->getBaseSubtotal();
        $delta = (float) $creditmemo->getSubtotal() / (float) $creditmemo->getOrder()->getSubtotal();
        $baseRefundedCustomFeeAmount = 0;
        $totalRefundedCustomFeeAmount = 0;

        array_walk(
            $customFees,
            static function (array &$customFee) use (
                $baseDelta,
                $delta,
                &$baseRefundedCustomFeeAmount,
                &$totalRefundedCustomFeeAmount,
            ): void {
                $customFee['base_value'] = round($customFee['base_value'] * $baseDelta, 2);
                $customFee['value'] = round($customFee['value'] * $delta, 2);
                $baseRefundedCustomFeeAmount += $customFee['base_value'];
                $totalRefundedCustomFeeAmount += $customFee['value'];
            },
        );

        return [
            'baseRefundedCustomFeeAmount' => $baseRefundedCustomFeeAmount,
            'totalRefundedCustomFeeAmount' => $totalRefundedCustomFeeAmount,
        ];
    }
}
