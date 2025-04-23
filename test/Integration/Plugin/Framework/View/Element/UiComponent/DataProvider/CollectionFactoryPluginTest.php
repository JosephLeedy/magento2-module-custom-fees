<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Plugin\Framework\View\Element\UiComponent\DataProvider;

use JosephLeedy\CustomFees\Plugin\Framework\View\Element\UiComponent\DataProvider\CollectionFactoryPlugin;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Framework\Interception\PluginList\PluginList;
use Magento\Framework\View\Element\UiComponent\DataProvider\CollectionFactory;
use Magento\Sales\Model\ResourceModel\Order\Grid\Collection as OrderGridCollection;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[AppArea('adminhtml')]
final class CollectionFactoryPluginTest extends TestCase
{
    public function testIsConfiguredCorrectly(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var PluginList $pluginList */
        $pluginList = $objectManager->create(PluginList::class);
        /**
         * @var array{add_custom_fees_to_order_grid_items: array{sortOrder: int, instance: class-string}} $plugins
         */
        $plugins = $pluginList->get(CollectionFactory::class, []);

        self::assertArrayHasKey('add_custom_fees_to_order_grid_items', $plugins);
        self::assertSame(CollectionFactoryPlugin::class, $plugins['add_custom_fees_to_order_grid_items']['instance']);
    }

    #[DataFixture('JosephLeedy_CustomFees::../test/Integration/_files/orders_with_custom_fees.php')]
    public function testAddsCustomFeesToOrderGridCollection(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var CollectionFactory $collectionFactory */
        $collectionFactory = $objectManager->create(
            CollectionFactory::class,
            [
                'collections' => [
                    'sales_order_grid_data_source' => OrderGridCollection::class,
                ],
            ]
        );

        $report = $collectionFactory->getReport('sales_order_grid_data_source');
        $firstItem = $report->getFirstItem();

        self::assertArrayHasKey('custom_fees', (array) $firstItem->getData());
    }

    public function testDoesNotAddCustomFeesToOrderGridCollection(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var CollectionFactory $collectionFactory */
        $collectionFactory = $objectManager->create(
            CollectionFactory::class,
            [
                'collections' => [
                    'product_grid_data_source' => ProductCollection::class,
                ],
            ]
        );

        $report = $collectionFactory->getReport('product_grid_data_source');
        $firstItem = $report->getFirstItem();

        self::assertArrayNotHasKey('custom_fees', (array) $firstItem->getData());
    }
}
