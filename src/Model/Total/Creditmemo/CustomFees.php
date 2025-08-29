<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Model\Total\Creditmemo;

use JosephLeedy\CustomFees\Service\CustomFeesRetriever;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Creditmemo\Total\AbstractTotal;

use function array_column;
use function array_sum;

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

        $creditmemoExtensionAttributes = $creditmemo->getExtensionAttributes();
        $applyDelta = true;

        if (!empty($creditmemoExtensionAttributes?->getRefundedCustomFees())) {
            $refundedCustomFees = $creditmemoExtensionAttributes?->getRefundedCustomFees() ?? [];
            $store = $creditmemo->getStore();
            $replacedCustomFeeCount = 0;

            array_walk(
                $customFees,
                function (array &$customFee) use (
                    $refundedCustomFees,
                    $store,
                    $creditmemo,
                    &$replacedCustomFeeCount,
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

                    $replacedCustomFeeCount++;
                },
            );

            if ($replacedCustomFeeCount > 0) {
                $applyDelta = false;
            }
        }

        $baseTotalCustomFees = array_sum(array_column($customFees, 'base_value'));
        $totalCustomFees = array_sum(array_column($customFees, 'value'));
        $baseRefundedCustomFeeAmount = $baseTotalCustomFees;
        $totalRefundedCustomFeeAmount = $totalCustomFees;

        if ($applyDelta) {
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
}
