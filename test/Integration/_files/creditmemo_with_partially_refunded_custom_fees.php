<?php

declare(strict_types=1);

use Magento\Framework\App\Area;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\State;
use Magento\Sales\Api\CreditmemoManagementInterface;
use Magento\Sales\Controller\Adminhtml\Order\CreditmemoLoader;
use Magento\Sales\Model\Order;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

/** @var mixed[] $data */

$resolver = Resolver::getInstance();

$resolver->requireDataFixture('Magento/Sales/_files/default_rollback.php');
$resolver->requireDataFixture('JosephLeedy_CustomFees::../test/Integration/_files/invoice_with_custom_fees.php');

$objectManager = Bootstrap::getObjectManager();
/** @var State $appState */
$appState = $objectManager->get(State::class);

$appState->setAreaCode(Area::AREA_ADMINHTML);

/** @var Order $order */
$order = $objectManager->create(Order::class);
/** @var CreditmemoLoader $creditmemoLoader */
$creditmemoLoader = $objectManager->create(CreditmemoLoader::class);
$creditmemoData = [
    'items' => [],
    'custom_fees' => [
        'test_fee_0' => '5.00',
        'test_fee_1' => '0.00',
    ],
];
/** @var RequestInterface $request */
$request = $objectManager->get(RequestInterface::class);

$order->loadByIncrementId('100000001');

foreach ($order->getAllItems() as $item) {
    $creditmemoData['items'][$item->getId()] = [
        'qty' => $item->getQtyOrdered(),
    ];
}

$request->setParams(['creditmemo' => $creditmemoData]);

$creditmemoLoader->setOrderId($order->getId());
$creditmemoLoader->setCreditmemo($creditmemoData);

$creditmemo = $creditmemoLoader->load();
$creditmemoManagement = $objectManager->create(CreditmemoManagementInterface::class);

$creditmemoManagement->refund($creditmemo, true);
