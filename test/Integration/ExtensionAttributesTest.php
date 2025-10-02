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
     * @magentoDataFixture JosephLeedy_CustomFees::../test/Integration/_files/orders_with_custom_fees.php
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
                '100000004',
            ],
            'in',
        )->create();
        /** @var OrderRepositoryInterface $orderRepository */
        $orderRepository = $objectManager->create(OrderRepositoryInterface::class);

        $expectedCustomOrderFees = [
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
        $searchResults = $orderRepository->getList($searchCriteria);

        foreach ($searchResults->getItems() as $order) {
            $actualCustomOrderFees = $order->getExtensionAttributes()
                ?->getCustomOrderFees()
                ?->getCustomFeesOrdered();

            self::assertNotNull($actualCustomOrderFees);
            self::assertEquals($expectedCustomOrderFees, $actualCustomOrderFees);
        }
    }
}
