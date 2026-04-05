<?php

declare(strict_types=1);

use JosephLeedy\CustomFees\Api\CustomOrderFeesRepositoryInterface;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFeesInterface;
use JosephLeedy\CustomFees\Model\FeeType;
use Magento\Sales\Model\Order;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

$resolver = Resolver::getInstance();

$resolver->requireDataFixture('JosephLeedy_CustomFees::../test/Integration/_files/order.php');

$objectManager = Bootstrap::getObjectManager();
/** @var Order $order */
$order = $objectManager->create(Order::class);
/** @var CustomOrderFeesInterface $customOrderFees */
$customOrderFees = $objectManager->create(CustomOrderFeesInterface::class);
/** @var CustomOrderFeesRepositoryInterface $customOrderFeesRepository */
$customOrderFeesRepository = $objectManager->create(CustomOrderFeesRepositoryInterface::class);
$customFeesOrdered = [
    'test_fee_0' => [
        'code' => 'test_fee_0',
        'title' => 'Test Fee',
        'type' => FeeType::Fixed,
        'percent' => null,
        'show_percentage' => false,
        'base_value' => 10.00,
        'value' => 10.00,
    ],
    'test_fee_1' => [
        'code' => 'test_fee_1',
        'title' => 'Another Test Fee',
        'type' => FeeType::Percent,
        'percent' => 5,
        'show_percentage' => true,
        'base_value' => 4.00,
        'value' => 4.00,
    ],
];

$order->loadByIncrementId('100000001');

$customOrderFees
    ->setOrderId($order->getEntityId())
    ->setData(CustomOrderFeesInterface::CUSTOM_FEES_ORDERED, json_encode($customFeesOrdered, JSON_THROW_ON_ERROR))
    ->setData(
        CustomOrderFeesInterface::CUSTOM_FEES_INVOICED,
        json_encode(
            [
                1 => array_merge_recursive(
                    $customFeesOrdered,
                    [
                        'test_fee_0' => [
                            'invoice_id' => 1,
                        ],
                        'test_fee_1' => [
                            'invoice_id' => 1,
                        ],
                    ],
                ),
            ],
            JSON_THROW_ON_ERROR,
        ),
    )->setData(
        CustomOrderFeesInterface::CUSTOM_FEES_REFUNDED,
        json_encode(
            [
                1 => array_merge_recursive(
                    $customFeesOrdered,
                    [
                        'test_fee_0' => [
                            'credit_memo_id' => 1,
                        ],
                        'test_fee_1' => [
                            'credit_memo_id' => 1,
                        ],
                    ],
                ),
            ],
            JSON_THROW_ON_ERROR,
        ),
    );

$customOrderFeesRepository->save($customOrderFees);
