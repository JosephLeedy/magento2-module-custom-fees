<?php

declare(strict_types=1);

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\ObjectManagerInterface;
use Magento\Sales\Api\CreditmemoManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\CreditmemoFactory;
use Magento\Sales\Model\Order\Invoice;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

$resolver = Resolver::getInstance();

$resolver->requireDataFixture('Magento/Sales/_files/default_rollback.php');
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
/** @var CreditmemoFactory $creditmemoFactory */
$creditmemoFactory = $objectManager->create(CreditmemoFactory::class);
/** @var CreditmemoManagementInterface $creditmemoManagement */
$creditmemoManagement = $objectManager->create(CreditmemoManagementInterface::class);

$orderCollection->walk(
    static function (Order $order) use ($creditmemoFactory, $creditmemoManagement): void {
        $order
            ->getInvoiceCollection()
            ->walk(
                static function (Invoice $invoice) use ($creditmemoFactory, $creditmemoManagement): void {
                    $creditmemo = $creditmemoFactory->createByInvoice($invoice);

                    $creditmemoManagement->refund($creditmemo);
                },
            );
    },
);
