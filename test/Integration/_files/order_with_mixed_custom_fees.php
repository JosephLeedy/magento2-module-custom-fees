<?php

declare(strict_types=1);

use JosephLeedy\CustomFees\Api\CustomOrderFeesRepositoryInterface;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFeeInterface;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFeesInterface;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFeesInterfaceFactory;
use JosephLeedy\CustomFees\Model\CustomOrderFeesRepository;
use JosephLeedy\CustomFees\Model\FeeType;
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
$testCustomFees = [
    'test_fee_0' => $objectManager->create(
        CustomOrderFeeInterface::class,
        [
            'data' => [
                'code' => 'test_fee_0',
                'title' => 'Test Fee',
                'type' => FeeType::Fixed,
                'percent' => null,
                'show_percentage' => false,
                'base_value' => 5.00,
                'value' => 5.00,
                'base_value_with_tax' => 5.00,
                'value_with_tax' => 5.00,
                'base_tax_amount' => 0.00,
                'tax_amount' => 0.00,
                'tax_rate' => 0.00,
            ],
        ],
    ),
    'test_fee_1' => $objectManager->create(
        CustomOrderFeeInterface::class,
        [
            'data' => [
                'code' => 'test_fee_1',
                'title' => 'Another Test Fee',
                'type' => FeeType::Percent,
                'percent' => null,
                'show_percentage' => true,
                'base_value' => 2.00,
                'value' => 2.00,
                'base_value_with_tax' => 2.00,
                'value_with_tax' => 2.00,
                'base_tax_amount' => 0.00,
                'tax_amount' => 0.00,
                'tax_rate' => 0.00,
            ],
        ],
    ),
];

$orderResource->load($order, '100000001', 'increment_id');

/** @var int $orderId */
$orderId = $order->getEntityId() ?? 0;

$customOrderFees->setOrderId($orderId);
$customOrderFees->setCustomFeesOrdered($testCustomFees);

$customOrderFeesRepository->save($customOrderFees);

$order->getExtensionAttributes()
    ?->setCustomOrderFees($customOrderFees);
