<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Api;

use Magento\Framework\Exception\LocalizedException;

interface ConfigInterface
{
    public const CONFIG_PATH_CUSTOM_FEES = 'sales/custom_order_fees/custom_fees';

    /**
     * @return array{code: string, title: string, value: float}[]
     * @throws LocalizedException
     */
    public function getCustomFees(int|string|null $storeId = null): array;
}
