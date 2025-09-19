<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Block\Adminhtml\Sales\Order\Creditmemo\Create;

use JosephLeedy\CustomFees\Block\Sales\Order\Totals as OrderTotals;
use Magento\Framework\DataObject;
use Magento\Sales\Api\Data\CreditmemoExtensionInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;

use function __;
use function array_key_exists;
use function array_walk;

class Totals extends OrderTotals
{
    public function initTotals(): self
    {
        parent::initTotals();

        $this->replaceRefundedCustomFeeTotals();

        $customFeeTotal = $this->dataObjectFactory->create(
            [
                'data' => [
                    'code' => 'custom_fees',
                    'block_name' => $this->getNameInLayout(),
                ],
            ],
        );

        $this->getParentBlock()->addTotal($customFeeTotal);

        return $this;
    }

    public function formatValue(float $value): string
    {
        /** @var Order $order */
        $order = $this->getSource()->getOrder();

        return $order
            ->getOrderCurrency()
            ->formatPrecision(
                $value,
                2,
                [
                    'display' => 1,
                ],
                false,
            );
    }

    private function replaceRefundedCustomFeeTotals(): void
    {
        /** @var Creditmemo $creditmemo */
        $creditmemo = $this->getSource();
        /** @var CreditmemoExtensionInterface $creditmemoExtensionAttributes */
        $creditmemoExtensionAttributes = $creditmemo->getExtensionAttributes();
        $refundedCustomFees = $creditmemoExtensionAttributes->getRefundedCustomFees() ?? [];

        array_walk(
            $this->customFeeTotals,
            function (DataObject $customFeeTotal) use ($refundedCustomFees) {
                $this->getParentBlock()->removeTotal($customFeeTotal->getCode());

                $customFeeTotal->setLabel(__('Refund %1', $customFeeTotal->getLabel()));

                if (
                    array_key_exists($customFeeTotal->getCode(), $refundedCustomFees)
                    && $refundedCustomFees[$customFeeTotal->getCode()] !== $customFeeTotal->getBaseValue()
                ) {
                    $customFeeTotal->setBaseValue($refundedCustomFees[$customFeeTotal->getCode()]);
                    $customFeeTotal->setValue(
                        $this->formatValue((float) $refundedCustomFees[$customFeeTotal->getCode()]),
                    );
                }
            },
        );
    }
}
