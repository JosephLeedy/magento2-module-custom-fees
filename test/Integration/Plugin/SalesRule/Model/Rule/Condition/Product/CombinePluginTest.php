<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Plugin\SalesRule\Model\Rule\Condition\Product;

use JosephLeedy\CustomFees\Model\Rule\Condition\CustomFee;
use JosephLeedy\CustomFees\Plugin\SalesRule\Model\Rule\Condition\Product\CombinePlugin;
use Magento\Framework\App\Area;
use Magento\Framework\Interception\PluginList\PluginList;
use Magento\SalesRule\Model\Rule\Condition\Product;
use Magento\SalesRule\Model\Rule\Condition\Product\Combine;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

use function __;

#[AppArea(Area::AREA_ADMINHTML)]
final class CombinePluginTest extends TestCase
{
    public function testIsConfiguredCorrectly(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var PluginList $pluginList */
        $pluginList = $objectManager->create(PluginList::class);
        /** @var array{add_custom_fee_sales_rule_condition?: array{sortOrder: int, instance: class-string}} $plugins */
        $plugins = $pluginList->get(Combine::class, []);

        self::assertArrayHasKey('add_custom_fee_sales_rule_condition', $plugins);
        self::assertSame(CombinePlugin::class, $plugins['add_custom_fee_sales_rule_condition']['instance']);
    }

    public function testAddsCustomFeeConditionToSelectOptions(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var Combine $combine */
        $combine = $objectManager->create(Combine::class);

        $expectedSelectOptions = [
            'label' => __('Custom Fee'),
            'value' => CustomFee::class . '|custom_fee',
        ];
        $actualSelectOptions = $combine->getNewChildSelectOptions();

        self::assertContainsEquals($expectedSelectOptions, $actualSelectOptions);
    }
}
