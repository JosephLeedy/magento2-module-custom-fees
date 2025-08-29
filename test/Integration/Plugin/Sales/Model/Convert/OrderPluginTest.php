<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Plugin\Sales\Model\Convert;

use JosephLeedy\CustomFees\Plugin\Sales\Model\Convert\OrderPlugin;
use Magento\Framework\App\Area;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Interception\PluginList\PluginList;
use Magento\Sales\Model\Convert\Order as OrderConverter;
use Magento\Sales\Model\Order;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[AppArea(Area::AREA_ADMINHTML)]
final class OrderPluginTest extends TestCase
{
    public function testIsConfiguredCorrectly(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var PluginList $pluginList */
        $pluginList = $objectManager->create(PluginList::class);
        /** @var array{init_custom_fee_data?: array{sortOrder: int, instance: class-string}} $plugins */
        $plugins = $pluginList->get(OrderConverter::class, []);

        self::assertArrayHasKey('init_custom_fee_data', $plugins);
        self::assertSame(OrderPlugin::class, $plugins['init_custom_fee_data']['instance'] ?? null);
    }

    #[DataFixture('JosephLeedy_CustomFees::../test/Integration/_files/order_with_custom_fees.php')]
    public function testInitializesCustomFeeData(): void
    {
        $refundedCustomFees = [
            'test_fee_0' => '5.00',
            'test_fee_1' => '0.00',
        ];
        $objectManager = Bootstrap::getObjectManager();
        /** @var RequestInterface $request */
        $request = $objectManager->get(RequestInterface::class);
        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        /** @var OrderConverter $orderConverter */
        $orderConverter = $objectManager->create(OrderConverter::class);

        $request->setParams(
            [
                'creditmemo' => [
                    'custom_fees' => $refundedCustomFees,
                ],
            ],
        );

        $order->loadByIncrementId('100000001');

        $creditmemo = $orderConverter->toCreditmemo($order);

        self::assertEquals($refundedCustomFees, $creditmemo->getExtensionAttributes()->getRefundedCustomFees());
    }
}
