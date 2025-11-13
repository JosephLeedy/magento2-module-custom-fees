<?php

declare(strict_types=1);

use JosephLeedy\CustomFees\Api\CustomOrderFeesRepositoryInterface;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFee\RefundedInterface as RefundedCustomFee;
use Magento\Sales\Api\CreditmemoManagementInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\CreditmemoFactory;
use Magento\Sales\Model\Order\Invoice;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

$resolver = Resolver::getInstance();

$resolver->requireDataFixture('Magento/Sales/_files/default_rollback.php');
$resolver->requireDataFixture('JosephLeedy_CustomFees::../test/Integration/_files/invoice_with_custom_fees_taxed.php');

$objectManager = Bootstrap::getObjectManager();
/** @var Order $order */
$order = $objectManager->create(Order::class);

$order->loadByIncrementId('100000001');

/** @var CreditmemoFactory $creditmemoFactory */
$creditmemoFactory = $objectManager->create(CreditmemoFactory::class);
/** @var CreditmemoManagementInterface $creditmemoManagement */
$creditmemoManagement = $objectManager->create(CreditmemoManagementInterface::class);
/** @var CustomOrderFeesRepositoryInterface $customOrderFeesRepository */
$customOrderFeesRepository = $objectManager->create(CustomOrderFeesRepositoryInterface::class);

/** @var Invoice $invoice */
$invoice = $order->getInvoiceCollection()->getFirstItem();

$creditmemo = $creditmemoFactory->createByInvoice($invoice);

$creditmemoManagement->refund($creditmemo);

/** @var RefundedCustomFee[] $refundedCustomFees */
$refundedCustomFees = $creditmemo->getExtensionAttributes()->getRefundedCustomFees();
$customOrderFees = $customOrderFeesRepository->getByOrderId($order->getEntityId());

array_walk(
    $refundedCustomFees,
    static function (RefundedCustomFee $refundedCustomFee) use ($creditmemo): void {
        $refundedCustomFee->setCreditMemoId((int) $creditmemo->getEntityId());
    },
);

$customOrderFees->setCustomFeesRefunded([$creditmemo->getEntityId() => $refundedCustomFees]);

$customOrderFeesRepository->save($customOrderFees);
