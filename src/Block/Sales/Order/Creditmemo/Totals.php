<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Block\Sales\Order\Creditmemo;

use JosephLeedy\CustomFees\Block\Sales\Order\Totals as OrderTotals;
use Magento\Framework\Currency\Data\Currency as CurrencyData;
use Magento\Framework\DataObject;
use Magento\Sales\Model\Order;

use function __;
use function array_walk;

class Totals extends OrderTotals
{
    public function initTotals(): self
    {
        parent::initTotals();

        array_walk(
            $this->customFeeTotals,
            function (DataObject $customFeeTotal) {
                $this->getParentBlock()->removeTotal($customFeeTotal->getCode());

                $customFeeTotal->setLabel(__('Refund %1', $customFeeTotal->getLabel()));
            },
        );

        $customFeeTotal = new DataObject(
            [
                'code' => 'custom_fees',
                'block_name' => $this->getNameInLayout(),
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
                    'display' => CurrencyData::NO_SYMBOL,
                ],
                false,
            );
    }
}
