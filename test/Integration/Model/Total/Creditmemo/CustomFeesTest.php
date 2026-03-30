<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Model\Total\Creditmemo;

use JosephLeedy\CustomFees\Api\ConfigInterface;
use JosephLeedy\CustomFees\Api\CustomOrderFeesRepositoryInterface;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFee\RefundedInterface as RefundedCustomFee;
use JosephLeedy\CustomFees\Model\Config;
use JosephLeedy\CustomFees\Model\FeeType;
use Magento\Framework\ObjectManagerInterface;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\CreditmemoFactory;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Invoice\Item;
use Magento\Sales\Model\ResourceModel\Order as OrderResource;
use Magento\Sales\Model\ResourceModel\Order\Creditmemo\Collection as CreditmemoCollection;
use Magento\Sales\Model\Service\CreditmemoService;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

use function round;

final class CustomFeesTest extends TestCase
{
    /**
     * @magentoDataFixture JosephLeedy_CustomFees::../test/Integration/_files/creditmemo_with_custom_fees.php
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

        /** @var CreditmemoCollection $creditmemosCollection */
        $creditmemosCollection = $order->getCreditmemosCollection()
            ?: $objectManager->create(CreditmemoCollection::class);

        /** @var Creditmemo $creditmemo */
        $creditmemo = $creditmemosCollection->getFirstItem();

        self::assertEquals(26.50, $creditmemo->getBaseGrandTotal());
        self::assertEquals(26.50, $creditmemo->getGrandTotal());
    }

    /**
     * @dataProvider customFeeAmountIncludesTaxDataProvider
     * @magentoDataFixture JosephLeedy_CustomFees::../test/Integration/_files/invoice_with_custom_fees_taxed.php
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

        /** @var Invoice $invoice */
        $invoice = $order->getInvoiceCollection()->getFirstItem();
        $creditmemo = $this->createCreditMemo($invoice);

        $expectedRefundedCustomFees = [
            'test_fee_0' => $objectManager->create(
                RefundedCustomFee::class,
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
                RefundedCustomFee::class,
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
        $actualRefundedCustomFees = $creditmemo->getExtensionAttributes()?->getRefundedCustomFees();

        self::assertEquals(28.09, $creditmemo->getBaseGrandTotal());
        self::assertEquals(28.09, $creditmemo->getGrandTotal());
        self::assertEquals(1.59, $creditmemo->getBaseTaxAmount());
        self::assertEquals(1.59, $creditmemo->getTaxAmount());
        self::assertEquals($expectedRefundedCustomFees, $actualRefundedCustomFees);
    }

    /**
     * @magentoDataFixture JosephLeedy_CustomFees::../test/Integration/_files/invoice_with_custom_fees_discounted.php
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

        /** @var Invoice $invoice */
        $invoice = $order->getInvoiceCollection()->getFirstItem();
        $creditmemo = $this->createCreditMemo($invoice);

        $expectedRefundedCustomFees = [
            'test_fee_0' => $objectManager->create(
                RefundedCustomFee::class,
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
                RefundedCustomFee::class,
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
        $actualRefundedCustomFees = $creditmemo->getExtensionAttributes()?->getRefundedCustomFees();

        self::assertEquals(25.85, $creditmemo->getBaseGrandTotal());
        self::assertEquals(25.85, $creditmemo->getGrandTotal());
        self::assertEquals(-0.65, $creditmemo->getBaseDiscountAmount());
        self::assertEquals(-0.65, $creditmemo->getDiscountAmount());
        self::assertEquals($expectedRefundedCustomFees, $actualRefundedCustomFees);
    }

    /**
     * @dataProvider customFeeAmountIncludesTaxDataProvider
     * @magentoDataFixture JosephLeedy_CustomFees::../test/Integration/_files/invoice_with_custom_fees_discounted_and_taxed.php
     */
    public function testCollectsCustomFeesTotalsWithDiscountsAndTaxes(bool $customFeeAmountIncludesTax): void
    {
        $configStub = $this->createStub(ConfigInterface::class);
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

        /** @var Invoice $invoice */
        $invoice = $order->getInvoiceCollection()->getFirstItem();

        if ($customFeeAmountIncludesTax) {
            /** @var CustomOrderFeesRepositoryInterface $customOrderFeesRepository */
            $customOrderFeesRepository = $objectManager->create(CustomOrderFeesRepositoryInterface::class);
            $customOrderFees = $customOrderFeesRepository->getByOrderId((int) $order->getEntityId());
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

            $invoice->setBaseDiscountTaxCompensationAmount(0.03);
            $invoice->setDiscountTaxCompensationAmount(0.03);
        }

        $creditmemo = $this->createCreditMemo($invoice);

        $expectedRefundedBaseGrandTotal = !$customFeeAmountIncludesTax ? 27.88 : 27.56;
        $expectedRefundedGrandTotal = !$customFeeAmountIncludesTax ? 27.88 : 27.56;
        $expectedRefundedBaseDiscountAmount = !$customFeeAmountIncludesTax ? -0.70 : -0.66;
        $expectedRefundedDiscountAmount = !$customFeeAmountIncludesTax ? -0.70 : -0.66;
        $expectedRefundedBaseTaxAmount = 1.58;
        $expectedRefundedTaxAmount = 1.58;
        $expectedRefundedBaseDiscountTaxCompensationAmount = !$customFeeAmountIncludesTax ? 0.00 : 0.03;
        $expectedRefundedDiscountTaxCompensationAmount = !$customFeeAmountIncludesTax ? 0.00 : 0.03;
        $expectedRefundedCustomFees = [
            'test_fee_0' => $objectManager->create(
                RefundedCustomFee::class,
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
                RefundedCustomFee::class,
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
        $actualRefundedBaseGrandTotal = $creditmemo->getBaseGrandTotal();
        $actualRefundedGrandTotal = $creditmemo->getGrandTotal();
        $actualRefundedBaseDiscountAmount = $creditmemo->getBaseDiscountAmount();
        $actualRefundedDiscountAmount = $creditmemo->getDiscountAmount();
        $actualRefundedBaseTaxAmount = $creditmemo->getBaseTaxAmount();
        $actualRefundedTaxAmount = $creditmemo->getTaxAmount();
        $actualRefundedBaseDiscountTaxCompensationAmount = $creditmemo->getBaseDiscountTaxCompensationAmount();
        $actualRefundedDiscountTaxCompensationAmount = $creditmemo->getDiscountTaxCompensationAmount();
        $actualRefundedCustomFees = $creditmemo->getExtensionAttributes()?->getRefundedCustomFees();

        self::assertEquals($expectedRefundedBaseGrandTotal, $actualRefundedBaseGrandTotal);
        self::assertEquals($expectedRefundedGrandTotal, $actualRefundedGrandTotal);
        self::assertEqualsWithDelta($expectedRefundedBaseDiscountAmount, $actualRefundedBaseDiscountAmount, 0.001);
        self::assertEqualsWithDelta($expectedRefundedDiscountAmount, $actualRefundedDiscountAmount, 0.001);
        self::assertEquals($expectedRefundedBaseTaxAmount, $actualRefundedBaseTaxAmount);
        self::assertEquals($expectedRefundedTaxAmount, $actualRefundedTaxAmount);
        self::assertEquals(
            $expectedRefundedBaseDiscountTaxCompensationAmount,
            $actualRefundedBaseDiscountTaxCompensationAmount,
        );
        self::assertEquals(
            $expectedRefundedDiscountTaxCompensationAmount,
            $actualRefundedDiscountTaxCompensationAmount,
        );
        self::assertEquals($expectedRefundedCustomFees, $actualRefundedCustomFees);
    }

    /**
     * @magentoDataFixture JosephLeedy_CustomFees::../test/Integration/_files/creditmemos_with_custom_fees.php
     */
    public function testCollectsCustomFeesTotalsForMultipleCreditMemos(): void
    {
        /** @var ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        /** @var OrderResource $orderResource */
        $orderResource = $objectManager->create(OrderResource::class);

        $orderResource->load($order, '100000001', 'increment_id');

        /** @var Creditmemo[] $creditmemos */
        $creditmemos = $order->getCreditmemosCollection()->getItems();

        foreach ($creditmemos as $creditmemo) {
            self::assertEquals(13.50, $creditmemo->getBaseGrandTotal());
            self::assertEquals(13.50, $creditmemo->getGrandTotal());
        }

        self::assertEquals(27.00, $order->getTotalRefunded());
    }

    /**
     * @dataProvider customFeeAmountIncludesTaxDataProvider
     * @magentoDataFixture JosephLeedy_CustomFees::../test/Integration/_files/invoice_with_custom_fees_taxed.php
     */
    public function testCollectsCustomFeesTotalsWithTaxForMultipleCreditMemos(bool $customFeeAmountIncludesTax): void
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

        /** @var Invoice $invoice */
        $invoice = $order->getInvoiceCollection()->getFirstItem();
        $creditmemos = $this->createCreditMemos($invoice);

        foreach ($creditmemos as $index => $creditmemo) {
            $expectedRefundedCustomFees = [
                'test_fee_0' => $objectManager->create(
                    RefundedCustomFee::class,
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
                    RefundedCustomFee::class,
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
                            'base_value_with_tax' => 0.80 - ($index / 100),
                            'value_with_tax' => 0.80 - ($index / 100),
                            'base_tax_amount' => 0.05 - ($index / 100),
                            'tax_amount' => 0.05 - ($index / 100),
                            'tax_rate' => 6.00,
                            'base_discount_tax_compensation' => 0.00,
                            'discount_tax_compensation' => 0.00,
                        ],
                    ],
                ),
            ];
            $actualRefundedCustomFees = $creditmemo->getExtensionAttributes()?->getRefundedCustomFees();

            self::assertEquals(round(14.05 - ($index / 100), 2), $creditmemo->getBaseGrandTotal());
            self::assertEquals(round(14.05 - ($index / 100), 2), $creditmemo->getGrandTotal());
            self::assertEquals(round(0.80 - ($index / 100), 2), $creditmemo->getBaseTaxAmount());
            self::assertEquals(round(0.80 - ($index / 100), 2), $creditmemo->getTaxAmount());
            self::assertEquals($expectedRefundedCustomFees, $actualRefundedCustomFees);
        }

        self::assertEquals(28.09, $order->getTotalRefunded());
    }

    /**
     * @magentoDataFixture JosephLeedy_CustomFees::../test/Integration/_files/invoice_with_custom_fees_discounted.php
     */
    public function testCollectsCustomFeesTotalsWithDiscountsForMultipleCreditMemos(): void
    {
        /** @var ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        /** @var OrderResource $orderResource */
        $orderResource = $objectManager->create(OrderResource::class);

        $orderResource->load($order, '100000001', 'increment_id');

        /** @var Invoice $invoice */
        $invoice = $order->getInvoiceCollection()->getFirstItem();
        $creditmemos = $this->createCreditMemos($invoice);

        foreach ($creditmemos as $index => $creditmemo) {
            $expectedRefundedCustomFees = [
                'test_fee_0' => $objectManager->create(
                    RefundedCustomFee::class,
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
                    RefundedCustomFee::class,
                    [
                        'data' => [
                            'code' => 'test_fee_1',
                            'title' => 'Another Test Fee',
                            'type' => FeeType::Fixed,
                            'percent' => null,
                            'show_percentage' => false,
                            'base_value' => 0.75,
                            'value' => 0.75,
                            'base_discount_amount' => 0.08 - ($index / 100),
                            'discount_amount' => 0.08 - ($index / 100),
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
            $actualRefundedCustomFees = $creditmemo->getExtensionAttributes()?->getRefundedCustomFees();

            self::assertEquals($expectedRefundedCustomFees, $actualRefundedCustomFees);
            self::assertEquals(12.92 + ($index / 100), $creditmemo->getBaseGrandTotal());
            self::assertEquals(12.92 + ($index / 100), $creditmemo->getGrandTotal());
            self::assertEquals(-0.33 + ($index / 100), $creditmemo->getBaseDiscountAmount());
            self::assertEquals(-0.33 + ($index / 100), $creditmemo->getDiscountAmount());
        }

        self::assertEquals(25.85, $order->getTotalRefunded());
    }

    /**
     * @dataProvider customFeeAmountIncludesTaxDataProvider
     * @magentoDataFixture JosephLeedy_CustomFees::../test/Integration/_files/invoice_with_custom_fees_discounted_and_taxed.php
     */
    public function testCollectsCustomFeesTotalsWithDiscountsAndTaxesForMultipleCreditMemos(
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

        /** @var Invoice $invoice */
        $invoice = $order->getInvoiceCollection()->getFirstItem();

        if ($customFeeAmountIncludesTax) {
            /** @var CustomOrderFeesRepositoryInterface $customOrderFeesRepository */
            $customOrderFeesRepository = $objectManager->create(CustomOrderFeesRepositoryInterface::class);
            $customOrderFees = $customOrderFeesRepository->getByOrderId((int) $order->getEntityId());
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

            $invoice->setBaseDiscountTaxCompensationAmount(0.03);
            $invoice->setDiscountTaxCompensationAmount(0.03);
        }

        $creditmemos = $this->createCreditMemos($invoice);

        foreach ($creditmemos as $index => $creditmemo) {
            $expectedRefundedCustomFees = [
                'test_fee_0' => $objectManager->create(
                    RefundedCustomFee::class,
                    [
                        'data' => [
                            'code' => 'test_fee_0',
                            'title' => 'Test Fee',
                            'type' => FeeType::Fixed,
                            'percent' => null,
                            'show_percentage' => false,
                            'base_value' => !$customFeeAmountIncludesTax ? 2.50 : 2.36,
                            'value' => !$customFeeAmountIncludesTax ? 2.50 : 2.36,
                            'base_discount_amount' => !$customFeeAmountIncludesTax
                                ? 0.25 : round(0.24 - ($index / 100), 2),
                            'discount_amount' => !$customFeeAmountIncludesTax
                                ? 0.25 : round(0.24 - ($index / 100), 2),
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
                    RefundedCustomFee::class,
                    [
                        'data' => [
                            'code' => 'test_fee_1',
                            'title' => 'Another Test Fee',
                            'type' => FeeType::Percent,
                            'percent' => 10.0,
                            'show_percentage' => false,
                            'base_value' => !$customFeeAmountIncludesTax ? 1.00 : 0.94,
                            'value' => !$customFeeAmountIncludesTax ? 1.00 : 0.94,
                            'base_discount_amount' => !$customFeeAmountIncludesTax
                                ? 0.10 : round(0.10 - ($index / 100), 2),
                            'discount_amount' => !$customFeeAmountIncludesTax
                                ? 0.10 : round(0.10 - ($index / 100), 2),
                            'discount_rate' => 10.0,
                            'base_value_with_tax' => !$customFeeAmountIncludesTax ? 1.06 : 1.00,
                            'value_with_tax' => !$customFeeAmountIncludesTax ? 1.06 : 1.00,
                            'base_tax_amount' => !$customFeeAmountIncludesTax ? 0.06 : round(0.05 + ($index / 100), 2),
                            'tax_amount' => !$customFeeAmountIncludesTax ? 0.06 : round(0.05 + ($index / 100), 2),
                            'tax_rate' => 6.0,
                            'base_discount_tax_compensation' => !$customFeeAmountIncludesTax
                                ? 0.00 : round(0.01 - ($index / 100), 2),
                            'discount_tax_compensation' => !$customFeeAmountIncludesTax
                                ? 0.00 : round(0.01 - ($index / 100), 2),
                        ],
                    ],
                ),
            ];
            $actualRefundedCustomFees = $creditmemo->getExtensionAttributes()?->getRefundedCustomFees();

            self::assertEquals($expectedRefundedCustomFees, $actualRefundedCustomFees);
        }

        self::assertEquals(!$customFeeAmountIncludesTax ? 27.88 : 27.55, $order->getTotalRefunded());
    }

    /**
     * @magentoAppArea adminhtml
     * @magentoDataFixture JosephLeedy_CustomFees::../test/Integration/_files/creditmemo_with_partially_refunded_custom_fees.php
     */
    public function testCollectsPartiallyRefundedCustomFeesTotals(): void
    {
        /** @var ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        /** @var OrderResource $orderResource */
        $orderResource = $objectManager->create(OrderResource::class);

        $orderResource->load($order, '100000001', 'increment_id');

        /** @var CreditmemoCollection $creditmemosCollection */
        $creditmemosCollection = $order->getCreditmemosCollection()
            ?: $objectManager->create(CreditmemoCollection::class);

        /** @var Creditmemo $creditmemo */
        $creditmemo = $creditmemosCollection->getFirstItem();

        self::assertEquals(25.00, $creditmemo->getBaseGrandTotal());
        self::assertEquals(25.00, $creditmemo->getGrandTotal());
    }

    /**
     * @magentoDataFixture JosephLeedy_CustomFees::../test/Integration/_files/creditmemo.php
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

        /** @var CreditmemoCollection $creditmemosCollection */
        $creditmemosCollection = $order->getCreditmemosCollection()
            ?: $objectManager->create(CreditmemoCollection::class);

        /** @var Creditmemo $creditmemo */
        $creditmemo = $creditmemosCollection->getFirstItem();

        self::assertEquals(20, $creditmemo->getGrandTotal());
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
     * @param array{qtys: array<int, int>} $orderItemQuantitiesToCreditMemo
     */
    private function createCreditMemo(Invoice $invoice, array $orderItemQuantitiesToCreditMemo = []): Creditmemo
    {
        /** @var ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var CreditmemoFactory $creditmemoFactory */
        $creditmemoFactory = $objectManager->create(CreditmemoFactory::class);
        $creditmemo = $creditmemoFactory->createByInvoice($invoice, $orderItemQuantitiesToCreditMemo);
        /** @var CreditmemoService $creditMemoService */
        $creditMemoService = $objectManager->create(CreditmemoService::class);
        /** @var Creditmemo $creditMemo */
        $creditMemo = $creditMemoService->refund($creditmemo);
        /** @var CreditmemoRepositoryInterface $creditmemoRepository */
        $creditmemoRepository = $objectManager->create(CreditmemoRepositoryInterface::class);
        /** @var CustomOrderFeesRepositoryInterface $customOrderFeesRepository */
        $customOrderFeesRepository = $objectManager->create(CustomOrderFeesRepositoryInterface::class);
        $customOrderFees = $customOrderFeesRepository->getByOrderId($creditMemo->getOrderId());

        $creditmemoRepository->save($creditMemo);

        $customOrderFees->setCustomFeesRefunded(
            [
                $creditMemo->getEntityId() => $creditMemo->getExtensionAttributes()?->getRefundedCustomFees() ?? [],
            ] + $customOrderFees->getCustomFeesRefunded(),
        );

        $customOrderFeesRepository->save($customOrderFees);

        return $creditMemo;
    }

    /**
     * @return Creditmemo[]
     */
    private function createCreditMemos(Invoice $invoice): array
    {
        $creditMemos = [];

        $invoice
            ->getItemsCollection()
            ->walk(
                function (Item $invoiceItem) use ($invoice, &$creditMemos): void {
                    $quantityInvoiced = $invoiceItem->getQty();

                    for ($i = 0; $i < $quantityInvoiced; $i++) {
                        $creditMemoQuantity = [
                            (int) $invoiceItem->getOrderItemId() => 1,
                        ];

                        $creditMemos[] = $this->createCreditmemo($invoice, ['qtys' => $creditMemoQuantity]);
                    }
                },
            );

        return $creditMemos;
    }
}
