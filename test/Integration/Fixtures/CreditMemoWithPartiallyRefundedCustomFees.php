<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Fixture;

use Magento\Framework\DataObject;
use Magento\TestFramework\Fixture\Data\ProcessorInterface;
use Magento\TestFramework\Fixture\RevertibleDataFixtureInterface;

class CreditMemoWithPartiallyRefundedCustomFees implements RevertibleDataFixtureInterface
{
    private const DEFAULT_DATA = [
        'perform_refund' => true,
    ];

    public function __construct() {}

    public function apply(array $data = []): ?DataObject
    {
        $data = array_merge(self::DEFAULT_DATA, $data);

        return null;
    }

    public function revert(DataObject $data): void
    {
    }
}
