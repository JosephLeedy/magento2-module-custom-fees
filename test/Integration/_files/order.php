<?php

declare(strict_types=1);

use Magento\Framework\ObjectManagerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

$resolver = Resolver::getInstance();

$resolver->requireDataFixture('Magento/Sales/_files/order.php');

/** @var ObjectManagerInterface $objectManager */
$objectManager = Bootstrap::getObjectManager();
/** @var Order $order */
$order = $objectManager->create(Order::class);

$order->loadByIncrementId('100000001');

$orderItems = $order->getAllItems();
$baseOrderTotal = 0;
$orderTotal = 0;

foreach ($orderItems as $orderItem) {
    $orderItem->setBaseRowTotal($orderItem->getBasePrice() * $orderItem->getQtyOrdered());
    $orderItem->setRowTotal($orderItem->getPrice() * $orderItem->getQtyOrdered());

    $baseOrderTotal += $orderItem->getBaseRowTotal();
    $orderTotal += $orderItem->getRowTotal();
}

$order->setBaseSubtotal($baseOrderTotal);
$order->setSubtotal($orderTotal);
$order->setBaseGrandTotal($baseOrderTotal);
$order->setGrandTotal($orderTotal);

/** @var OrderRepositoryInterface $orderRepository */
$orderRepository = $objectManager->create(OrderRepositoryInterface::class);

$orderRepository->save($order);
