<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Plugin\Sales\Model\Order;

use ColinODell\PsrTestLogger\TestLogger;
use JosephLeedy\CustomFees\Api\CustomOrderFeesRepositoryInterface;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFeesInterface;
use JosephLeedy\CustomFees\Model\FeeType;
use JosephLeedy\CustomFees\Plugin\Sales\Model\Order\InvoicePlugin;
use Magento\Framework\App\Area;
use Magento\Framework\DB\Transaction;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Interception\PluginList\PluginList;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

use function __;

#[AppArea(Area::AREA_ADMINHTML)]
final class InvoicePluginTest extends TestCase
{
    public function testIsConfiguredCorrectly(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var PluginList $pluginList */
        $pluginList = $objectManager->create(PluginList::class);
        /**
         * @var array{save_invoiced_custom_fees?: array{sortOrder: int, instance: class-string}} $plugins
         */
        $plugins = $pluginList->get(Invoice::class, []);

        self::assertArrayHasKey('save_invoiced_custom_fees', $plugins);
        self::assertSame(InvoicePlugin::class, $plugins['save_invoiced_custom_fees']['instance'] ?? null);
    }

    #[DataFixture('JosephLeedy_CustomFees::../test/Integration/_files/order_with_custom_fees.php')]
    public function testSavesInvoicedCustomFees(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        /** @var InvoiceService $invoiceService */
        $invoiceService = $objectManager->create(InvoiceService::class);
        /** @var Transaction $transaction */
        $transaction = $objectManager->create(Transaction::class);
        /** @var CustomOrderFeesRepositoryInterface $customOrderFeesRepository */
        $customOrderFeesRepository = $objectManager->create(CustomOrderFeesRepositoryInterface::class);

        $order->loadByIncrementId('100000001');

        $invoice = $invoiceService->prepareInvoice($order);

        $invoice->register();

        $order->setIsInProcess(true);

        $transaction
            ->addObject($invoice)
            ->addObject($order)
            ->save();

        $invoiceId = (int) $invoice->getEntityId();

        try {
            $customOrderFees = $customOrderFeesRepository->getByOrderId((int) $order->getEntityId());
        } catch (NoSuchEntityException) {
            /** @var CustomOrderFeesInterface $customOrderFees */
            $customOrderFees = $objectManager->create(CustomOrderFeesInterface::class);
        }

        $expectedInvoicedCustomFees = [
            $invoiceId => [
                'test_fee_0' => [
                    'invoice_id' => $invoiceId,
                    'code' => 'test_fee_0',
                    'title' => 'Test Fee',
                    'type' => FeeType::Fixed->value,
                    'percent' => null,
                    'show_percentage' => false,
                    'base_value' => 5.00,
                    'value' => 5.00,
                ],
                'test_fee_1' => [
                    'invoice_id' => $invoiceId,
                    'code' => 'test_fee_1',
                    'title' => 'Another Test Fee',
                    'type' => FeeType::Fixed->value,
                    'percent' => null,
                    'show_percentage' => false,
                    'base_value' => 1.50,
                    'value' => 1.50,
                ],
            ],
        ];
        $actualInvoicedCustomFees = $customOrderFees->getCustomFeesInvoiced();

        self::assertEquals($expectedInvoicedCustomFees, $actualInvoicedCustomFees);
    }

    #[DataFixture('JosephLeedy_CustomFees::../test/Integration/_files/order.php')]
    /**
     * @dataProvider doesMotSaveInvoicedCustomFeesDataProvider
     */
    public function testDoesNotSaveInvoicedCustomFees(
        bool $orderHasCustomFees,
        bool $invoiceHasCustomFees,
        bool $customFeesAlreadyInvoiced,
    ): void {
        $objectManager = Bootstrap::getObjectManager();
        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        $customFees = [
            'test_fee_0' => [
                'code' => 'test_fee_0',
                'title' => 'Test Fee',
                'type' => FeeType::Fixed->value,
                'percent' => null,
                'show_percentage' => false,
                'base_value' => 5.00,
                'value' => 5.00,
            ],
            'test_fee_1' => [
                'code' => 'test_fee_1',
                'title' => 'Another Test Fee',
                'type' => FeeType::Fixed->value,
                'percent' => null,
                'show_percentage' => false,
                'base_value' => 1.50,
                'value' => 1.50,
            ],
        ];
        /** @var OrderRepositoryInterface $orderRepository */
        $orderRepository = $objectManager->create(OrderRepositoryInterface::class);
        /** @var InvoiceService $invoiceService */
        $invoiceService = $objectManager->create(InvoiceService::class);
        /** @var Transaction $transaction */
        $transaction = $objectManager->create(Transaction::class);
        /** @var CustomOrderFeesRepositoryInterface $customOrderFeesRepository */
        $customOrderFeesRepository = $objectManager->create(CustomOrderFeesRepositoryInterface::class);
        $expectedInvoicedCustomFeeCount = 0;

        $order->loadByIncrementId('100000001');

        if ($orderHasCustomFees) {
            /** @var CustomOrderFeesInterface $customOrderFees */
            $customOrderFees = $objectManager->create(CustomOrderFeesInterface::class);

            $customOrderFees->setOrderId((int) $order->getEntityId());
            $customOrderFees->setCustomFeesOrdered($customFees);

            $order
                ->getExtensionAttributes()
                ?->setCustomOrderFees($customOrderFees);

            $orderRepository->save($order);

            unset($customOrderFees);
        }

        $invoice = $invoiceService->prepareInvoice($order);

        $invoice->register();

        if ($invoiceHasCustomFees && ($invoice->getExtensionAttributes()?->getInvoicedCustomFees() ?? []) === []) {
            $invoice
                ->getExtensionAttributes()
                ?->setInvoicedCustomFees($customFees);
        }

        if (!$invoiceHasCustomFees) {
            $invoice
                ->getExtensionAttributes()
                ?->setInvoicedCustomFees([]);
        }

        $order->setIsInProcess(true);

        $transaction
            ->addObject($invoice)
            ->addObject($order)
            ->save();

        if ($customFeesAlreadyInvoiced) {
            // Save the invoice again to simulate the case where the custom fees have already been invoiced
            $invoice->save();

            $expectedInvoicedCustomFeeCount = 1;
        }

        try {
            $customOrderFees = $customOrderFeesRepository->getByOrderId((int) $order->getEntityId());
        } catch (NoSuchEntityException) {
            /** @var CustomOrderFeesInterface $customOrderFees */
            $customOrderFees = $objectManager->create(CustomOrderFeesInterface::class);
        }

        $actualInvoicedCustomFees = $customOrderFees->getCustomFeesInvoiced();

        self::assertCount($expectedInvoicedCustomFeeCount, $actualInvoicedCustomFees);
    }

    #[DataFixture('JosephLeedy_CustomFees::../test/Integration/_files/order_with_custom_fees.php')]
    public function testLogsErrorIfInvoicedCustomFeesCannotBeSaved(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        /** @var InvoiceService $invoiceService */
        $invoiceService = $objectManager->create(InvoiceService::class);
        $testLogger = new TestLogger();
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
        $customOrderFeesRepositoryStub = $this->createStub(CustomOrderFeesRepositoryInterface::class);
        /** @var Transaction $transaction */
        $transaction = $objectManager->create(Transaction::class);

        $order->loadByIncrementId('100000001');

        $invoice = $invoiceService->prepareInvoice($order);

        $invoice->register();

        $order->setIsInProcess(true);

        $customOrderFeesRepositoryStub
            ->method('save')
            ->willThrowException($couldNotSaveException);

        $objectManager->configure(
            [
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

        $transaction
            ->addObject($invoice)
            ->addObject($order)
            ->save();

        self::assertTrue(
            $testLogger->hasRecord(
                [
                    'message' => __(
                        'Could not save invoiced custom fees. Error: "%1"',
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

    public static function doesMotSaveInvoicedCustomFeesDataProvider(): array
    {
        return [
            'if invoice has no custom fees' => [
                'orderHasCustomFees' => true,
                'invoiceHasCustomFees' => false,
                'customFeesAlreadyInvoiced' => false,
            ],
            'if order has no custom fees' => [
                'orderHasCustomFees' => false,
                'invoiceHasCustomFees' => true,
                'customFeesAlreadyInvoiced' => false,
            ],
            'if custom fees have already been invoiced' => [
                'orderHasCustomFees' => true,
                'invoiceHasCustomFees' => true,
                'customFeesAlreadyInvoiced' => true,
            ],
        ];
    }
}
