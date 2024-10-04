<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

final class ExtensionAttributesTest extends TestCase
{
    /**
     * @magentoDataFixture JosephLeedy_CustomFees::Test/Integration/_files/orders_with_custom_fees.php
     */
    public function testOrderExtensionAttributeAddsCustomOrderFeesToMultipleOrders(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var SearchCriteriaBuilder $searchCriteriaBuilder */
        $searchCriteriaBuilder = $objectManager->create(SearchCriteriaBuilder::class);
        $searchCriteria = $searchCriteriaBuilder->addFilter(
            'increment_id',
            [
                '100000001',
                '100000002',
                '100000003',
                '100000004'
            ],
            'in'
        )->create();
        /** @var OrderRepositoryInterface $orderRepository */
        $orderRepository = $objectManager->create(OrderRepositoryInterface::class);

        $expectedCustomOrderFees = [
            '_1727299122629_629' => [
                'code' => 'test_fee_0',
                'title' => 'Test Fee',
                'base_value' => 5.00,
                'value' => 5.00
            ],
            '_1727299257083_083' => [
                'code' => 'test_fee_1',
                'title' => 'Another Test Fee',
                'base_value' => 1.50,
                'value' => 1.50
            ]
        ];
        $searchResults = $orderRepository->getList($searchCriteria);

        foreach ($searchResults->getItems() as $order) {
            $actualCustomOrderFees = $order->getExtensionAttributes()
                ?->getCustomOrderFees()
                ?->getCustomFees();

            self::assertNotNull($actualCustomOrderFees);
            self::assertEquals($expectedCustomOrderFees, $actualCustomOrderFees);
        }
    }
}
