<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Block\Adminhtml\CustomOrderFees\Report;

use JosephLeedy\CustomFees\Block\Adminhtml\CustomOrderFees\Report\Grid\Column\Renderer\Currency\LocalizedFeeAmount;
use JosephLeedy\CustomFees\Model\ResourceModel\Report\CustomOrderFees\Collection as CustomOrderFeesCollection;
use Magento\Framework\DataObject;
use Magento\Reports\Block\Adminhtml\Grid\AbstractGrid;
use Magento\Reports\Block\Adminhtml\Grid\Column\Renderer\Currency;
use Magento\Reports\Block\Adminhtml\Sales\Grid\Column\Renderer\Date;
use Magento\Reports\Model\ResourceModel\Report\Collection\AbstractCollection;

use function __;
use function array_sum;
use function implode;

class Grid extends AbstractGrid
{
    protected $_resourceCollectionName = CustomOrderFeesCollection::class;
    protected $_columnGroupBy = 'period';

    public function setTotals(DataObject $totals): void
    {
        $totalsCollection = $this->getTotalsCollection()->load();

        if ($totalsCollection->getSize() < 2) {
            parent::setTotals($totals);

            return;
        }

        $baseFeeAmount = [];
        $paidFeeAmount = [];
        $paidOrderCurrency = [];
        $invoicedFeeAmount = [];

        foreach ($totalsCollection->getItems() as $item) {
            $baseFeeAmount[] = $item->getData('base_fee_amount');
            $paidFeeAmount[] = $item->getData('paid_fee_amount');
            $paidOrderCurrency[] = $item->getData('paid_order_currency');
            $invoicedFeeAmount[] = $item->getData('invoiced_fee_amount');
        }

        $totals->setData('base_fee_amount', (string) array_sum($baseFeeAmount));
        $totals->setData('paid_fee_amount', implode(', ', $paidFeeAmount));
        $totals->setData('paid_order_currency', implode(', ', $paidOrderCurrency));
        $totals->setData('invoiced_fee_amount', implode(', ', $invoicedFeeAmount));

        parent::setTotals($totals);
    }

    protected function _construct(): void
    {
        parent::_construct();

        $this->setCountTotals(true);
    }

    protected function _prepareColumns(): self
    {
        $this->setStoreIds($this->_getStoreIds());
        $currencyCode = $this->getCurrentCurrencyCode();
        $rate = $this->getRate($currencyCode);

        $this->addColumn(
            'period',
            [
                'header' => __('Interval'),
                'index' => 'period',
                'sortable' => false,
                'period_type' => $this->getPeriodType(),
                'renderer' => Date::class,
                'totals_label' => __('Total'),
                'html_decorators' => ['nobr'],
                'header_css_class' => 'col-period',
                'column_css_class' => 'col-period',
            ],
        );
        $this->addColumn(
            'fee_title',
            [
                'header' => __('Title'),
                'index' => 'fee_title',
                'type' => 'text',
                'sortable' => false,
                'header_css_class' => 'col-fee-title',
                'column_css_class' => 'col-fee-title',
            ],
        );
        $this->addColumn(
            'base_fee_amount',
            [
                'header' => __('Amount'),
                'type' => 'currency',
                'currency_code' => $currencyCode,
                'index' => 'base_fee_amount',
                'total' => 'sum',
                'sortable' => false,
                'renderer' => Currency::class,
                'rate' => $rate,
                'header_css_class' => 'col-base-fee-amount',
                'column_css_class' => 'col-base-fee-amount',
                'visibility_filter' => [
                    'show_base_amount',
                ],
            ],
        );
        $this->addColumn(
            'paid_fee_amount',
            [
                'header' => __('Amount Paid'),
                'type' => 'currency',
                'index' => 'paid_fee_amount',
                'total' => 'sum',
                'sortable' => false,
                'renderer' => LocalizedFeeAmount::class,
                'rate' => $rate,
                'header_css_class' => 'col-paid-fee-amount',
                'column_css_class' => 'col-paid-fee-amount',
            ],
        );
        $this->addColumn(
            'invoiced_fee_amount',
            [
                'header' => __('Amount Invoiced'),
                'type' => 'currency',
                'index' => 'invoiced_fee_amount',
                'total' => 'sum',
                'sortable' => false,
                'renderer' => LocalizedFeeAmount::class,
                'rate' => $rate,
                'header_css_class' => 'col-invoiced-fee-amount',
                'column_css_class' => 'col-invoiced-fee-amount',
            ],
        );

        $this->addExportType('*/*/export_csv', (string) __('CSV'));
        $this->addExportType('*/*/export_excel', (string) __('Excel XML'));

        return parent::_prepareColumns();
    }

    private function getTotalsCollection(): AbstractCollection
    {
        /** @var DataObject $filterData */
        $filterData = $this->getFilterData();
        /** @var AbstractCollection $totalsCollection */
        $totalsCollection = $this
            ->_resourceFactory
            ->create($this->getResourceCollectionName())
            ->setPeriod($filterData->getData('period_type'))
            ->setDateRange($filterData->getData('from', null), $filterData->getData('to', null))
            ->addStoreFilter($this->_getStoreIds())
            ->setAggregatedColumns($this->_getAggregatedColumns())
            ->isTotals(true);

        return $totalsCollection;
    }
}
