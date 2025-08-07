<?php

declare(strict_types=1);

use Magento\Framework\DB\Transaction;
use Magento\Framework\ObjectManagerInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

$resolver = Resolver::getInstance();

$resolver->requireDataFixture('Magento/Sales/_files/default_rollback.php');
$resolver->requireDataFixture('JosephLeedy_CustomFees::../test/Integration/_files/order_with_mixed_custom_fees.php');

/** @var ObjectManagerInterface $objectManager */
$objectManager = Bootstrap::getObjectManager();
/** @var Order $order */
$order = $objectManager->create(Order::class);

$order->loadByIncrementId('100000001');

$orderItems = $order->getAllItems();
/** @var InvoiceService $invoiceService */
$invoiceService = $objectManager->create(InvoiceService::class);
/** @var Transaction $transactionSave */
$transactionSave = $objectManager->create(Transaction::class);

foreach ($orderItems as $orderItem) {
    $quantityOrdered = $orderItem->getQtyOrdered();

    for ($i = 0; $i < $quantityOrdered; $i++) {
        $invoiceQuantity = [
            $orderItem->getItemId() => 1,
        ];
        $invoice = $invoiceService->prepareInvoice($order, $invoiceQuantity)->register();

        $transactionSave->addObject($invoice);
    }
}

$order->setIsInProcess(true);

$transactionSave
    ->addObject($order)
    ->save();
