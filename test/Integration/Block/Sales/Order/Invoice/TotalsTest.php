<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Block\Sales\Order\Invoice;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use JosephLeedy\CustomFees\Block\Sales\Order\Invoice\Totals as CustomInvoiceFeesTotalsBlock;
use JosephLeedy\CustomFees\Service\CustomFeesRetriever;
use Magento\Framework\App\Area;
use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\DB\Transaction;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Block\Order\Invoice\Totals as InvoiceTotalsBlock;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[AppArea(Area::AREA_ADMINHTML)]
final class TotalsTest extends TestCase
{
    use ArraySubsetAsserts;

    #[DataFixture('JosephLeedy_CustomFees::../test/Integration/_files/order_with_custom_fees.php')]
    public function testInitializesInvoicedCustomFeeTotals(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        /** @var InvoiceTotalsBlock $invoiceTotalsBlock */
        $invoiceTotalsBlock = $objectManager->create(InvoiceTotalsBlock::class);
        $customInvoiceFeesTotalsBlock = $this
            ->getMockBuilder(CustomInvoiceFeesTotalsBlock::class)
            ->setConstructorArgs(
                [
                    'context' => $objectManager->get(Context::class),
                    'customFeesRetriever' => $objectManager->create(CustomFeesRetriever::class),
                    'dataObjectFactory' => $objectManager->get(DataObjectFactory::class),
                    'data' => [],
                ],
            )->onlyMethods(
                [
                    'getParentBlock',
                ],
            )->getMock();

        $order->loadByIncrementId('100000001');

        $invoice = $this->createInvoice($order);

        $customInvoiceFeesTotalsBlock
            ->method('getParentBlock')
            ->willReturn($invoiceTotalsBlock);

        $invoiceTotalsBlock->setOrder($order);
        $invoiceTotalsBlock->setInvoice($invoice);
        $invoiceTotalsBlock->toHtml();

        $customInvoiceFeesTotalsBlock->initTotals();

        $expectedInvoiceTotals = [
            'test_fee_0' => $objectManager->create(
                DataObject::class,
                [
                    'data' => [
                        'code' => 'test_fee_0',
                        'label' => __('Test Fee'),
                        'base_value' => 5.00,
                        'value' => 5.00,
                    ],
                ],
            ),
            'test_fee_1' => $objectManager->create(
                DataObject::class,
                [
                    'data' => [
                        'code' => 'test_fee_1',
                        'label' => __('Another Test Fee'),
                        'base_value' => 1.50,
                        'value' => 1.50,
                    ],
                ],
            ),
        ];
        $actualInvoiceTotals = $invoiceTotalsBlock->getTotals();

        self::assertArraySubset($expectedInvoiceTotals, $actualInvoiceTotals);
    }

    #[DataFixture('JosephLeedy_CustomFees::../test/Integration/_files/order.php')]
    public function testDoesNotInitializeInvoicedCustomFeeTotalsIfInvoiceDoesNotHaveCustomFees(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        /** @var InvoiceTotalsBlock $invoiceTotalsBlock */
        $invoiceTotalsBlock = $objectManager->create(InvoiceTotalsBlock::class);
        $customInvoiceFeesTotalsBlock = $this
            ->getMockBuilder(CustomInvoiceFeesTotalsBlock::class)
            ->setConstructorArgs(
                [
                    'context' => $objectManager->get(Context::class),
                    'customFeesRetriever' => $objectManager->create(CustomFeesRetriever::class),
                    'dataObjectFactory' => $objectManager->get(DataObjectFactory::class),
                    'data' => [],
                ],
            )->onlyMethods(
                [
                    'getParentBlock',
                ],
            )->getMock();

        $order->loadByIncrementId('100000001');

        $invoice = $this->createInvoice($order);

        $customInvoiceFeesTotalsBlock
            ->method('getParentBlock')
            ->willReturn($invoiceTotalsBlock);

        $invoiceTotalsBlock->setOrder($order);
        $invoiceTotalsBlock->setInvoice($invoice);
        $invoiceTotalsBlock->toHtml();

        $customInvoiceFeesTotalsBlock->initTotals();

        $actualInvoiceTotals = $invoiceTotalsBlock->getTotals();

        self::assertArrayNotHasKey('test_fee_0', $actualInvoiceTotals);
        self::assertArrayNotHasKey('test_fee_1', $actualInvoiceTotals);
    }

    private function createInvoice(Order $order): Invoice
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var InvoiceService $invoiceService */
        $invoiceService = $objectManager->create(InvoiceService::class);
        /** @var Transaction $transaction */
        $transaction = $objectManager->create(Transaction::class);

        $invoice = $invoiceService->prepareInvoice($order);

        $invoice->register();

        $order->setIsInProcess(true);

        $transaction
            ->addObject($invoice)
            ->addObject($order)
            ->save();

        return $invoice;
    }
}
