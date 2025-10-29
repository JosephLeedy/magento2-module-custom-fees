<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Model\Total\Invoice;

use JosephLeedy\CustomFees\Api\Data\CustomOrderFee\InvoicedInterface as InvoicedCustomFee;
use JosephLeedy\CustomFees\Model\FeeType;
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
            'test_fee_0' => $objectManager->create(
                InvoicedCustomFee::class,
                [
                    'data' => [
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
                InvoicedCustomFee::class,
                [
                    'data' => [
                        'code' => 'test_fee_1',
                        'title' => 'Another Test Fee',
                        'type' => FeeType::Fixed,
                        'percent' => null,
                        'show_percentage' => false,
                        'base_value' => 1.50,
                        'value' => 1.50,
                        'base_value_with_tax' => 1.50,
                        'value_with_tax' => 1.50,
                        'base_tax_amount' => 0.00,
                        'tax_amount' => 0.00,
                    ],
                ],
            ),
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

        foreach ($invoices as $invoice) {
            $expectedInvoicedCustomFees = [
                'test_fee_0' => $objectManager->create(
                    InvoicedCustomFee::class,
                    [
                        'data' => [
                            'code' => 'test_fee_0',
                            'title' => 'Test Fee',
                            'type' => FeeType::Fixed,
                            'percent' => null,
                            'show_percentage' => false,
                            'base_value' => 2.50,
                            'value' => 2.50,
                            'base_value_with_tax' => 2.50,
                            'value_with_tax' => 2.50,
                            'base_tax_amount' => 0.00,
                            'tax_amount' => 0.00,
                        ],
                    ],
                ),
                'test_fee_1' => $objectManager->create(
                    InvoicedCustomFee::class,
                    [
                        'data' => [
                            'code' => 'test_fee_1',
                            'title' => 'Another Test Fee',
                            'type' => FeeType::Fixed,
                            'percent' => null,
                            'show_percentage' => false,
                            'base_value' => 0.75,
                            'value' => 0.75,
                            'base_value_with_tax' => 0.75,
                            'value_with_tax' => 0.75,
                            'base_tax_amount' => 0.00,
                            'tax_amount' => 0.00,
                        ],
                    ],
                ),
            ];

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
