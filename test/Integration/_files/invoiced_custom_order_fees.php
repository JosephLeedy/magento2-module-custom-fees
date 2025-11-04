<?php

declare(strict_types=1);

use JosephLeedy\CustomFees\Api\Data\CustomOrderFee\InvoicedInterface as InvoicedCustomFee;
use JosephLeedy\CustomFees\Model\FeeType;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\DB\Transaction;
use Magento\Framework\ObjectManagerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

$resolver = Resolver::getInstance();

$resolver->requireDataFixture(
    'JosephLeedy_CustomFees::../test/Integration/_files/order_list_with_invoice_and_custom_fees.php',
);

/** @var ObjectManagerInterface $objectManager */
$objectManager = Bootstrap::getObjectManager();
/** @var SearchCriteriaBuilder $searchCriteriaBuilder */
$searchCriteriaBuilder = $objectManager->create(SearchCriteriaBuilder::class);
$orderSearchResults = $searchCriteriaBuilder
    ->addFilter(
        'increment_id',
        [
            '100000001',
            '100000002',
            '100000003',
            '100000004',
            '100000005',
            '100000006',
        ],
        'in',
    )->create();
/** @var OrderRepositoryInterface $orderRepository */
$orderRepository = $objectManager->create(OrderRepositoryInterface::class);
$orderCollection = $orderRepository->getList($orderSearchResults);
/** @var Transaction $transaction */
$transaction = $objectManager->create(Transaction::class);

$orderCollection->walk(
    static function (Order $order) use ($objectManager, $transaction): void {
        $customOrderFees = $order->getExtensionAttributes()?->getCustomOrderFees();
        $invoicedCustomFees = [];

        if ($customOrderFees === null) {
            return;
        }

        $order
            ->getInvoiceCollection()
            ->walk(
                static function (Invoice $invoice) use (&$invoicedCustomFees, $objectManager): void {
                    $invoiceId = (int) $invoice->getEntityId();
                    $invoicedCustomFees[$invoiceId] = [
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
                                    'invoice_id' => $invoiceId,
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
                },
            );

        $customOrderFees->setCustomFeesInvoiced($invoicedCustomFees);

        $transaction->addObject($customOrderFees);
        $transaction->save();
    },
);
