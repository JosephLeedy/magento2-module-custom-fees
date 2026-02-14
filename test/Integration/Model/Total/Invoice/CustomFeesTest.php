<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Model\Total\Invoice;

use JosephLeedy\CustomFees\Api\ConfigInterface;
use JosephLeedy\CustomFees\Api\CustomOrderFeesRepositoryInterface;
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
                        'base_discount_amount' => 0.00,
                        'discount_amount' => 0.00,
                        'discount_rate' => 0.00,
                        'base_value_with_tax' => 5.00,
                        'value_with_tax' => 5.00,
                        'base_tax_amount' => 0.00,
                        'tax_amount' => 0.00,
                        'tax_rate' => 0.00,
                        'base_discount_tax_compensation' => 0.00,
                        'discount_tax_compensation' => 0.00,
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
                        'base_discount_amount' => 0.00,
                        'discount_amount' => 0.00,
                        'discount_rate' => 0.00,
                        'base_value_with_tax' => 1.50,
                        'value_with_tax' => 1.50,
                        'base_tax_amount' => 0.00,
                        'tax_amount' => 0.00,
                        'tax_rate' => 0.00,
                        'base_discount_tax_compensation' => 0.00,
                        'discount_tax_compensation' => 0.00,
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
                        'base_discount_amount' => 0.00,
                        'discount_amount' => 0.00,
                        'discount_rate' => 0.00,
                        'base_value_with_tax' => 5.30,
                        'value_with_tax' => 5.30,
                        'base_tax_amount' => 0.30,
                        'tax_amount' => 0.30,
                        'tax_rate' => 6.00,
                        'base_discount_tax_compensation' => 0.00,
                        'discount_tax_compensation' => 0.00,
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
                        'base_discount_amount' => 0.00,
                        'discount_amount' => 0.00,
                        'discount_rate' => 0.00,
                        'base_value_with_tax' => 1.59,
                        'value_with_tax' => 1.59,
                        'base_tax_amount' => 0.09,
                        'tax_amount' => 0.09,
                        'tax_rate' => 6.00,
                        'base_discount_tax_compensation' => 0.00,
                        'discount_tax_compensation' => 0.00,
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
                        'base_discount_tax_compensation' => 0.00,
                        'discount_tax_compensation' => 0.00,
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
                        'base_discount_tax_compensation' => 0.00,
                        'discount_tax_compensation' => 0.00,
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
     * @dataProvider customFeeAmountIncludesTaxDataProvider
     * @magentoDataFixture JosephLeedy_CustomFees::../test/Integration/_files/order_with_custom_fees_discounted_and_taxed.php
     */
    public function testCollectsCustomFeesTotalsWithDiscountsAndTaxes(bool $customFeeAmountIncludesTax): void
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

        if ($customFeeAmountIncludesTax) {
            /** @var CustomOrderFeesRepositoryInterface $customOrderFeesRepository */
            $customOrderFeesRepository = $objectManager->create(CustomOrderFeesRepositoryInterface::class);
            $customOrderFees = $customOrderFeesRepository->getByOrderId($order->getEntityId());
            $orderedCustomFees = $customOrderFees->getCustomFeesOrdered();

            $orderedCustomFees['test_fee_0']->setBaseValue(4.72);
            $orderedCustomFees['test_fee_0']->setValue(4.72);
            $orderedCustomFees['test_fee_0']->setBaseDiscountAmount(0.47);
            $orderedCustomFees['test_fee_0']->setDiscountAmount(0.47);
            $orderedCustomFees['test_fee_0']->setBaseValueWithTax(5.00);
            $orderedCustomFees['test_fee_0']->setValueWithTax(5.00);
            $orderedCustomFees['test_fee_0']->setBaseTaxAmount(0.28);
            $orderedCustomFees['test_fee_0']->setTaxAmount(0.28);
            $orderedCustomFees['test_fee_0']->setBaseDiscountTaxCompensation(0.02);
            $orderedCustomFees['test_fee_0']->setDiscountTaxCompensation(0.02);

            $orderedCustomFees['test_fee_1']->setBaseValue(1.88);
            $orderedCustomFees['test_fee_1']->setValue(1.88);
            $orderedCustomFees['test_fee_1']->setBaseDiscountAmount(0.19);
            $orderedCustomFees['test_fee_1']->setDiscountAmount(0.19);
            $orderedCustomFees['test_fee_1']->setBaseValueWithTax(2.00);
            $orderedCustomFees['test_fee_1']->setValueWithTax(2.00);
            $orderedCustomFees['test_fee_1']->setBaseTaxAmount(0.12);
            $orderedCustomFees['test_fee_1']->setTaxAmount(0.12);
            $orderedCustomFees['test_fee_1']->setBaseDiscountTaxCompensation(0.01);
            $orderedCustomFees['test_fee_1']->setDiscountTaxCompensation(0.01);

            $customOrderFees->setCustomFeesOrdered($orderedCustomFees);

            $customOrderFeesRepository->save($customOrderFees);
        }

        $invoice = $this->createInvoice($order);

        $expectedInvoicedBaseGrandTotal = !$customFeeAmountIncludesTax ? 27.88 : 27.56;
        $expectedInvoicedGrandTotal = !$customFeeAmountIncludesTax ? 27.88 : 27.56;
        $expectedInvoicedBaseDiscountAmount = !$customFeeAmountIncludesTax ? -0.70 : -0.66;
        $expectedInvoicedDiscountAmount = !$customFeeAmountIncludesTax ? -0.70 : -0.66;
        $expectedInvoicedBaseTaxAmount = 1.58;
        $expectedInvoicedTaxAmount = 1.58;
        $expectedInvoicedBaseDiscountTaxCompensationAmount = !$customFeeAmountIncludesTax ? 0.00 : 0.03;
        $expectedInvoicedDiscountTaxCompensationAmount = !$customFeeAmountIncludesTax ? 0.00 : 0.03;
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
                        'base_value' => !$customFeeAmountIncludesTax ? 5.00 : 4.72,
                        'value' => !$customFeeAmountIncludesTax ? 5.00 : 4.72,
                        'base_discount_amount' => !$customFeeAmountIncludesTax ? 0.50 : 0.47,
                        'discount_amount' => !$customFeeAmountIncludesTax ? 0.50 : 0.47,
                        'discount_rate' => 10.0,
                        'base_value_with_tax' => !$customFeeAmountIncludesTax ? 5.30 : 5.00,
                        'value_with_tax' => !$customFeeAmountIncludesTax ? 5.30 : 5.00,
                        'base_tax_amount' => !$customFeeAmountIncludesTax ? 0.30 : 0.26,
                        'tax_amount' => !$customFeeAmountIncludesTax ? 0.30 : 0.26,
                        'tax_rate' => 6.0,
                        'base_discount_tax_compensation' => !$customFeeAmountIncludesTax ? 0.00 : 0.02,
                        'discount_tax_compensation' => !$customFeeAmountIncludesTax ? 0.00 : 0.02,
                    ],
                ],
            ),
            'test_fee_1' => $objectManager->create(
                InvoicedCustomFee::class,
                [
                    'data' => [
                        'code' => 'test_fee_1',
                        'title' => 'Another Test Fee',
                        'type' => FeeType::Percent,
                        'percent' => 10.0,
                        'show_percentage' => false,
                        'base_value' => !$customFeeAmountIncludesTax ? 2.00 : 1.89,
                        'value' => !$customFeeAmountIncludesTax ? 2.00 : 1.89,
                        'base_discount_amount' => !$customFeeAmountIncludesTax ? 0.20 : 0.19,
                        'discount_amount' => !$customFeeAmountIncludesTax ? 0.20 : 0.19,
                        'discount_rate' => 10.0,
                        'base_value_with_tax' => !$customFeeAmountIncludesTax ? 2.12 : 2.00,
                        'value_with_tax' => !$customFeeAmountIncludesTax ? 2.12 : 2.00,
                        'base_tax_amount' => !$customFeeAmountIncludesTax ? 0.12 : 0.10,
                        'tax_amount' => !$customFeeAmountIncludesTax ? 0.12 : 0.10,
                        'tax_rate' => 6.0,
                        'base_discount_tax_compensation' => !$customFeeAmountIncludesTax ? 0.00 : 0.01,
                        'discount_tax_compensation' => !$customFeeAmountIncludesTax ? 0.00 : 0.01,
                    ],
                ],
            ),
        ];
        $actualInvoicedBaseGrandTotal = $invoice->getBaseGrandTotal();
        $actualInvoicedGrandTotal = $invoice->getGrandTotal();
        $actualInvoicedBaseDiscountAmount = $invoice->getBaseDiscountAmount();
        $actualInvoicedDiscountAmount = $invoice->getDiscountAmount();
        $actualInvoicedBaseTaxAmount = $invoice->getBaseTaxAmount();
        $actualInvoicedTaxAmount = $invoice->getTaxAmount();
        $actualInvoicedBaseDiscountTaxCompensationAmount = $invoice->getBaseDiscountTaxCompensationAmount();
        $actualInvoicedDiscountTaxCompensationAmount = $invoice->getDiscountTaxCompensationAmount();
        $actualInvoicedCustomFees = $invoice->getExtensionAttributes()?->getInvoicedCustomFees();

        self::assertEquals($expectedInvoicedBaseGrandTotal, $actualInvoicedBaseGrandTotal);
        self::assertEquals($expectedInvoicedGrandTotal, $actualInvoicedGrandTotal);
        self::assertEqualsWithDelta($expectedInvoicedBaseDiscountAmount, $actualInvoicedBaseDiscountAmount, 0.001);
        self::assertEqualsWithDelta($expectedInvoicedDiscountAmount, $actualInvoicedDiscountAmount, 0.001);
        self::assertEquals($expectedInvoicedBaseTaxAmount, $actualInvoicedBaseTaxAmount);
        self::assertEquals($expectedInvoicedTaxAmount, $actualInvoicedTaxAmount);
        self::assertEquals(
            $expectedInvoicedBaseDiscountTaxCompensationAmount,
            $actualInvoicedBaseDiscountTaxCompensationAmount,
        );
        self::assertEquals(
            $expectedInvoicedDiscountTaxCompensationAmount,
            $actualInvoicedDiscountTaxCompensationAmount,
        );
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
                            'base_discount_amount' => 0.00,
                            'discount_amount' => 0.00,
                            'discount_rate' => 0.00,
                            'base_value_with_tax' => 2.50,
                            'value_with_tax' => 2.50,
                            'base_tax_amount' => 0.00,
                            'tax_amount' => 0.00,
                            'tax_rate' => 0.00,
                            'base_discount_tax_compensation' => 0.00,
                            'discount_tax_compensation' => 0.00,
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
                            'base_discount_amount' => 0.00,
                            'discount_amount' => 0.00,
                            'discount_rate' => 0.00,
                            'base_value_with_tax' => 0.75,
                            'value_with_tax' => 0.75,
                            'base_tax_amount' => 0.00,
                            'tax_amount' => 0.00,
                            'tax_rate' => 0.00,
                            'base_discount_tax_compensation' => 0.00,
                            'discount_tax_compensation' => 0.00,
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
                            'base_discount_amount' => 0.00,
                            'discount_amount' => 0.00,
                            'discount_rate' => 0.00,
                            'base_value_with_tax' => 2.65,
                            'value_with_tax' => 2.65,
                            'base_tax_amount' => 0.15,
                            'tax_amount' => 0.15,
                            'tax_rate' => 6.00,
                            'base_discount_tax_compensation' => 0.00,
                            'discount_tax_compensation' => 0.00,
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
                            'base_discount_amount' => 0.00,
                            'discount_amount' => 0.00,
                            'discount_rate' => 0.00,
                            'base_value_with_tax' => 0.80,
                            'value_with_tax' => 0.80,
                            'base_tax_amount' => 0.05,
                            'tax_amount' => 0.05,
                            'tax_rate' => 6.00,
                            'base_discount_tax_compensation' => 0.00,
                            'discount_tax_compensation' => 0.00,
                        ],
                    ],
                ),
            ];
            $actualInvoicedCustomFees = $invoice->getExtensionAttributes()?->getInvoicedCustomFees();

            self::assertEquals(14.05, round($invoice->getBaseGrandTotal(), 2));
            self::assertEquals(14.05, round($invoice->getGrandTotal(), 2));
            self::assertEquals(0.80, round($invoice->getBaseTaxAmount(), 2));
            self::assertEquals(0.80, round($invoice->getTaxAmount(), 2));
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
                            'base_discount_tax_compensation' => 0.00,
                            'discount_tax_compensation' => 0.00,
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
                            'base_discount_tax_compensation' => 0.00,
                            'discount_tax_compensation' => 0.00,
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
     * @dataProvider customFeeAmountIncludesTaxDataProvider
     * @magentoDataFixture JosephLeedy_CustomFees::../test/Integration/_files/order_with_custom_fees_discounted_and_taxed.php
     */
    public function testCollectsCustomFeesTotalsWithDiscountsAndTaxesForMultipleInvoices(
        bool $customFeeAmountIncludesTax,
    ): void {
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

        if ($customFeeAmountIncludesTax) {
            /** @var CustomOrderFeesRepositoryInterface $customOrderFeesRepository */
            $customOrderFeesRepository = $objectManager->create(CustomOrderFeesRepositoryInterface::class);
            $customOrderFees = $customOrderFeesRepository->getByOrderId($order->getEntityId());
            $orderedCustomFees = $customOrderFees->getCustomFeesOrdered();

            $orderedCustomFees['test_fee_0']->setBaseValue(4.72);
            $orderedCustomFees['test_fee_0']->setValue(4.72);
            $orderedCustomFees['test_fee_0']->setBaseDiscountAmount(0.47);
            $orderedCustomFees['test_fee_0']->setDiscountAmount(0.47);
            $orderedCustomFees['test_fee_0']->setBaseValueWithTax(5.00);
            $orderedCustomFees['test_fee_0']->setValueWithTax(5.00);
            $orderedCustomFees['test_fee_0']->setBaseTaxAmount(0.28);
            $orderedCustomFees['test_fee_0']->setTaxAmount(0.28);
            $orderedCustomFees['test_fee_0']->setBaseDiscountTaxCompensation(0.02);
            $orderedCustomFees['test_fee_0']->setDiscountTaxCompensation(0.02);

            $orderedCustomFees['test_fee_1']->setBaseValue(1.88);
            $orderedCustomFees['test_fee_1']->setValue(1.88);
            $orderedCustomFees['test_fee_1']->setBaseDiscountAmount(0.19);
            $orderedCustomFees['test_fee_1']->setDiscountAmount(0.19);
            $orderedCustomFees['test_fee_1']->setBaseValueWithTax(2.00);
            $orderedCustomFees['test_fee_1']->setValueWithTax(2.00);
            $orderedCustomFees['test_fee_1']->setBaseTaxAmount(0.12);
            $orderedCustomFees['test_fee_1']->setTaxAmount(0.12);
            $orderedCustomFees['test_fee_1']->setBaseDiscountTaxCompensation(0.01);
            $orderedCustomFees['test_fee_1']->setDiscountTaxCompensation(0.01);

            $customOrderFees->setCustomFeesOrdered($orderedCustomFees);

            $customOrderFeesRepository->save($customOrderFees);
        }

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
                            'base_value' => !$customFeeAmountIncludesTax ? 2.50 : 2.36,
                            'value' => !$customFeeAmountIncludesTax ? 2.50 : 2.36,
                            'base_discount_amount' => !$customFeeAmountIncludesTax ? 0.25 : 0.24,
                            'discount_amount' => !$customFeeAmountIncludesTax ? 0.25 : 0.24,
                            'discount_rate' => 10.0,
                            'base_value_with_tax' => !$customFeeAmountIncludesTax ? 2.65 : 2.50,
                            'value_with_tax' => !$customFeeAmountIncludesTax ? 2.65 : 2.50,
                            'base_tax_amount' => !$customFeeAmountIncludesTax ? 0.15 : 0.13,
                            'tax_amount' => !$customFeeAmountIncludesTax ? 0.15 : 0.13,
                            'tax_rate' => 6.0,
                            'base_discount_tax_compensation' => !$customFeeAmountIncludesTax ? 0.00 : 0.01,
                            'discount_tax_compensation' => !$customFeeAmountIncludesTax ? 0.00 : 0.01,
                        ],
                    ],
                ),
                'test_fee_1' => $objectManager->create(
                    InvoicedCustomFee::class,
                    [
                        'data' => [
                            'code' => 'test_fee_1',
                            'title' => 'Another Test Fee',
                            'type' => FeeType::Percent,
                            'percent' => 10.0,
                            'show_percentage' => false,
                            'base_value' => !$customFeeAmountIncludesTax ? 1.00 : 0.94,
                            'value' => !$customFeeAmountIncludesTax ? 1.00 : 0.94,
                            'base_discount_amount' => 0.10,
                            'discount_amount' => 0.10,
                            'discount_rate' => 10.0,
                            'base_value_with_tax' => !$customFeeAmountIncludesTax ? 1.06 : 1.00,
                            'value_with_tax' => !$customFeeAmountIncludesTax ? 1.06 : 1.00,
                            'base_tax_amount' => !$customFeeAmountIncludesTax ? 0.06 : 0.05,
                            'tax_amount' => !$customFeeAmountIncludesTax ? 0.06 : 0.05,
                            'tax_rate' => 6.0,
                            'base_discount_tax_compensation' => !$customFeeAmountIncludesTax ? 0.00 : 0.01,
                            'discount_tax_compensation' => !$customFeeAmountIncludesTax ? 0.00 : 0.01,
                        ],
                    ],
                ),
            ];
            $actualInvoicedCustomFees = $invoice->getExtensionAttributes()?->getInvoicedCustomFees();

            self::assertEquals($expectedInvoicedCustomFees, $actualInvoicedCustomFees);
        }

        self::assertEqualsWithDelta(!$customFeeAmountIncludesTax ? 27.88 : 27.56, $order->getTotalPaid(), 0.01);
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
