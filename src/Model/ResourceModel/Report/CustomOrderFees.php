<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Model\ResourceModel\Report;

use DateTimeInterface;
use Exception;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Reports\Model\ResourceModel\Report\AbstractReport;
use Zend_Db_Statement_Exception;

use function preg_match;
use function rtrim;
use function str_contains;
use function strtolower;
use function version_compare;

class CustomOrderFees extends AbstractReport
{
    public const TABLE_NAME = 'report_custom_order_fees_aggregated';

    protected function _construct(): void
    {
        $this->_init(self::TABLE_NAME, 'id');
    }

    /**
     * @throws LocalizedException
     * @throws Zend_Db_Statement_Exception
     * @throws Exception
     */
    public function aggregate(
        string|DateTimeInterface|null $from = null,
        string|DateTimeInterface|null $to = null,
    ): self {
        $databaseServerVersion = $this->getDatabaseServerVersion();

        if (!$this->isDatabaseServerSupported($databaseServerVersion)) {
            throw new LocalizedException(__('Unsupported database server version "%1".', $databaseServerVersion));
        }

        /** @var AdapterInterface $connection */
        $connection = $this->getConnection();

        $connection->beginTransaction();

        try {
            $salesOrderTable = $this->getTable('sales_order');
            $customOrderFeesTable = $this->getTable('custom_order_fees');

            if ($from !== null || $to !== null) {
                $subSelect = $this->_getTableDateRangeSelect(
                    $salesOrderTable,
                    'created_at',
                    'created_at',
                    $from,
                    $to,
                );
            } else {
                $subSelect = null;
            }

            $this->_clearTableByDateRange($this->getMainTable(), $from, $to, $subSelect);

            $periodExpression = $connection->getDatePartSql(
                $this->getStoreTZOffsetQuery(
                    ['so' => $salesOrderTable],
                    'so.created_at',
                    $from,
                    $to,
                ),
            );
            // phpcs:disable Magento2.SQL.RawQuery.FoundRawSql
            $query = <<<SQL
                SELECT
                    $periodExpression AS period,
                    so.store_id AS store_id,
                    fee.title AS fee_title,
                    SUM(fee.base_value) AS base_fee_amount,
                    SUM(fee.`value`) AS paid_fee_amount,
                    so.order_currency_code AS paid_order_currency,
                    CAST(IFNULL(SUM(invoiced_fee.base_value), 0.00) AS DECIMAL (20,4)) AS base_invoiced_fee_amount,
                    CAST(IFNULL(SUM(invoiced_fee.`value`), 0.00) AS DECIMAL (20,4)) AS invoiced_fee_amount,
                    CAST(IFNULL(SUM(refunded_fee.base_value), 0.00) AS DECIMAL (20,4)) AS base_refunded_fee_amount,
                    CAST(IFNULL(SUM(refunded_fee.`value`), 0.00) AS DECIMAL (20,4)) AS refunded_fee_amount
                FROM $customOrderFeesTable AS cof
                CROSS JOIN JSON_TABLE(
                    JSON_UNQUOTE(cof.custom_fees_ordered),
                    '$' COLUMNS (
                        NESTED PATH '$.*' COLUMNS (
                            title VARCHAR(255) PATH '$.title',
                            `value` DECIMAL(20, 4) PATH '$.value',
                            base_value DECIMAL(20, 4) PATH '$.base_value'
                        )
                    )
                ) AS fee
                LEFT JOIN JSON_TABLE(
                    JSON_UNQUOTE(cof.custom_fees_invoiced),
                    '$.*' COLUMNS (
                        NESTED PATH '$.*' COLUMNS (
                            title VARCHAR(255) PATH '$.title',
                            `value` DECIMAL(20, 4) PATH '$.value',
                            base_value DECIMAL(20, 4) PATH '$.base_value'
                        )
                    )
                ) AS invoiced_fee ON invoiced_fee.title = fee.title
                LEFT JOIN JSON_TABLE(
                    JSON_UNQUOTE(cof.custom_fees_refunded),
                    '$.*' COLUMNS (
                        NESTED PATH '$.*' COLUMNS (
                            title VARCHAR(255) PATH '$.title',
                            `value` DECIMAL(20, 4) PATH '$.value',
                            base_value DECIMAL(20, 4) PATH '$.base_value'
                        )
                    )
                ) AS refunded_fee ON refunded_fee.title = fee.title
                LEFT JOIN $salesOrderTable AS so ON so.entity_id = cof.order_entity_id
                GROUP BY
                    so.store_id,
                    $periodExpression,
                    so.order_currency_code,
                    fee.title
                SQL;
            // phpcs:enable

            if ($subSelect !== null) {
                /** @var string $periodCondition */
                $periodCondition = $this->_makeConditionFromDateRangeSelect($subSelect, 'period') ?: '1=0';
                $query .= "\nHAVING $periodCondition";
            }

            $query .= ';';

            $customOrderFees = $connection->query($query)->fetchAll();

            if (count($customOrderFees) > 0) {
                $connection->insertMultiple($this->getMainTable(), $customOrderFees);
            }

            $connection->commit();
        } catch (Exception $exception) {
            $connection->rollBack();

            throw $exception;
        }

        $this->_setFlagData('report_custom_order_fees_aggregated');

        return $this;
    }

    public function getDatabaseServerVersion(): string
    {
        /** @var AdapterInterface $connection */
        $connection = $this->getConnection();
        $version = $connection->fetchOne('SELECT VERSION();');

        return $version;
    }

    public function isDatabaseServerSupported(string $version): bool
    {
        $isMatched = preg_match('/^[\d.]+/', $version, $matches);

        return match (true) {
            !$isMatched => false,
            str_contains(strtolower($version), 'mariadb') => version_compare($matches[0], '10.6.0', '>='),
            str_contains(strtolower($version), 'aurora') => version_compare(rtrim($matches[0], '.'), '8.0', '>='),
            default => version_compare($matches[0], '8.0.4', '>='),
        };
    }
}
