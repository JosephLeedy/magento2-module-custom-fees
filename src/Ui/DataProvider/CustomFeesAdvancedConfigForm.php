<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Ui\DataProvider;

use Magento\Framework\Api\Filter;
use Magento\Ui\DataProvider\AbstractDataProvider;

class CustomFeesAdvancedConfigForm extends AbstractDataProvider
{
    public function addFilter(Filter $filter) // phpcs:ignore Magento2.CodeAnalysis.EmptyBlock.DetectedFunction
    {
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return [];
    }
}
