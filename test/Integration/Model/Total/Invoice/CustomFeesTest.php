<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Model\Total\Invoice;

use Magento\Framework\DB\Transaction;
use Magento\Framework\ObjectManagerInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Item;
use Magento\Sales\Model\ResourceModel\Order as OrderResource;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

final class CustomFeesTest extends TestCase
{
    /**
     * @magentoDataFixture JosephLeedy_CustomFees::../test/Integration/_files/order_with_custom_fees.php
     */
    public function testCollectsCustomFeesTotals(): void
    {
        /** @var ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        /** @var OrderResource $orderResource */
        $orderResource = $objectManager->create(OrderResource::class);

        $orderResource->load($order, '100000001', 'increment_id');

        $invoice = $this->createInvoice($order);

        $expectedInvoicedCustomFees = [
            '_1727299833817_817' => [
                'code' => 'test_fee_0',
                'title' => 'Test Fee',
                'type' => 'fixed',
                'percent' => null,
                'show_percentage' => false,
                'base_value' => 5.00,
                'value' => 5.00,
            ],
            '_1727299843197_197' => [
                'code' => 'test_fee_1',
                'title' => 'Another Test Fee',
                'type' => 'fixed',
                'percent' => null,
                'show_percentage' => false,
                'base_value' => 1.50,
                'value' => 1.50,
            ],
        ];
        $actualInvoicedCustomFees = $invoice->getExtensionAttributes()?->getInvoicedCustomFees();

        self::assertEquals(26.50, $invoice->getGrandTotal());
        self::assertEquals($expectedInvoicedCustomFees, $actualInvoicedCustomFees);
    }

    /**
     * @magentoDataFixture JosephLeedy_CustomFees::../test/Integration/_files/order_with_custom_fees.php
     */
    public function testCollectsCustomFeesTotalsForMultipleInvoices(): void
    {
        /** @var ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        /** @var OrderResource $orderResource */
        $orderResource = $objectManager->create(OrderResource::class);

        $orderResource->load($order, '100000001', 'increment_id');

        $invoices = $this->createInvoices($order);

        $expectedInvoicedCustomFees = [
            '_1727299833817_817' => [
                'code' => 'test_fee_0',
                'title' => 'Test Fee',
                'type' => 'fixed',
                'percent' => null,
                'show_percentage' => false,
                'base_value' => 2.50,
                'value' => 2.50,
            ],
            '_1727299843197_197' => [
                'code' => 'test_fee_1',
                'title' => 'Another Test Fee',
                'type' => 'fixed',
                'percent' => null,
                'show_percentage' => false,
                'base_value' => 0.75,
                'value' => 0.75,
            ],
        ];

        foreach ($invoices as $invoice) {
            self::assertEquals(13.25, $invoice->getBaseGrandTotal());
            self::assertEquals(13.25, $invoice->getGrandTotal());
            self::assertEquals(
                $expectedInvoicedCustomFees,
                $invoice->getExtensionAttributes()?->getInvoicedCustomFees(),
            );
        }

        self::assertEquals(26.50, $order->getTotalPaid());
    }

    /**
     * @magentoDataFixture JosephLeedy_CustomFees::../test/Integration/_files/order.php
     */
    public function testDoesNotCollectCustomFeesTotals(): void
    {
        /** @var ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        /** @var OrderResource $orderResource */
        $orderResource = $objectManager->create(OrderResource::class);

        $orderResource->load($order, '100000001', 'increment_id');

        $invoice = $this->createInvoice($order);

        self::assertEquals(20.00, $invoice->getBaseGrandTotal());
        self::assertEquals(20.00, $invoice->getGrandTotal());
        self::assertEmpty($invoice->getExtensionAttributes()?->getInvoicedCustomFees());
    }

    /**
     * @param array<int, int> $orderItemQuantitiesToInvoice
     */
    private function createInvoice(Order $order, array $orderItemQuantitiesToInvoice = []): Invoice
    {
        /** @var ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var InvoiceService $invoiceService */
        $invoiceService = $objectManager->create(InvoiceService::class);
        $invoice = $invoiceService->prepareInvoice($order, $orderItemQuantitiesToInvoice);
        /** @var Transaction $transaction */
        $transaction = $objectManager->create(Transaction::class);

        $invoice->register();

        $order->setIsInProcess(true);

        $transaction
            ->addObject($invoice)
            ->addObject($order)
            ->save();

        return $invoice;
    }

    /**
     * @return Invoice[]
     */
    private function createInvoices(Order $order): array
    {
        $invoices = [];

        $order
            ->getItemsCollection()
            ->walk(
                function (Item $orderItem) use ($order, &$invoices): void {
                    $quantityOrdered = $orderItem->getQtyOrdered();

                    for ($i = 0; $i < $quantityOrdered; $i++) {
                        $invoiceQuantity = [
                            (int) $orderItem->getItemId() => 1,
                        ];

                        $invoices[] = $this->createInvoice($order, $invoiceQuantity);
                    }
                },
            );

        return $invoices;
    }
}
