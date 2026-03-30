<?php

declare(strict_types=1);

use JosephLeedy\CustomFees\Api\CustomOrderFeesRepositoryInterface;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFee\InvoicedInterface;
use Magento\Framework\DB\Transaction;
use Magento\Framework\ObjectManagerInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

$resolver = Resolver::getInstance();

$resolver->requireDataFixture('Magento/Sales/_files/default_rollback.php');
$resolver->requireDataFixture(
    'JosephLeedy_CustomFees::../test/Integration/_files/order_with_custom_fees_discounted_and_taxed.php',
);

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

$transactionSave
    ->addObject($invoice)
    ->addObject($order)
    ->save();

/** @var CustomOrderFeesRepositoryInterface $customOrderFeesRepository */
$customOrderFeesRepository = $objectManager->create(CustomOrderFeesRepositoryInterface::class);
$customOrderFees = $customOrderFeesRepository->getByOrderId($order->getEntityId());
/** @var array<string, InvoicedInterface> $invoicedCustomFees */
$invoicedCustomFees = $invoice->getExtensionAttributes()?->getInvoicedCustomFees() ?? [];

$customOrderFees->setCustomFeesInvoiced(
    [
        (int) $invoice->getEntityId() => $invoicedCustomFees,
    ],
);

$customOrderFeesRepository->save($customOrderFees);
