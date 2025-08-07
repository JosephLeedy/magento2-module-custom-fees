<?php

declare(strict_types=1);

use Magento\Sales\Api\CreditmemoManagementInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\CreditmemoFactory;
use Magento\Sales\Model\Order\Invoice;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

$resolver = Resolver::getInstance();

$resolver->requireDataFixture('Magento/Sales/_files/default_rollback.php');
$resolver->requireDataFixture('JosephLeedy_CustomFees::../test/Integration/_files/invoices_with_custom_fees.php');

$objectManager = Bootstrap::getObjectManager();
/** @var Order $order */
$order = $objectManager->create(Order::class);

$order->loadByIncrementId('100000001');

/** @var Invoice[] $invoices */
$invoices = $order->getInvoiceCollection()->getItems();
/** @var CreditmemoFactory $creditmemoFactory */
$creditmemoFactory = $objectManager->create(CreditmemoFactory::class);
/** @var CreditmemoManagementInterface $creditmemoManagement */
$creditmemoManagement = $objectManager->create(CreditmemoManagementInterface::class);

foreach ($invoices as $invoice) {
    $creditmemo = $creditmemoFactory->createByInvoice($invoice);

    $creditmemoManagement->refund($creditmemo);
}
