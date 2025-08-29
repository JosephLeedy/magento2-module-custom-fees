<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Plugin\Sales\Api;

use JosephLeedy\CustomFees\Api\CustomOrderFeesRepositoryInterface;
use JosephLeedy\CustomFees\Plugin\Sales\Api\CreditmemoRepositoryInterfacePlugin;
use Magento\Framework\App\Area;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Interception\PluginList\PluginList;
use Magento\Sales\Api\CreditmemoManagementInterface;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Controller\Adminhtml\Order\CreditmemoLoader;
use Magento\Sales\Model\Order;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[AppArea(Area::AREA_ADMINHTML)]
final class CreditmemoRepositoryInterfacePluginTest extends TestCase
{
    public function testIsConfiguredCorrectly(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var PluginList $pluginList */
        $pluginList = $objectManager->create(PluginList::class);
        /**
         * @var array{save_refunded_custom_fees?: array{sortOrder: int, instance: class-string}} $plugins
         */
        $plugins = $pluginList->get(CreditmemoRepositoryInterface::class, []);

        self::assertArrayHasKey('save_refunded_custom_fees', $plugins);
        self::assertSame(
            CreditmemoRepositoryInterfacePlugin::class,
            $plugins['save_refunded_custom_fees']['instance'] ?? null,
        );
    }

    #[DataFixture('JosephLeedy_CustomFees::../test/Integration/_files/invoice_with_custom_fees.php')]
    public function testSavesRefundedCustomFeesForCreditmemo(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        $creditmemoData = [
            'items' => [],
            'custom_fees' => [
                'test_fee_0' => '5.00',
                'test_fee_1' => '0.00',
            ],
        ];
        /** @var RequestInterface $request */
        $request = $objectManager->get(RequestInterface::class);
        /** @var CreditmemoLoader $creditmemoLoader */
        $creditmemoLoader = $objectManager->create(CreditmemoLoader::class);
        /** @var CreditmemoManagementInterface $creditmemoManagement */
        $creditmemoManagement = $objectManager->create(CreditmemoManagementInterface::class);
        /** @var CustomOrderFeesRepositoryInterface $customOrderFeesRepository */
        $customOrderFeesRepository = $objectManager->create(CustomOrderFeesRepositoryInterface::class);

        $order->loadByIncrementId('100000001');

        foreach ($order->getAllItems() as $item) {
            $creditmemoData['items'][$item->getId()] = [
                'qty' => $item->getQtyOrdered(),
            ];
        }

        $request->setParams(['creditmemo' => $creditmemoData]);

        $creditmemoLoader->setOrderId($order->getId());
        $creditmemoLoader->setCreditmemo($creditmemoData);

        /** @var CreditmemoInterface $creditmemo */
        $creditmemo = $creditmemoLoader->load();

        $creditmemoManagement->refund($creditmemo, true);

        $customOrderFees = $customOrderFeesRepository->getByOrderId($order->getId());
        $expectedRefundedCustomFees = [
            'test_fee_0' => [
                'credit_memo_id' => $creditmemo->getId(),
                'code' => 'test_fee_0',
                'title' => 'Test Fee',
                'type' => 'fixed',
                'percent' => null,
                'show_percentage' => false,
                'base_value' => 5.00,
                'value' => 5.00,
            ],
            'test_fee_1' => [
                'credit_memo_id' => $creditmemo->getId(),
                'code' => 'test_fee_1',
                'title' => 'Another Test Fee',
                'type' => 'fixed',
                'percent' => null,
                'show_percentage' => false,
                'base_value' => 0.00,
                'value' => 0.00,
            ],
        ];
        $actualRefundedCustomFees = $customOrderFees->getCustomFeesRefunded();

        self::assertNotEmpty($actualRefundedCustomFees);
        self::assertEquals($expectedRefundedCustomFees, $actualRefundedCustomFees);
    }
}
