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

Resolver::getInstance()->requireDataFixture('JosephLeedy_CustomFees::Test/Integration/_files/order.php');

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
$testCustomFees = [
    '_1727299833817_817' => [
        'code' => 'test_fee_0',
        'title' => 'Test Fee',
        'base_value' => 5.00,
        'value' => 5.00
    ],
    '_1727299843197_197' => [
        'code' => 'test_fee_1',
        'title' => 'Another Test Fee',
        'base_value' => 1.50,
        'value' => 1.50
    ]
];

$orderResource->load($order, '100000001', 'increment_id');

/** @var int $orderId */
$orderId = $order->getEntityId() ?? 0;

$customOrderFees->setOrderId($orderId);
$customOrderFees->setCustomFees($testCustomFees);

$customOrderFeesRepository->save($customOrderFees);

$order->getExtensionAttributes()
    ?->setCustomOrderFees($customOrderFees);
