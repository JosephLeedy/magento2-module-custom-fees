<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Service;

use JosephLeedy\CustomFees\Service\CustomFeesRetriever;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order as OrderResource;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

use function current;

final class CustomFeesRetrieverTest extends TestCase
{
    /**
     * @dataProvider retrievesCustomFeesDataProvider
     * @magentoDataFixture JosephLeedy_CustomFees::../test/Integration/_files/order_with_custom_fees.php
     */
    public function testRetrievesCustomFeesForOrder(string $source): void
    {
        /** @var ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var CustomFeesRetriever $customFeesRetriever */
        $customFeesRetriever = $objectManager->create(CustomFeesRetriever::class);

        if ($source === 'order_extension') {
            /** @var SearchCriteriaBuilder $searchCriteriaBuilder */
            $searchCriteriaBuilder = $objectManager->create(SearchCriteriaBuilder::class);
            /** @var SearchCriteriaInterface $searchCriteria */
            $searchCriteria = $searchCriteriaBuilder->addFilter('increment_id', '100000001')
                ->create();
            /** @var OrderRepositoryInterface $orderRepository */
            $orderRepository = $objectManager->create(OrderRepositoryInterface::class);
            $orders = $orderRepository->getList($searchCriteria)
                ->getItems();
            /** @var Order $order */
            $order = current($orders);
        } else {
            /** @var Order $order */
            $order = $objectManager->create(Order::class);
            /** @var OrderResource $orderResource */
            $orderResource = $objectManager->create(OrderResource::class);

            $orderResource->load($order, '100000001', 'increment_id');
        }

        $expectedCustomFees = [
            '_1727299833817_817' => [
                'code' => 'test_fee_0',
                'title' => 'Test Fee',
                'type' => 'fixed',
                'percent' => null,
                'base_value' => 5.00,
                'value' => 5.00,
            ],
            '_1727299843197_197' => [
                'code' => 'test_fee_1',
                'title' => 'Another Test Fee',
                'type' => 'fixed',
                'percent' => null,
                'base_value' => 1.50,
                'value' => 1.50,
            ],
        ];
        $actualCustomFees = $customFeesRetriever->retrieve($order);

        self::assertEquals($expectedCustomFees, $actualCustomFees);
    }

    /**
     * @dataProvider doesNotRetrieveCustomFeesDataProvider
     * @magentoDataFixture Magento/Sales/_files/order.php
     */
    public function testDoesNotRetrieveCustomFeesForOrder(string $condition): void
    {
        /** @var ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var CustomFeesRetriever $customFeesRetriever */
        $customFeesRetriever = $objectManager->create(CustomFeesRetriever::class);

        if ($condition === 'order_extension_null') {
            $order = $this->createPartialMock(Order::class, ['getExtensionAttributes']);

            $order->method('getExtensionAttributes')
                ->willReturn(null);
        } else {
            /** @var Order $order */
            $order = $objectManager->create(Order::class);
            /** @var OrderResource $orderResource */
            $orderResource = $objectManager->create(OrderResource::class);

            $orderResource->load($order, '100000001', 'increment_id');
        }

        $customFees = $customFeesRetriever->retrieve($order);

        self::assertEmpty($customFees);
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function retrievesCustomFeesDataProvider(): array
    {
        return [
            'from extension attribute' => [
                'source' => 'order_extension',
            ],
            'from custom order fees database table' => [
                'source' => 'custom_order_fees_table',
            ],
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function doesNotRetrieveCustomFeesDataProvider(): array
    {
        return [
            'extension attribute not instantiated' => [
                'condition' => 'order_extension_null',
            ],
            'no custom fees for order' => [
                'source' => 'no_custom_order_fees',
            ],
        ];
    }
}
