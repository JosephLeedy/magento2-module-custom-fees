<?php

declare(strict_types=1);

use JosephLeedy\CustomFees\Api\CustomOrderFeesRepositoryInterface;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFeesInterfaceFactory;
use Magento\Directory\Model\Currency;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\ResourceModel\Order\Collection as OrderCollection;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

Resolver::getInstance()->requireDataFixture('Magento/Sales/_files/order_list.php');

$objectManager = Bootstrap::getObjectManager();
/** @var OrderCollection $orderCollection */
$orderCollection = $objectManager->create(OrderCollection::class);
/** @var OrderInterface[] $orders */
$orders = $orderCollection
    ->addFieldToFilter(
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
        'base_value' => 5.00,
        'value' => 5.00,
    ],
    '_1727299257083_083' => [
        'code' => 'test_fee_1',
        'title' => 'Another Test Fee',
        'base_value' => 1.50,
        'value' => 1.50,
    ],
];
/** @var CustomOrderFeesInterfaceFactory $customOrderFeesFactory */
$customOrderFeesFactory = $objectManager->create(CustomOrderFeesInterfaceFactory::class);
/** @var CustomOrderFeesRepositoryInterface $customOrderFeesRepository */
$customOrderFeesRepository = $objectManager->create(CustomOrderFeesRepositoryInterface::class);
/** @var Currency $currency */
$currency = $objectManager->get(Currency::class);
$rate = $currency->load('USD')->getRate('EUR');
/** @var PriceCurrencyInterface $priceCurrency */
$priceCurrency = $objectManager->get(PriceCurrencyInterface::class);

foreach ($orders as $key => $order) {
    $customFeesForOrder = $testCustomFees;

    if ($key % 2 === 0) {
        $order->setOrderCurrencyCode('EUR');
        $order->setBaseToOrderRate($rate);
        $order->save();

        $customFeesForOrder['_1727299122629_629']['value'] = $priceCurrency->convert(
            $customFeesForOrder['_1727299122629_629']['value'],
            $order->getStoreId(),
            $order->getOrderCurrencyCode(),
        );
        $customFeesForOrder['_1727299257083_083']['value'] = $priceCurrency->convert(
            $customFeesForOrder['_1727299257083_083']['value'],
            $order->getStoreId(),
            $order->getOrderCurrencyCode(),
        );
    }

    if ($order->getOrderCurrencyCode() === null) {
        $order->setOrderCurrencyCode('USD');
        $order->setBaseToOrderRate(1);
        $order->save();
    }

    $customOrderFees = $customOrderFeesFactory->create();

    $customOrderFees->setOrderId($order->getEntityId() ?? 0);
    $customOrderFees->setCustomFees($customFeesForOrder);

    $customOrderFeesRepository->save($customOrderFees);

    $order->getExtensionAttributes()?->setCustomOrderFees($customOrderFees);
}
