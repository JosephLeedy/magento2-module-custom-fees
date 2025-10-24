<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Api;

use JosephLeedy\CustomFees\Model\DisplayType;
use JosephLeedy\CustomFees\Model\FeeStatus;
use JosephLeedy\CustomFees\Model\FeeType;
use Magento\Framework\Exception\LocalizedException;

interface ConfigInterface
{
    public const CONFIG_PATH_CUSTOM_FEES = 'sales/custom_order_fees/custom_fees';
    public const CONFIG_PATH_REPORTS_CUSTOM_ORDER_FEES_ENABLE_AGGREGATION
        = 'reports/custom_order_fees/enable_aggregation';
    public const CONFIG_PATH_REPORTS_CUSTOM_ORDER_FEES_AGGREGATION_TIME = 'reports/custom_order_fees/aggregation_time';
    public const CONFIG_PATH_REPORTS_CUSTOM_ORDER_FEES_AGGREGATION_FREQUENCY
        = 'reports/custom_order_fees/aggregation_frequency';
    public const CONFIG_PATH_TAX_CLASS_CUSTOM_FEE_TAX_CLASS = 'tax/classes/custom_fee_tax_class';
    public const CONFIG_PATH_TAX_CALCULATION_CUSTOM_FEES_INCLUDE_TAX = 'tax/calculation/custom_fees_include_tax';
    public const CONFIG_PATH_TAX_DISPLAY_CUSTOM_FEES = 'tax/display/custom_fees';
    public const CONFIG_PATH_TAX_CART_DISPLAY_CUSTOM_FEES = 'tax/cart_display/custom_fees';
    public const CONFIG_PATH_TAX_SALES_DISPLAY_CUSTOM_FEES = 'tax/sales_display/custom_fees';

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

    public function getTaxClass(int|string|null $storeId = null): int;

    public function isTaxIncluded(int|string|null $storeId = null): bool;

    public function getDisplayType(int|string|null $storeId = null): DisplayType;

    public function getCartDisplayType(int|string|null $storeId = null): DisplayType;

    public function getSalesDisplayType(int|string|null $storeId = null): DisplayType;
}
