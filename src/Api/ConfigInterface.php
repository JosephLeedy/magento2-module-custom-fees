<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Api;

use JosephLeedy\CustomFees\Model\FeeStatus;
use JosephLeedy\CustomFees\Model\FeeType;
use Magento\Framework\Exception\LocalizedException;

interface ConfigInterface
{
    public const CONFIG_PATH_CUSTOM_FEES = 'sales/custom_order_fees/custom_fees';
    public const CONFIG_PATH_CUSTOM_ORDER_FEES_REPORT_AGGREGATION_ENABLE
        = 'custom_order_fees_report/aggregation/enable';
    public const CONFIG_PATH_CUSTOM_ORDER_FEES_REPORT_AGGREGATION_TIME = 'custom_order_fees_report/aggregation/time';
    public const CONFIG_PATH_CUSTOM_ORDER_FEES_REPORT_AGGREGATION_FREQUENCY
        = 'custom_order_fees_report/aggregation/frequency';

    /**
     * @return array{
     *     code: string,
     *     title: string,
     *     type: value-of<FeeType>,
     *     value: float,
     *     status: value-of<FeeStatus>,
     *     advanced: array{
     *         conditions?: array{
     *             type: class-string,
     *             aggregator: string,
     *             value: '0'|'1',
     *             conditions: array<
     *                 int,
     *                 array{
     *                     type: class-string,
     *                     attribute: string,
     *                     operator: string,
     *                     value: string
     *                 }
     *             >
     *         },
     *         show_percentage: bool,
     *     }
     * }[]
     * @throws LocalizedException
     */
    public function getCustomFees(int|string|null $storeId = null): array;

    public function isCustomOrderFeesReportAggregationEnabled(): bool;

    /**
     * @phpstan-return string Hour, minute and seconds separated by commas, e.g. "05,30,10"
     */
    public function getCustomOrderFeesReportAggregationTime(): string;

    /**
     * @phpstan-return 'D'|'W'|'M' "D" for daily, "W" for weekly or "M" for monthly
     */
    public function getCustomOrderFeesReportAggregationFrequency(): string;
}
