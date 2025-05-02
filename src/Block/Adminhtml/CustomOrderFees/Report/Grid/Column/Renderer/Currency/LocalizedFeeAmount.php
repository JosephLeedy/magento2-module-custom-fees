<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Block\Adminhtml\CustomOrderFees\Report\Grid\Column\Renderer\Currency;

use Magento\Framework\Currency\Exception\CurrencyException;
use Magento\Framework\DataObject;
use Magento\Reports\Block\Adminhtml\Grid\Column\Renderer\Currency;

use function explode;

class LocalizedFeeAmount extends Currency
{
    public function render(DataObject $row): string
    {
        /** @var string|null $paidOrderCurrency */
        $paidOrderCurrency = $row->getData('paid_order_currency');

        if ($paidOrderCurrency !== null && !str_contains($paidOrderCurrency, ',')) {
            $this->getColumn()->setData('currency_code', $paidOrderCurrency);

            return parent::render($row);
        }

        return $this->renderAsList($row);
    }

    /**
     * @throws CurrencyException
     */
    private function renderAsList(DataObject $row): string
    {
        /** @var string $index */
        $index = $this->getColumn()->getIndex();
        /** @var string $localizedFeeAmount */
        $localizedFeeAmount = $row->getData($index);
        $localizedFeeAmounts = explode(',', $localizedFeeAmount);
        /** @var string $orderCurrency */
        $orderCurrency = $row->getData('paid_order_currency');
        $localizedOrderCurrencies = explode(', ', $orderCurrency);
        $localizedFeeAmountsWithCurrency = array_map(
            fn(string $localizedFeeAmount, int $key): string
                => $this
                    ->_localeCurrency
                    ->getCurrency($localizedOrderCurrencies[$key])
                    ->toCurrency((float) $localizedFeeAmount),
            $localizedFeeAmounts,
            array_keys($localizedFeeAmounts),
        );

        return implode(', ', $localizedFeeAmountsWithCurrency);
    }
}
