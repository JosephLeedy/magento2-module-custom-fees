<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Plugin\Ui\Component;

use JosephLeedy\CustomFees\Plugin\Ui\Component\AbstractComponentPlugin;
use Magento\Framework\Interception\PluginList\PluginList;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Ui\Component\AbstractComponent;
use Magento\Ui\Component\Listing\Columns;
use PHPUnit\Framework\TestCase;

#[AppArea('adminhtml')]
class AbstractComponentPluginTest extends TestCase
{
    public function testIsConfiguredCorrectly(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var PluginList $pluginList */
        $pluginList = $objectManager->create(PluginList::class);
        /**
         * @var array{add_custom_fees_columns_to_order_grid: array{sortOrder: int, instance: class-string}} $plugins
         */
        $plugins = $pluginList->get(AbstractComponent::class, []);

        self::assertArrayHasKey('add_custom_fees_columns_to_order_grid', $plugins);
        self::assertSame(AbstractComponentPlugin::class, $plugins['add_custom_fees_columns_to_order_grid']['instance']);
    }

    #[DataFixture('JosephLeedy_CustomFees::../test/Integration/_files/orders_with_custom_fees.php')]
    public function testAddsCustomFeeColumnsToSalesOrderGrid(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var DataProvider $dataProvider */
        $dataProvider = $objectManager->create(
            DataProvider::class,
            [
                'name' => 'sales_order_grid_data_source',
                'requestFieldName' => 'id',
                'primaryFieldName' => 'main_table.entity_id',
            ]
        );
        /** @var ContextInterface $context */
        $context = $objectManager->create(
            ContextInterface::class,
            [
                'dataProvider' => $dataProvider,
            ]
        );
        /** @var Columns $salesOrderGridColumns */
        $salesOrderGridColumns = $objectManager->create(
            Columns::class,
            [
                'context' => $context,
                'data' => [
                    'name' => 'sales_order_columns',
                ]
            ]
        );

        $salesOrderGridColumns->prepare();

        $components = $salesOrderGridColumns->getChildComponents();

        self::assertArrayHasKey('test_fee_0_base', $components);
        self::assertArrayHasKey('test_fee_0', $components);
        self::assertArrayHasKey('test_fee_1_base', $components);
        self::assertArrayHasKey('test_fee_1', $components);
    }

    public function testDoesNotAddCustomFeeColumnsToSalesOrderGrid(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var Columns $columns */
        $columns = $objectManager->create(
            Columns::class,
            [
                'data' => [
                    'name' => 'sales_order_invoice_columns',
                ]
            ]
        );

        $columns->prepare();

        $components = $columns->getChildComponents();

        self::assertArrayNotHasKey('test_fee_0_base', $components);
        self::assertArrayNotHasKey('test_fee_0', $components);
        self::assertArrayNotHasKey('test_fee_1_base', $components);
        self::assertArrayNotHasKey('test_fee_1', $components);
    }
}
