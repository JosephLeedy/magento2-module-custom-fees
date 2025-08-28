<?php

declare(strict_types=1);

use JosephLeedy\CustomFees\Api\CustomOrderFeesRepositoryInterface;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFeesInterface;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFeesInterfaceFactory;
use JosephLeedy\CustomFees\Model\CustomOrderFeesRepository;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order as OrderResource;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

Resolver::getInstance()->requireDataFixture('JosephLeedy_CustomFees::../test/Integration/_files/order.php');

$objectManager = Bootstrap::getObjectManager();
/** @var Order $order */
$order = $objectManager->create(Order::class);
/** @var OrderResource $orderResource */
$orderResource = $objectManager->create(OrderResource::class);
/** @var CustomOrderFeesInterfaceFactory $customOrderFeesFactory */
$customOrderFeesFactory = $objectManager->create(CustomOrderFeesInterfaceFactory::class);
/** @var CustomOrderFeesInterface $customOrderFees */
$customOrderFees = $customOrderFeesFactory->create();
/** @var CustomOrderFeesRepository $customOrderFeesRepository */
$customOrderFeesRepository = $objectManager->create(CustomOrderFeesRepositoryInterface::class);
$customFees = [
    [
        'code' => 'example_fee',
        'title' => 'Example Fee',
        'type' => 'fixed',
        'percent' => null,
        'show_percentage' => false,
        'base_value' => 0.00,
        'value' => 0.00,
    ],
];

$orderResource->load($order, '100000001', 'increment_id');

/** @var int $orderId */
$orderId = $order->getEntityId() ?? 0;

$customOrderFees->setOrderId($orderId);
$customOrderFees->setCustomFeesOrdered($customFees);

$customOrderFeesRepository->save($customOrderFees);

$order->getExtensionAttributes()
    ?->setCustomOrderFees($customOrderFees);
