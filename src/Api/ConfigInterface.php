<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Api;

use JosephLeedy\CustomFees\Model\FeeType;
use Magento\Framework\Exception\LocalizedException;

interface ConfigInterface
{
    public const CONFIG_PATH_CUSTOM_FEES = 'sales/custom_order_fees/custom_fees';

    /**
     * @return array{
     *     code: string,
     *     title: string,
     *     type: value-of<FeeType>,
     *     value: float,
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
     *         }
     *     }
     * }[]
     * @throws LocalizedException
     */
    public function getCustomFees(int|string|null $storeId = null): array;
}
