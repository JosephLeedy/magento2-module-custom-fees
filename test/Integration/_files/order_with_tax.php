<?php

declare(strict_types=1);

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Tax;
use Magento\Sales\Model\Order\Tax\Item;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

$resolver = Resolver::getInstance();

$resolver->requireDataFixture('JosephLeedy_CustomFees::../test/Integration/_files/order.php');

$objectManager = Bootstrap::getObjectManager();
/** @var Order $order */
$order = $objectManager->create(Order::class);
/** @var Tax $tax */
$tax = $objectManager->create(Tax::class);
/** @var Item $orderTaxItem */
$orderTaxItem = $objectManager->create(Item::class);

$order->loadByIncrementId('100000001');
$order->setBaseTaxAmount($order->getBaseGrandTotal() * (6 / 100));
$order->setTaxAmount($order->getGrandTotal() * (6 / 100));
$order->setBaseGrandTotal($order->getBaseGrandTotal() + $order->getBaseTaxAmount());
$order->setGrandTotal($order->getGrandTotal() + $order->getTaxAmount());

foreach ($order->getAllItems() as $orderItem) {
    $orderItem->setBaseTaxAmount(($orderItem->getBasePrice() * (6 / 100)) * $orderItem->getQtyOrdered());
    $orderItem->setTaxAmount(($orderItem->getPrice() * (6 / 100)) * $orderItem->getQtyOrdered());
    $orderItem->save();
}

$tax
    ->setOrderId($order->getId())
    ->setCode('US-CA-*-Rate-1')
    ->setTitle('US-CA-*-Rate-1')
    ->setPercent(6.0)
    ->setBaseAmount($order->getBaseTaxAmount())
    ->setBaseRealAmount($order->getBaseTaxAmount())
    ->setAmount($order->getTaxAmount())
    ->save();

$orderTaxItem
    ->setOrderId($order->getId())
    ->setStoreId($order->getStoreId())
    ->setTaxId($tax->getId())
    ->setProductOptions([])
    ->save();

$order->save();
