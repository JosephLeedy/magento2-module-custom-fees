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

        $customFees = $this->customFeesRetriever->retrieve($creditmemo->getOrder());

        if (count($customFees) === 0) {
            return $this;
        }

        $refundedCustomFeeCount = $this->processRefundedCustomFees($creditmemo, $customFees);
        $baseTotalCustomFees = array_sum(array_column($customFees, 'base_value'));
        $totalCustomFees = array_sum(array_column($customFees, 'value'));
        $baseRefundedCustomFeeAmount = $baseTotalCustomFees;
        $totalRefundedCustomFeeAmount = $totalCustomFees;

        if ($refundedCustomFeeCount === 0) {
            $baseRefundedCustomFeeAmount = (
                (float) $creditmemo->getBaseSubtotal() / (float) $creditmemo->getOrder()->getBaseSubtotal()
            ) * $baseTotalCustomFees;
            $totalRefundedCustomFeeAmount = (
                (float) $creditmemo->getSubtotal() / (float) $creditmemo->getOrder()->getSubtotal()
            ) * $totalCustomFees;
        }

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
        /** @var array<string, float>|null $refundedCustomFees */
        $refundedCustomFees = $creditmemoExtensionAttributes?->getRefundedCustomFees();

        if (empty($refundedCustomFees)) {
            return $refundedCustomFeeCount;
        }

        $store = $creditmemo->getStore();

        array_walk(
            $customFees,
            function (array &$customFee) use (
                $refundedCustomFees,
                $store,
                $creditmemo,
                &$refundedCustomFeeCount,
            ): void {
                if (!array_key_exists($customFee['code'], $refundedCustomFees)) {
                    return;
                }

                $customFee['base_value'] = $refundedCustomFees[$customFee['code']];
                $customFee['value'] = $this->priceCurrency->convert(
                    $refundedCustomFees[$customFee['code']],
                    $store,
                    $creditmemo->getOrderCurrencyCode(),
                );

                $refundedCustomFeeCount++;
            },
        );

        return $refundedCustomFeeCount;
    }
}
