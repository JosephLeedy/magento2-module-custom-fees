<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Plugin\Framework\View\Element\UiComponent\DataProvider;

use JosephLeedy\CustomFees\Plugin\Framework\View\Element\UiComponent\DataProvider\DataProviderPlugin;
use Magento\Framework\Interception\PluginList\PluginList;
use Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[AppArea('adminhtml')]
final class DataProviderPluginTest extends TestCase
{
    public function testIsConfiguredCorrectly(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var PluginList $pluginList */
        $pluginList = $objectManager->create(PluginList::class);
        /**
         * @var array{process_custom_order_fees: array{sortOrder: int, instance: class-string}} $plugins
         */
        $plugins = $pluginList->get(DataProvider::class, []);

        self::assertArrayHasKey('process_custom_order_fees', $plugins);
        self::assertSame(DataProviderPlugin::class, $plugins['process_custom_order_fees']['instance']);
    }

    #[DataFixture('JosephLeedy_CustomFees::../test/Integration/_files/orders_with_custom_fees.php')]
    public function testProcessesCustomFeesInOrderGridItems(): void
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

        /**
         * @var array{
         *     items: array<int, array{
         *         test_fee_0_base: string,
         *         test_fee_0: string,
         *         test_fee_1_base: string,
         *         test_fee_1: string
         *     }>
         * } $data
         */
        $data = $dataProvider->getData();

        self::assertArrayHasKey('test_fee_0_base', $data['items'][0]);
        self::assertArrayHasKey('test_fee_0', $data['items'][0]);
        self::assertArrayHasKey('test_fee_1_base', $data['items'][0]);
        self::assertArrayHasKey('test_fee_1', $data['items'][0]);
    }

    #[DataFixture('Magento/Sales/_files/invoice_list.php')]
    public function testDoesNotProcessesCustomFeesInOrderGridItems(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var DataProvider $dataProvider */
        $dataProvider = $objectManager->create(
            DataProvider::class,
            [
                'name' => 'sales_order_invoice_grid_data_source',
                'requestFieldName' => 'id',
                'primaryFieldName' => 'entity_id',
            ]
        );

        /**
         * @var array{items: array<int, array<string, mixed>>} $data
         */
        $data = $dataProvider->getData();

        self::assertArrayNotHasKey('test_fee_0_base', $data['items'][0]);
        self::assertArrayNotHasKey('test_fee_0', $data['items'][0]);
        self::assertArrayNotHasKey('test_fee_1_base', $data['items'][0]);
        self::assertArrayNotHasKey('test_fee_1', $data['items'][0]);
    }
}
