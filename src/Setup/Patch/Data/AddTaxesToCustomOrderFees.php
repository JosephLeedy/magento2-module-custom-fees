<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Setup\Patch\Data;

use JosephLeedy\CustomFees\Service\CustomOrderFeeTaxValueAdder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Adds tax values to ordered, invoiced and refunded custom fees as needed
 *
 * @since 1.4.0
 */
class AddTaxesToCustomOrderFees implements DataPatchInterface
{
    public function __construct(private readonly CustomOrderFeeTaxValueAdder $customOrderFeeTaxValueAdder) {}

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }

    /**
     * @throws LocalizedException
     */
    public function apply(): self
    {
        $this->customOrderFeeTaxValueAdder->addTaxValues();

        return $this;
    }
}
