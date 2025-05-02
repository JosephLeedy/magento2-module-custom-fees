<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Model\ResourceModel\Report\CustomOrderFees;

use JosephLeedy\CustomFees\Model\ResourceModel\Report\CustomOrderFees;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Reports\Model\Item;
use Magento\Reports\Model\ResourceModel\Report\Collection\AbstractCollection;
use Zend_Db_Expr;

class Collection extends AbstractCollection
{
    private Zend_Db_Expr $periodFormat;
    /**
     * @var array<string, string|Zend_Db_Expr>|array{}
     */
    private array $selectedColumns = [];

    public function _construct(): void
    {
        $this->_init(Item::class, CustomOrderFees::class);
    }

    public function addOrderStatusFilter(): self
    {
        return $this;
    }

    protected function _beforeLoad(): self
    {
        $this->getSelect()->from($this->getResource()->getMainTable(), $this->getSelectedColumns());

        if (!$this->isTotals()) {
            $this
                ->getSelect()
                ->group($this->periodFormat)
                ->group('fee_title')
                ->group('paid_order_currency');
        }

        if ($this->isTotals()) {
            $this->getSelect()->group('paid_order_currency');
        }

        return parent::_beforeLoad();
    }

    /**
     * @return array<string, string|Zend_Db_Expr>
     */
    private function getSelectedColumns(): array
    {
        $connection = $this->getConnection();

        if ('month' === $this->_period) {
            $this->periodFormat = $connection->getDateFormatSql('period', '%Y-%m');
        } elseif ('year' === $this->_period) {
            $this->periodFormat = $connection->getDateExtractSql('period', AdapterInterface::INTERVAL_YEAR);
        } else {
            $this->periodFormat = $connection->getDateFormatSql('period', '%Y-%m-%d');
        }

        if (!$this->isTotals()) {
            $this->selectedColumns = [
                'period' => $this->periodFormat,
                'fee_title' => 'fee_title',
                'base_fee_amount' => 'base_fee_amount',
                'paid_fee_amount' => 'paid_fee_amount',
                'paid_order_currency' => 'paid_order_currency',
                'invoiced_fee_amount' => 'invoiced_fee_amount',
            ];
        }

        if ($this->isTotals()) {
            /** @var array<string, string> $aggregatedColumns */
            $aggregatedColumns = $this->getAggregatedColumns();
            $this->selectedColumns = $aggregatedColumns;
            $this->selectedColumns['paid_order_currency'] = 'paid_order_currency';
        }

        return $this->selectedColumns;
    }
}
