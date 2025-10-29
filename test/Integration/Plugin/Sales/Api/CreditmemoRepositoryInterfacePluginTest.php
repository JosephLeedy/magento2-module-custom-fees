<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Plugin\Sales\Api;

use ColinODell\PsrTestLogger\TestLogger;
use JosephLeedy\CustomFees\Api\CustomOrderFeesRepositoryInterface;
use JosephLeedy\CustomFees\Model\CustomOrderFee\Refunded as RefundedCustomFee;
use JosephLeedy\CustomFees\Model\CustomOrderFees;
use JosephLeedy\CustomFees\Model\FeeType;
use JosephLeedy\CustomFees\Model\ResourceModel\CustomOrderFees as CustomOrderFeesResourceModel;
use JosephLeedy\CustomFees\Plugin\Sales\Api\CreditmemoRepositoryInterfacePlugin;
use Magento\Framework\App\Area;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\State;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Interception\PluginList\PluginList;
use Magento\Sales\Api\CreditmemoManagementInterface;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Controller\Adminhtml\Order\CreditmemoLoader;
use Magento\Sales\Model\Order;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

use function __;

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
    public function testSavesRefundedCustomFeesForCreditMemo(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        $creditMemoData = [
            'items' => [],
            'custom_fees' => [
                'test_fee_0' => '5.00',
                'test_fee_1' => '0.00',
            ],
        ];
        /** @var RequestInterface $request */
        $request = $objectManager->get(RequestInterface::class);
        /** @var CreditmemoLoader $creditMemoLoader */
        $creditMemoLoader = $objectManager->create(CreditmemoLoader::class);
        /** @var CreditmemoManagementInterface $creditMemoManagement */
        $creditMemoManagement = $objectManager->create(CreditmemoManagementInterface::class);
        /** @var CustomOrderFeesRepositoryInterface $customOrderFeesRepository */
        $customOrderFeesRepository = $objectManager->create(CustomOrderFeesRepositoryInterface::class);

        $order->loadByIncrementId('100000001');

        foreach ($order->getAllItems() as $item) {
            $creditMemoData['items'][$item->getId()] = [
                'qty' => $item->getQtyOrdered(),
            ];
        }

        $request->setParams(['creditmemo' => $creditMemoData]);

        $creditMemoLoader->setOrderId($order->getId());
        $creditMemoLoader->setCreditmemo($creditMemoData);

        /** @var CreditmemoInterface $creditMemo */
        $creditMemo = $creditMemoLoader->load();

        $creditMemoManagement->refund($creditMemo, true);

        $creditMemoId = $creditMemo->getEntityId();
        $customOrderFees = $customOrderFeesRepository->getByOrderId($order->getId());
        $expectedRefundedCustomFees = [
            $creditMemoId => [
                'test_fee_0' => $objectManager->create(
                    RefundedCustomFee::class,
                    [
                        'data' => [
                            'credit_memo_id' => $creditMemoId,
                            'code' => 'test_fee_0',
                            'title' => 'Test Fee',
                            'type' => FeeType::Fixed,
                            'percent' => null,
                            'show_percentage' => false,
                            'base_value' => 5.00,
                            'value' => 5.00,
                            'base_value_with_tax' => 5.00,
                            'value_with_tax' => 5.00,
                            'base_tax_amount' => 0.00,
                            'tax_amount' => 0.00,
                        ],
                    ],
                ),
                'test_fee_1' => $objectManager->create(
                    RefundedCustomFee::class,
                    [
                        'data' => [
                            'credit_memo_id' => $creditMemoId,
                            'code' => 'test_fee_1',
                            'title' => 'Another Test Fee',
                            'type' => FeeType::Fixed,
                            'percent' => null,
                            'show_percentage' => false,
                            'base_value' => 0.00,
                            'value' => 0.00,
                            'base_value_with_tax' => 0.00,
                            'value_with_tax' => 0.00,
                            'base_tax_amount' => 0.00,
                            'tax_amount' => 0.00,
                        ],
                    ],
                ),
            ],
        ];
        $actualRefundedCustomFees = $customOrderFees->getCustomFeesRefunded();

        self::assertEquals($expectedRefundedCustomFees, $actualRefundedCustomFees);
    }

    /**
     * @dataProvider doesNotSaveRefundedCustomFeesDataProvider
     */
    public function testDoesNotSaveRefundedCustomFeesForCreditMemo(bool $orderHasCustomFees): void
    {
        $resolver = Resolver::getInstance();

        $resolver->setCurrentFixtureType(\Magento\TestFramework\Annotation\DataFixture::ANNOTATION);

        if ($orderHasCustomFees) {
            $resolver->requireDataFixture(
                'JosephLeedy_CustomFees::../test/Integration/_files/invoice_with_custom_fees.php',
            );
        } else {
            $resolver->requireDataFixture('JosephLeedy_CustomFees::../test/Integration/_files/invoice.php');
        }

        $objectManager = Bootstrap::getObjectManager();
        $appState = $objectManager->get(State::class);

        /* Somehow, the area code gets reset to default after the fixture is loaded using the Resolver, so we need to
           set it again so that the plug-in for saving the refunded custom fees is registered and called correctly. */
        $appState->setAreaCode(Area::AREA_ADMINHTML);

        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        $creditMemoData = [
            'items' => [],
        ];
        /** @var RequestInterface $request */
        $request = $objectManager->get(RequestInterface::class);
        /** @var CreditmemoLoader $creditMemoLoader */
        $creditMemoLoader = $objectManager->create(CreditmemoLoader::class);
        /** @var CreditmemoManagementInterface $creditMemoManagement */
        $creditMemoManagement = $objectManager->create(CreditmemoManagementInterface::class);
        /** @var CustomOrderFees $customOrderFees */
        $customOrderFees = $objectManager->create(CustomOrderFees::class);
        /** @var CustomOrderFeesResourceModel $customOrderFeesResourceModel */
        $customOrderFeesResourceModel = $objectManager->create(CustomOrderFeesResourceModel::class);

        $order->loadByIncrementId('100000001');

        foreach ($order->getAllItems() as $item) {
            $creditMemoData['items'][$item->getId()] = [
                'qty' => $item->getQtyOrdered(),
            ];
        }

        if (!$orderHasCustomFees) {
            $creditMemoData['custom_fees'] = [
                'test_fee_0' => 0.00,
                'test_fee_1' => 1.50,
            ];
        }

        $request->setParams(['creditmemo' => $creditMemoData]);

        $creditMemoLoader->setOrderId($order->getId());
        $creditMemoLoader->setCreditmemo($creditMemoData);

        /** @var CreditmemoInterface $creditMemo */
        $creditMemo = $creditMemoLoader->load();

        if ($orderHasCustomFees) {
            $creditMemo->getExtensionAttributes()->setRefundedCustomFees([]);
        }

        $creditMemoManagement->refund($creditMemo, true);

        /* The Custom Order Fees Repository can be used to load the order's custom fees because it throws an exception
           if the order does not have custom fees. */
        $customOrderFeesResourceModel->load($customOrderFees, $order->getId(), 'order_entity_id');

        self::assertEmpty($customOrderFees->getCustomFeesRefunded());
    }

    #[DataFixture('JosephLeedy_CustomFees::../test/Integration/_files/invoice_with_custom_fees.php')]
    public function testLogsExceptionIfRefundedCustomFeesCannotBeSaved(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        $creditMemoData = [
            'items' => [],
            'custom_fees' => [
                'test_fee_0' => '5.00',
                'test_fee_1' => '0.00',
            ],
        ];
        /** @var RequestInterface $request */
        $request = $objectManager->get(RequestInterface::class);
        /** @var CreditmemoLoader $creditMemoLoader */
        $creditMemoLoader = $objectManager->create(CreditmemoLoader::class);
        $customOrderFeesRepositoryStub = $this->createStub(CustomOrderFeesRepositoryInterface::class);
        /** @var CouldNotSaveException $couldNotSaveException */
        $couldNotSaveException = $objectManager->create(
            CouldNotSaveException::class,
            [
                'phrase' => __(
                    'Could not save custom fees for order with ID "%1". Error: "%2"',
                    1,
                    '2006: MySQL server has gone away',
                ),
            ],
        );
        /** @var CreditmemoManagementInterface $creditMemoManagement */
        $creditMemoManagement = $objectManager->create(CreditmemoManagementInterface::class);
        $testLogger = new TestLogger();

        $order->loadByIncrementId('100000001');

        foreach ($order->getAllItems() as $item) {
            $creditMemoData['items'][$item->getId()] = [
                'qty' => $item->getQtyOrdered(),
            ];
        }

        $request->setParams(['creditmemo' => $creditMemoData]);

        $creditMemoLoader->setOrderId($order->getId());
        $creditMemoLoader->setCreditmemo($creditMemoData);

        /** @var CreditmemoInterface $creditMemo */
        $creditMemo = $creditMemoLoader->load();

        $customOrderFeesRepositoryStub
            ->method('save')
            ->willThrowException($couldNotSaveException);

        $objectManager->configure(
            [
                CreditmemoRepositoryInterface::class => [
                    'shared' => true,
                ],
                LoggerInterface::class => [
                    'shared' => true,
                ],
                'preferences' => [
                    LoggerInterface::class => TestLogger::class,
                ],
            ],
        );
        $objectManager->addSharedInstance(
            $customOrderFeesRepositoryStub,
            CustomOrderFeesRepositoryInterface::class,
            true,
        );
        $objectManager->addSharedInstance($testLogger, LoggerInterface::class, true);

        $creditMemoManagement->refund($creditMemo, true);

        self::assertTrue(
            $testLogger->hasRecord(
                [
                    'message' => __(
                        'Could not save refunded custom fees. Error: "%1"',
                        $couldNotSaveException->getMessage(),
                    ),
                    'context' => [
                        'exception' => $couldNotSaveException,
                    ],
                ],
                LogLevel::CRITICAL,
            ),
        );
    }

    /**
     * @return array<string, array<string, bool>>
     */
    public function doesNotSaveRefundedCustomFeesDataProvider(): array
    {
        return [
            'if credit memo does not have custom fees' => [
                'orderHasCustomFees' => true,
            ],
            'if order does not have custom fees' => [
                'orderHasCustomFees' => false,
            ],
        ];
    }
}
