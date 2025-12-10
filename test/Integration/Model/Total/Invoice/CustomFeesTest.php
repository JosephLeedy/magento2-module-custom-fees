<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Model\Total\Invoice;

use JosephLeedy\CustomFees\Api\ConfigInterface;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFee\InvoicedInterface as InvoicedCustomFee;
use JosephLeedy\CustomFees\Model\Config;
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

use function round;

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
                        'tax_rate' => 0.00,
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
                        'tax_rate' => 0.00,
                    ],
                ],
            ),
        ];
        $actualInvoicedCustomFees = $invoice->getExtensionAttributes()?->getInvoicedCustomFees();

        self::assertEquals(26.50, $invoice->getGrandTotal());
        self::assertEquals($expectedInvoicedCustomFees, $actualInvoicedCustomFees);
    }

    /**
     * @dataProvider customFeeAmountIncludesTaxDataProvider
     * @magentoDataFixture JosephLeedy_CustomFees::../test/Integration/_files/order_with_custom_fees_taxed.php
     */
    public function testCollectsCustomFeesTotalsWithTax(bool $customFeeAmountIncludesTax): void
    {
        $configStub = $this->createStub(ConfigInterface::class);
        /** @var ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        /** @var OrderResource $orderResource */
        $orderResource = $objectManager->create(OrderResource::class);

        $configStub->method('isTaxIncluded')->willReturn($customFeeAmountIncludesTax);

        $objectManager->configure(
            [
                Config::class => [
                    'shared' => true,
                ],
            ],
        );
        $objectManager->addSharedInstance($configStub, Config::class);

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
                        'base_value_with_tax' => 5.30,
                        'value_with_tax' => 5.30,
                        'base_tax_amount' => 0.30,
                        'tax_amount' => 0.30,
                        'tax_rate' => 6.00,
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
                        'base_value_with_tax' => 1.59,
                        'value_with_tax' => 1.59,
                        'base_tax_amount' => 0.09,
                        'tax_amount' => 0.09,
                        'tax_rate' => 6.00,
                    ],
                ],
            ),
        ];
        $actualInvoicedCustomFees = $invoice->getExtensionAttributes()?->getInvoicedCustomFees();

        self::assertEquals(28.09, $invoice->getBaseGrandTotal());
        self::assertEquals(28.09, $invoice->getGrandTotal());
        self::assertEquals(1.59, $invoice->getBaseTaxAmount());
        self::assertEquals(1.59, $invoice->getTaxAmount());
        self::assertEquals($expectedInvoicedCustomFees, $actualInvoicedCustomFees);
    }

    /**
     * @magentoDataFixture JosephLeedy_CustomFees::../test/Integration/_files/order_with_custom_fees_discounted.php
     */
    public function testCollectsCustomFeesTotalsWithDiscounts(): void
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
                        'base_discount_amount' => 0.50,
                        'discount_amount' => 0.50,
                        'discount_rate' => 10.00,
                        'base_value_with_tax' => 5.00,
                        'value_with_tax' => 5.00,
                        'base_tax_amount' => 0.00,
                        'tax_amount' => 0.00,
                        'tax_rate' => 0.00,
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
                        'base_discount_amount' => 0.15,
                        'discount_amount' => 0.15,
                        'discount_rate' => 10.00,
                        'base_value_with_tax' => 1.50,
                        'value_with_tax' => 1.50,
                        'base_tax_amount' => 0.00,
                        'tax_amount' => 0.00,
                        'tax_rate' => 0.00,
                    ],
                ],
            ),
        ];
        $actualInvoicedCustomFees = $invoice->getExtensionAttributes()?->getInvoicedCustomFees();

        self::assertEquals(25.85, $invoice->getBaseGrandTotal());
        self::assertEquals(25.85, $invoice->getGrandTotal());
        self::assertEquals(-0.65, $invoice->getBaseDiscountAmount());
        self::assertEquals(-0.65, $invoice->getDiscountAmount());
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
                            'tax_rate' => 0.00,
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
                            'tax_rate' => 0.00,
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
     * @dataProvider customFeeAmountIncludesTaxDataProvider
     * @magentoDataFixture JosephLeedy_CustomFees::../test/Integration/_files/order_with_custom_fees_taxed.php
     */
    public function testCollectsCustomFeesTotalsWithTaxForMultipleInvoices(bool $customFeeAmountIncludesTax): void
    {
        $configStub = $this->createStub(ConfigInterface::class);
        /** @var ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        /** @var OrderResource $orderResource */
        $orderResource = $objectManager->create(OrderResource::class);

        $configStub->method('isTaxIncluded')->willReturn($customFeeAmountIncludesTax);

        $objectManager->configure(
            [
                Config::class => [
                    'shared' => true,
                ],
            ],
        );
        $objectManager->addSharedInstance($configStub, Config::class);

        $orderResource->load($order, '100000001', 'increment_id');

        $invoices = $this->createInvoices($order);

        foreach ($invoices as $index => $invoice) {
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
                            'base_value_with_tax' => 2.65,
                            'value_with_tax' => 2.65,
                            'base_tax_amount' => 0.15,
                            'tax_amount' => 0.15,
                            'tax_rate' => 6.00,
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
                            'base_value_with_tax' => 0.80,
                            'value_with_tax' => 0.80,
                            'base_tax_amount' => 0.05,
                            'tax_amount' => 0.05,
                            'tax_rate' => 6.00,
                        ],
                    ],
                ),
            ];
            $actualInvoicedCustomFees = $invoice->getExtensionAttributes()?->getInvoicedCustomFees();

            self::assertEquals(round(14.05 - ($index / 100), 2), $invoice->getBaseGrandTotal());
            self::assertEquals(round(14.05 - ($index / 100), 2), $invoice->getGrandTotal());
            self::assertEquals(round(0.80 - ($index / 100), 2), $invoice->getBaseTaxAmount());
            self::assertEquals(round(0.80 - ($index / 100), 2), $invoice->getTaxAmount());
            self::assertEquals($expectedInvoicedCustomFees, $actualInvoicedCustomFees);
        }

        self::assertEquals(28.09, $order->getTotalPaid());
    }

    /**
     * @magentoDataFixture JosephLeedy_CustomFees::../test/Integration/_files/order_with_custom_fees_discounted.php
     */
    public function testCollectsCustomFeesTotalsWithDiscountsForMultipleInvoices(): void
    {
        /** @var ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        /** @var OrderResource $orderResource */
        $orderResource = $objectManager->create(OrderResource::class);

        $orderResource->load($order, '100000001', 'increment_id');

        $invoices = $this->createInvoices($order);

        foreach ($invoices as $index => $invoice) {
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
                            'base_discount_amount' => 0.25,
                            'discount_amount' => 0.25,
                            'discount_rate' => 10.00,
                            'base_value_with_tax' => 2.50,
                            'value_with_tax' => 2.50,
                            'base_tax_amount' => 0.00,
                            'tax_amount' => 0.00,
                            'tax_rate' => 0.00,
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
                            'base_discount_amount' => 0.08,
                            'discount_amount' => 0.08,
                            'discount_rate' => 10.00,
                            'base_value_with_tax' => 0.75,
                            'value_with_tax' => 0.75,
                            'base_tax_amount' => 0.00,
                            'tax_amount' => 0.00,
                            'tax_rate' => 0.00,
                        ],
                    ],
                ),
            ];
            $actualInvoicedCustomFees = $invoice->getExtensionAttributes()?->getInvoicedCustomFees();

            self::assertEquals($expectedInvoicedCustomFees, $actualInvoicedCustomFees);
        }

        self::assertEquals(25.85, $order->getTotalPaid());
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
     * @return array<string, array<string, bool>>
     */
    public static function customFeeAmountIncludesTaxDataProvider(): array
    {
        return [
            'custom fee amount excludes tax' => [
                'customFeeAmountIncludesTax' => false,
            ],
            'custom fee amount includes tax' => [
                'customFeeAmountIncludesTax' => true,
            ],
        ];
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
