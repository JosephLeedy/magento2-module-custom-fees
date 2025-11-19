<?php

declare(strict_types=1);

use JosephLeedy\CustomFees\Api\CustomOrderFeesRepositoryInterface;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFeeInterface;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFeesInterfaceFactory;
use JosephLeedy\CustomFees\Model\FeeType;
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
                'type' => FeeType::Fixed,
                'percent' => null,
                'show_percentage' => false,
                'base_value' => 1.50,
                'value' => 1.50,
                'base_value_with_tax' => 1.50,
                'value_with_tax' => 1.50,
                'base_tax_amount' => 0.00,
                'tax_amount' => 0.00,
                'tax_rate' => 0.00,
            ],
        ],
    ),
];
/** @var CustomOrderFeesInterfaceFactory $customOrderFeesFactory */
$customOrderFeesFactory = $objectManager->create(CustomOrderFeesInterfaceFactory::class);
/** @var CustomOrderFeesRepositoryInterface $customOrderFeesRepository */
$customOrderFeesRepository = $objectManager->create(CustomOrderFeesRepositoryInterface::class);

foreach ($orders as $order) {
    $customOrderFees = $customOrderFeesFactory->create();

    $customOrderFees->setOrderId($order->getEntityId() ?? 0);
    $customOrderFees->setCustomFeesOrdered($testCustomFees);

    $customOrderFeesRepository->save($customOrderFees);

    $order->getExtensionAttributes()
        ?->setCustomOrderFees($customOrderFees);
}
