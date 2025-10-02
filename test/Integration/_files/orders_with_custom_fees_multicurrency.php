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
    'test_fee_0' => [
        'code' => 'test_fee_0',
        'title' => 'Test Fee',
        'type' => 'fixed',
        'percent' => null,
        'show_percentage' => false,
        'base_value' => 5.00,
        'value' => 5.00,
    ],
    'test_fee_1' => [
        'code' => 'test_fee_1',
        'title' => 'Another Test Fee',
        'type' => 'fixed',
        'percent' => null,
        'show_percentage' => false,
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

        $customFeesForOrder['test_fee_0']['value'] = $priceCurrency->convert(
            $customFeesForOrder['test_fee_0']['value'],
            $order->getStoreId(),
            $order->getOrderCurrencyCode(),
        );
        $customFeesForOrder['test_fee_1']['value'] = $priceCurrency->convert(
            $customFeesForOrder['test_fee_1']['value'],
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
    $customOrderFees->setCustomFeesOrdered($customFeesForOrder);

    $customOrderFeesRepository->save($customOrderFees);

    $order->getExtensionAttributes()?->setCustomOrderFees($customOrderFees);
}
