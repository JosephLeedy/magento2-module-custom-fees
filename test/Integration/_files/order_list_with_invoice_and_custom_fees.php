<?php

declare(strict_types=1);

use JosephLeedy\CustomFees\Model\CustomOrderFees;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\DB\Transaction;
use Magento\Framework\Stdlib\DateTime;
use Magento\Sales\Api\Data\OrderInterfaceFactory;
use Magento\Sales\Api\InvoiceManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\OrderFactory;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

Resolver::getInstance()
    ->requireDataFixture('JosephLeedy_CustomFees::../test/Integration/_files/order_with_custom_fees.php');

$objectManager = Bootstrap::getObjectManager();
/** @var SearchCriteriaBuilder $searchCriteriaBuilder */
$searchCriteriaBuilder = $objectManager->create(SearchCriteriaBuilder::class);
$orderSearchCriteria = $searchCriteriaBuilder->addFilter('increment_id', '100000001')->create();
/** @var OrderRepositoryInterface $orderRepository */
$orderRepository = $objectManager->get(OrderRepositoryInterface::class);
$orderSearchResults = $orderRepository->getList($orderSearchCriteria);
$orders = $orderSearchResults->getItems();
/** @var Order $order */
$order = reset($orders);
$items = $order->getItems();
$orderItem = reset($items);
$billingAddress = $order->getBillingAddress();
$shippingAddress = $order->getShippingAddress();
$customOrderFees = $order->getExtensionAttributes()->getCustomOrderFees();
$payment = $order->getPayment();
/** @var OrderFactory $orderFactory */
$orderFactory = $objectManager->get(OrderInterfaceFactory::class);
/** @var InvoiceManagementInterface $invoiceManagement */
$invoiceManagement = $objectManager->get(InvoiceManagementInterface::class);
/** @var Transaction $transaction */
$transaction = $objectManager->get(Transaction::class);
$dateTime = new DateTimeImmutable();
$ordersData = [
    [
        'increment_id' => '100000002',
        'state' => Order::STATE_PROCESSING,
        'status' => 'processing',
        'base_to_global_rate' => 1,
        'base_grand_total' => 120.00,
        'grand_total' => 120.00,
        'base_subtotal' => 120.00,
        'subtotal' => 120.00,
        'created_at' => $dateTime->modify('-1 hour')->format(DateTime::DATETIME_PHP_FORMAT),
    ],
    [
        'increment_id' => '100000003',
        'state' => Order::STATE_PROCESSING,
        'status' => 'processing',
        'base_to_global_rate' => 1,
        'base_grand_total' => 130.00,
        'grand_total' => 130.00,
        'base_subtotal' => 130.00,
        'subtotal' => 130.00,
        'created_at' => max($dateTime->modify('-1 day'), $dateTime->modify('first day of this month'))
            ->format(DateTime::DATETIME_PHP_FORMAT),
    ],
    [
        'increment_id' => '100000004',
        'state' => Order::STATE_PROCESSING,
        'status' => 'processing',
        'base_to_global_rate' => 1,
        'base_grand_total' => 140.00,
        'grand_total' => 140.00,
        'base_subtotal' => 140.00,
        'subtotal' => 140.00,
        'created_at' => $dateTime->modify('first day of this month')->format(DateTime::DATETIME_PHP_FORMAT),
    ],
    [
        'increment_id' => '100000005',
        'state' => Order::STATE_PROCESSING,
        'status' => 'processing',
        'base_to_global_rate' => 1,
        'base_grand_total' => 150.00,
        'grand_total' => 150.00,
        'base_subtotal' => 150.00,
        'subtotal' => 150.00,
        'created_at' => $dateTime->modify('first day of january this year')->format(DateTime::DATETIME_PHP_FORMAT),
    ],
    [
        'increment_id' => '100000006',
        'state' => Order::STATE_PROCESSING,
        'status' => 'processing',
        'base_to_global_rate' => 1,
        'base_grand_total' => 160.00,
        'grand_total' => 160.00,
        'base_subtotal' => 160.00,
        'subtotal' => 160.00,
        'created_at' => $dateTime->modify('first day of january last year')->format(DateTime::DATETIME_PHP_FORMAT),
    ],
];

// Fix first order not invoiced
/** @var Invoice $invoice */
$invoice = $invoiceManagement->prepareInvoice($order);

$invoice->register();

$order->setIsInProcess(true);

$transaction
    ->addObject($order)
    ->addObject($invoice)
    ->save();
// end fix

foreach ($ordersData as $orderData) {
    // Fix item not being invoiced for order
    $newOrderItem = clone $orderItem;

    $newOrderItem->setItemId(null);
    $newOrderItem->setOrderId(null);
    $newOrderItem->setQtyInvoiced(0.0);
    $newOrderItem->setQtyToInvoice($newOrderItem->getQtyOrdered());
    $newOrderItem->setBaseRowInvoiced(0.0);
    $newOrderItem->setRowInvoiced(0.0);
    $newOrderItem->setBaseTaxInvoiced(0.0);
    $newOrderItem->setTaxInvoiced(0.0);
    $newOrderItem->setBaseDiscountInvoiced(0.0);
    $newOrderItem->setDiscountInvoiced(0.0);
    $newOrderItem->setBasePrice((float) ($orderData['base_subtotal'] / $newOrderItem->getQtyOrdered()));
    $newOrderItem->setPrice((float) ($orderData['subtotal'] / $newOrderItem->getQtyOrdered()));
    $newOrderItem->setBaseRowTotal((float) $orderData['base_subtotal']);
    $newOrderItem->setRowTotal((float) $orderData['subtotal']);
    // end fix

    /** @var Order $newOrder */
    $newOrder = $orderFactory->create();

    $newOrder
        ->setData($orderData)
        ->addItem($newOrderItem)
        ->setCustomerIsGuest(true)
        ->setCustomerEmail('customer@example.com')
        ->setBillingAddress(clone $billingAddress)
        ->setShippingAddress(clone $shippingAddress);

    // Fix payment not being saved for order
    /** @var Payment $newPayment */
    $newPayment = clone $payment;

    $newPayment->setEntityId(null);
    $newPayment->setOrder($newOrder);

    $newOrder->setPayment($newPayment);
    // end fix

    /** @var CustomOrderFees $newCustomOrderFees */
    $newCustomOrderFees = clone $customOrderFees;

    $newCustomOrderFees->setId(null);
    $newCustomOrderFees->setData('order_entity_id', null);

    $newOrder->getExtensionAttributes()->setCustomOrderFees($newCustomOrderFees);

    $orderRepository->save($newOrder);

    /** @var Invoice $newInvoice */
    $newInvoice = $invoiceManagement->prepareInvoice($newOrder);

    $newInvoice->register();

    $newOrder->setIsInProcess(true);

    $transaction
        ->addObject($newOrder)
        ->addObject($newInvoice)
        ->save();
}
