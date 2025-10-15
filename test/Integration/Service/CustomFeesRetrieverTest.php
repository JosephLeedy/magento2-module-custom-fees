<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Service;

use JosephLeedy\CustomFees\Api\Data\CustomOrderFee\InvoicedInterface as InvoicedCustomFee;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFee\RefundedInterface as RefundedCustomFee;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFeeInterface;
use JosephLeedy\CustomFees\Model\FeeType;
use JosephLeedy\CustomFees\Service\CustomFeesRetriever;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\DB\Transaction;
use Magento\Framework\ObjectManagerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\ResourceModel\Order as OrderResource;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

use function current;

final class CustomFeesRetrieverTest extends TestCase
{
    /**
     * @dataProvider retrievesCustomOrderFeesDataProvider
     * @magentoDataFixture JosephLeedy_CustomFees::../test/Integration/_files/order_with_custom_fees.php
     */
    public function testRetrievesCustomFeesForOrder(string $source): void
    {
        /** @var ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var CustomFeesRetriever $customFeesRetriever */
        $customFeesRetriever = $objectManager->create(CustomFeesRetriever::class);

        if ($source === 'order_extension') {
            /** @var SearchCriteriaBuilder $searchCriteriaBuilder */
            $searchCriteriaBuilder = $objectManager->create(SearchCriteriaBuilder::class);
            /** @var SearchCriteriaInterface $searchCriteria */
            $searchCriteria = $searchCriteriaBuilder->addFilter('increment_id', '100000001')
                ->create();
            /** @var OrderRepositoryInterface $orderRepository */
            $orderRepository = $objectManager->create(OrderRepositoryInterface::class);
            $orders = $orderRepository->getList($searchCriteria)
                ->getItems();
            /** @var Order $order */
            $order = current($orders);
        } else {
            /** @var Order $order */
            $order = $objectManager->create(Order::class);
            /** @var OrderResource $orderResource */
            $orderResource = $objectManager->create(OrderResource::class);

            $orderResource->load($order, '100000001', 'increment_id');
        }

        $expectedCustomFees = [
            'test_fee_0' => $objectManager->create(
                CustomOrderFeeInterface::class,
                [
                    'data' => [
                        'code' => 'test_fee_0',
                        'title' => 'Test Fee',
                        'type' => FeeType::Fixed,
                        'percent' => null,
                        'show_percentage' => false,
                        'base_value' => 5.00,
                        'value' => 5.00,
                    ],
                ],
            ),
            'test_fee_1' => $objectManager->create(
                CustomOrderFeeInterface::class,
                [
                    'data' => [
                        'code' => 'test_fee_1',
                        'title' => 'Another Test Fee',
                        'type' => FeeType::Fixed,
                        'percent' => null,
                        'show_percentage' => false,
                        'base_value' => 1.50,
                        'value' => 1.50,
                    ],
                ],
            ),
        ];
        $actualCustomFees = $customFeesRetriever->retrieveOrderedCustomFees($order);

        self::assertEquals($expectedCustomFees, $actualCustomFees);
    }

    /**
     * @dataProvider doesNotRetrieveCustomOrderFeesDataProvider
     * @magentoDataFixture Magento/Sales/_files/order.php
     */
    public function testDoesNotRetrieveCustomFeesForOrder(string $condition): void
    {
        /** @var ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var CustomFeesRetriever $customFeesRetriever */
        $customFeesRetriever = $objectManager->create(CustomFeesRetriever::class);

        if ($condition === 'order_extension_null') {
            $order = $this->createPartialMock(Order::class, ['getExtensionAttributes']);

            $order->method('getExtensionAttributes')
                ->willReturn(null);
        } else {
            /** @var Order $order */
            $order = $objectManager->create(Order::class);
            /** @var OrderResource $orderResource */
            $orderResource = $objectManager->create(OrderResource::class);

            $orderResource->load($order, '100000001', 'increment_id');
        }

        $customFees = $customFeesRetriever->retrieveOrderedCustomFees($order);

        self::assertEmpty($customFees);
    }

    /**
     * @magentoAppArea adminhtml
     * @magentoDataFixture JosephLeedy_CustomFees::../test/Integration/_files/order_with_custom_fees.php
     */
    public function testRetrievesCustomFeesForInvoice(): void
    {
        /** @var ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        /** @var InvoiceService $invoiceService */
        $invoiceService = $objectManager->create(InvoiceService::class);
        /** @var Transaction $transaction */
        $transaction = $objectManager->create(Transaction::class);
        /** @var CustomFeesRetriever $customFeesRetriever */
        $customFeesRetriever = $objectManager->create(CustomFeesRetriever::class);

        $order->loadByIncrementId('100000001');

        /* We need to create the invoice here rather than using the `invoice_with_custom_fees` fixture because the
           fixture ignores the area set by the `magentoAppArea` annotation, causing the plug-in that saves the invoiced
           custom fees to not execute. */
        $invoice = $invoiceService->prepareInvoice($order);

        $invoice->register();

        $transaction
            ->addObject($invoice)
            ->addObject($order)
            ->save();

        $invoiceId = (int) $invoice->getEntityId();

        $expectedCustomFees = [
            $invoiceId => [
                'test_fee_0' => $objectManager->create(
                    InvoicedCustomFee::class,
                    [
                        'data' => [
                            'invoice_id' => $invoiceId,
                            'code' => 'test_fee_0',
                            'title' => 'Test Fee',
                            'type' => FeeType::Fixed,
                            'percent' => null,
                            'show_percentage' => false,
                            'base_value' => 5.00,
                            'value' => 5.00,
                        ],
                    ],
                ),
                'test_fee_1' => $objectManager->create(
                    InvoicedCustomFee::class,
                    [
                        'data' => [
                            'invoice_id' => $invoiceId,
                            'code' => 'test_fee_1',
                            'title' => 'Another Test Fee',
                            'type' => FeeType::Fixed,
                            'percent' => null,
                            'show_percentage' => false,
                            'base_value' => 1.50,
                            'value' => 1.50,
                        ],
                    ],
                ),
            ],
        ];
        $actualCustomFees = $customFeesRetriever->retrieveInvoicedCustomFees($order);

        self::assertEquals($expectedCustomFees, $actualCustomFees);
    }

    /**
     * @magentoDataFixture JosephLeedy_CustomFees::../test/Integration/_files/invoice.php
     */
    public function testDoesNotRetrieveCustomFeesForInvoiceIfNoneExist(): void
    {
        /** @var ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        /** @var CustomFeesRetriever $customFeesRetriever */
        $customFeesRetriever = $objectManager->create(CustomFeesRetriever::class);

        $order->loadByIncrementId('100000001');

        self::assertEmpty($customFeesRetriever->retrieveInvoicedCustomFees($order));
    }

    /**
     * @magentoDataFixture JosephLeedy_CustomFees::../test/Integration/_files/creditmemo_with_partially_refunded_custom_fees.php
     */
    public function testRetrievesCustomFeesForCreditMemo(): void
    {
        /** @var ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        /** @var CustomFeesRetriever $customFeesRetriever */
        $customFeesRetriever = $objectManager->create(CustomFeesRetriever::class);

        $order->loadByIncrementId('100000001');

        /** @var Creditmemo $creditMemo */
        $creditMemo = $order->getCreditmemosCollection()->getFirstItem();
        $creditMemoId = $creditMemo->getEntityId();

        $expectedCustomFees = [
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
                        ],
                    ],
                ),
            ],
        ];
        $actualCustomFees = $customFeesRetriever->retrieveRefundedCustomFees($order);

        self::assertEquals($expectedCustomFees, $actualCustomFees);
    }

    /**
     * @magentoDataFixture JosephLeedy_CustomFees::../test/Integration/_files/creditmemo.php
     */
    public function testDoesNotRetrieveCustomFeesForCreditMemoIfNoneExist(): void
    {
        /** @var ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        /** @var CustomFeesRetriever $customFeesRetriever */
        $customFeesRetriever = $objectManager->create(CustomFeesRetriever::class);

        $order->loadByIncrementId('100000001');

        self::assertEmpty($customFeesRetriever->retrieveRefundedCustomFees($order));
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function retrievesCustomOrderFeesDataProvider(): array
    {
        return [
            'from extension attribute' => [
                'source' => 'order_extension',
            ],
            'from custom order fees database table' => [
                'source' => 'custom_order_fees_table',
            ],
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function doesNotRetrieveCustomOrderFeesDataProvider(): array
    {
        return [
            'extension attribute not instantiated' => [
                'condition' => 'order_extension_null',
            ],
            'no custom fees for order' => [
                'source' => 'no_custom_order_fees',
            ],
        ];
    }
}
