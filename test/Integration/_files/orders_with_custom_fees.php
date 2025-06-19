<?php

declare(strict_types=1);

use JosephLeedy\CustomFees\Api\CustomOrderFeesRepositoryInterface;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFeesInterfaceFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\ResourceModel\Order\Collection as OrderCollection;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

Resolver::getInstance()->requireDataFixture('Magento/Sales/_files/order_list.php');

$objectManager = Bootstrap::getObjectManager();
/** @var OrderCollection $orderCollection */
$orderCollection = $objectManager->create(OrderCollection::class);
/** @var OrderInterface[] $orders */
$orders = $orderCollection->addFieldToFilter(
    'increment_id',
    [
        'in' => [
            '100000001',
            '100000002',
            '100000003',
            '100000004',
        ],
    ],
)->getItems();
$testCustomFees = [
    '_1727299122629_629' => [
        'code' => 'test_fee_0',
        'title' => 'Test Fee',
        'type' => 'fixed',
        'percent' => null,
        'base_value' => 5.00,
        'value' => 5.00,
    ],
    '_1727299257083_083' => [
        'code' => 'test_fee_1',
        'title' => 'Another Test Fee',
        'type' => 'fixed',
        'percent' => null,
        'base_value' => 1.50,
        'value' => 1.50,
    ],
];
/** @var CustomOrderFeesInterfaceFactory $customOrderFeesFactory */
$customOrderFeesFactory = $objectManager->create(CustomOrderFeesInterfaceFactory::class);
/** @var CustomOrderFeesRepositoryInterface $customOrderFeesRepository */
$customOrderFeesRepository = $objectManager->create(CustomOrderFeesRepositoryInterface::class);

foreach ($orders as $order) {
    $customOrderFees = $customOrderFeesFactory->create();

    $customOrderFees->setOrderId($order->getEntityId() ?? 0);
    $customOrderFees->setCustomFees($testCustomFees);

    $customOrderFeesRepository->save($customOrderFees);

    $order->getExtensionAttributes()
        ?->setCustomOrderFees($customOrderFees);
}
