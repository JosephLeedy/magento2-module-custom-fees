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
$resolver->requireDataFixture('JosephLeedy_CustomFees::Test/Integration/_files/invoice_with_custom_fees.php');

$objectManager = Bootstrap::getObjectManager();
/** @var Order $order */
$order = $objectManager->create(Order::class);

$order->loadByIncrementId('100000001');

/** @var CreditmemoFactory $creditmemoFactory */
$creditmemoFactory = $objectManager->create(CreditmemoFactory::class);
$creditmemo = $creditmemoFactory->createByOrder($order, $order->getData());
/** @var CreditmemoManagementInterface $creditmemoManagement */
$creditmemoManagement = $objectManager->create(CreditmemoManagementInterface::class);

/** @var Invoice $invoice */
$invoice = $order->getInvoiceCollection()->getFirstItem();

$creditmemo->setInvoice($invoice);

$creditmemoManagement->refund($creditmemo);
