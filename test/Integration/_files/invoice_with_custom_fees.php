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
$resolver->requireDataFixture('JosephLeedy_CustomFees::Test/Integration/_files/order_with_custom_fees.php');

/** @var ObjectManagerInterface $objectManager */
$objectManager = Bootstrap::getObjectManager();
/** @var Order $order */
$order = $objectManager->create(Order::class);

$order->loadByIncrementId('100000001');

/** @var InvoiceService $invoiceService */
$invoiceService = $objectManager->create(InvoiceService::class);
$invoice = $invoiceService->prepareInvoice($order);

$invoice->register();

$order = $invoice->getOrder();

$order->setIsInProcess(true);

/** @var Transaction $transactionSave */
$transactionSave = $objectManager->create(Transaction::class);

$transactionSave->addObject($invoice)
    ->addObject($order)
    ->save();
