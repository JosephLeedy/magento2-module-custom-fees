<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Model\Total\Creditmemo;

use JosephLeedy\CustomFees\Api\ConfigInterface;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFee\RefundedInterface as RefundedCustomFee;
use JosephLeedy\CustomFees\Model\Config;
use JosephLeedy\CustomFees\Model\FeeType;
use Magento\Framework\ObjectManagerInterface;
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
                        'base_value_with_tax' => 5.30,
                        'value_with_tax' => 5.30,
                        'base_tax_amount' => 0.30,
                        'tax_amount' => 0.30,
                        'tax_rate' => 6.00,
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
                        'base_value_with_tax' => 1.59,
                        'value_with_tax' => 1.59,
                        'base_tax_amount' => 0.09,
                        'tax_amount' => 0.09,
                        'tax_rate' => 6.00,
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
                            'base_value_with_tax' => 2.65,
                            'value_with_tax' => 2.65,
                            'base_tax_amount' => 0.15,
                            'tax_amount' => 0.15,
                            'tax_rate' => 6.00,
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
                            'base_value_with_tax' => 0.80,
                            'value_with_tax' => 0.80,
                            'base_tax_amount' => 0.05,
                            'tax_amount' => 0.05,
                            'tax_rate' => 6.00,
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
