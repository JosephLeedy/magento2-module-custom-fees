<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Plugin\Sales\Api;

use JosephLeedy\CustomFees\Api\Data\CustomOrderFeeInterface;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFeesInterface;
use JosephLeedy\CustomFees\Model\FeeType;
use JosephLeedy\CustomFees\Plugin\Sales\Api\OrderRepositoryInterfacePlugin;
use Magento\Framework\Interception\PluginList\PluginList;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order as OrderResource;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

// phpcs:ignore Magento2.PHP.FinalImplementation.FoundFinal
final class OrderRepositoryInterfacePluginTest extends TestCase
{
    /**
     * @magentoAppArea frontend
     */
    public function testIsConfiguredCorrectly(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var PluginList $pluginList */
        $pluginList = $objectManager->create(PluginList::class);
        /**
         * @var array{
         *     add_custom_fees_to_order?: array{sortOrder: int, instance: class-string},
         *     save_custom_order_fees?: array{sortOrder: int, instance: class-string}
         * } $plugins
         */
        $plugins = $pluginList->get(OrderRepositoryInterface::class, []);

        self::assertArrayHasKey('add_custom_fees_to_order', $plugins);
        self::assertArrayHasKey('save_custom_order_fees', $plugins);
        // @phpstan-ignore-next-line
        self::assertSame(OrderRepositoryInterfacePlugin::class, $plugins['add_custom_fees_to_order']['instance']);
        // @phpstan-ignore-next-line
        self::assertSame(OrderRepositoryInterfacePlugin::class, $plugins['save_custom_order_fees']['instance']);
    }

    /**
     * @magentoDataFixture JosephLeedy_CustomFees::../test/Integration/_files/order_with_custom_fees.php
     */
    public function testGetsCustomOrderFeesForAnOrder(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var Order $order */
        $order = $objectManager->create(OrderInterface::class);
        /** @var OrderResource $orderResource */
        $orderResource = $objectManager->create(OrderResource::class);
        /** @var OrderRepositoryInterface $orderRepository */
        $orderRepository = $objectManager->create(OrderRepositoryInterface::class);
        $expectedCustomOrderFees = [
            'test_fee_0' => $objectManager->create(
                CustomOrderFeeInterface::class,
                [
                    'data' => [
                        'code' => 'test_fee_0',
                        'title' => 'Test Fee',
                        'type' => FeeType::Fixed,
                        'percent' => null,
                        'show_percentage' => false,
                        'base_value' => 5.00,
                        'value' => 5.00,
                        'base_discount_amount' => 0.00,
                        'discount_amount' => 0.00,
                        'discount_rate' => 0.00,
                        'base_value_with_tax' => 5.00,
                        'value_with_tax' => 5.00,
                        'base_tax_amount' => 0.00,
                        'tax_amount' => 0.00,
                        'tax_rate' => 0.00,
                    ],
                ],
            ),
            'test_fee_1' => $objectManager->create(
                CustomOrderFeeInterface::class,
                [
                    'data' => [
                        'code' => 'test_fee_1',
                        'title' => 'Another Test Fee',
                        'type' => FeeType::Fixed,
                        'percent' => null,
                        'show_percentage' => false,
                        'base_value' => 1.50,
                        'value' => 1.50,
                        'base_discount_amount' => 0.00,
                        'discount_amount' => 0.00,
                        'discount_rate' => 0.00,
                        'base_value_with_tax' => 1.50,
                        'value_with_tax' => 1.50,
                        'base_tax_amount' => 0.00,
                        'tax_amount' => 0.00,
                        'tax_rate' => 0.00,
                    ],
                ],
            ),
        ];

        // Load the order by its increment ID to avoid hard-coding the entity ID, which can change.
        $orderResource->load($order, '100000001', 'increment_id');

        // Reload the entire order to ensure that custom fees are added
        /** @var int $orderId */
        $orderId = $order->getId();
        $fullOrder = $orderRepository->get($orderId);

        unset($order);

        $actualCustomOrderFees = $fullOrder
            ->getExtensionAttributes()
            ?->getCustomOrderFees()
            ?->getCustomFeesOrdered();

        self::assertEquals($expectedCustomOrderFees, $actualCustomOrderFees);
    }

    /**
     * @magentoDataFixture Magento/Sales/_files/order.php
     */
    public function testSavesCustomFeesForAnOrder(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var Order $order */
        $order = $objectManager->create(OrderInterface::class);
        /** @var OrderResource $orderResource */
        $orderResource = $objectManager->create(OrderResource::class);
        /** @var OrderRepositoryInterface $orderRepository */
        $orderRepository = $objectManager->create(OrderRepositoryInterface::class);
        /** @var CustomOrderFeesInterface $customOrderFees */
        $customOrderFees = $objectManager->create(CustomOrderFeesInterface::class);

        // Load the order by its increment ID to avoid hard-coding the entity ID, which can change.
        $orderResource->load($order, '100000001', 'increment_id');

        /** @var int|string $orderId */
        $orderId = $order->getEntityId();

        $customOrderFees->setOrderId($orderId);
        $customOrderFees->setCustomFeesOrdered(
            [
                'test_fee_0' => $objectManager->create(
                    CustomOrderFeeInterface::class,
                    [
                        'data' => [
                            'code' => 'test_fee_0',
                            'title' => 'Test Fee',
                            'type' => FeeType::Fixed,
                            'percent' => null,
                            'show_percentage' => false,
                            'base_value' => 5.00,
                            'value' => 4.50,
                            'base_value_with_tax' => 5.00,
                            'value_with_tax' => 4.50,
                            'base_tax_amount' => 0.00,
                            'tax_amount' => 0.00,
                            'tax_rate' => 0.00,
                        ],
                    ],
                ),
                'test_fee_1' => $objectManager->create(
                    CustomOrderFeeInterface::class,
                    [
                        'data' => [
                            'code' => 'test_fee_1',
                            'title' => 'Another Test Fee',
                            'type' => FeeType::Fixed,
                            'percent' => null,
                            'show_percentage' => false,
                            'base_value' => 1.50,
                            'value' => 1.35,
                            'base_value_with_tax' => 1.50,
                            'value_with_tax' => 1.35,
                            'base_tax_amount' => 0.00,
                            'tax_amount' => 0.00,
                            'tax_rate' => 0.00,
                        ],
                    ],
                ),
            ],
        );

        $order
            ->getExtensionAttributes()
            ?->setCustomOrderFees($customOrderFees);

        $orderRepository->save($order);

        self::assertIsNumeric($customOrderFees->getId());
    }
}
