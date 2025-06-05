<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Setup\CategorySetup;
use Magento\Catalog\Setup\CategorySetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class MarkProductAttributesAsUsableForFeeConditions implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly CategorySetupFactory $categorySetupFactory,
    ) {}

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }

    public function apply(): self
    {
        /** @var CategorySetup $categorySetup */
        $categorySetup = $this->categorySetupFactory->create(['setup' => $this->moduleDataSetup]);

        $categorySetup->updateAttribute(Product::ENTITY, 'sku', 'is_used_for_custom_fee_conditions', '1');
        $categorySetup->updateAttribute(Product::ENTITY, 'manufacturer', 'is_used_for_custom_fee_conditions', '1');
        $categorySetup->updateAttribute(
            Product::ENTITY,
            'country_of_manufacture',
            'is_used_for_custom_fee_conditions',
            '1',
        );

        return $this;
    }
}
