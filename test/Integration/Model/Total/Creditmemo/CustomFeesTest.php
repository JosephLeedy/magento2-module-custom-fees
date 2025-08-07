<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Model\Total\Creditmemo;

use Magento\Framework\ObjectManagerInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\ResourceModel\Order as OrderResource;
use Magento\Sales\Model\ResourceModel\Order\Creditmemo\Collection as CreditmemoCollection;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

final class CustomFeesTest extends TestCase
{
    /**
     * @magentoDataFixture JosephLeedy_CustomFees::../test/Integration/_files/creditmemo_with_custom_fees.php
     */
    public function testCollectsCustomFeesTotals(): void
    {
        /** @var ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        /** @var OrderResource $orderResource */
        $orderResource = $objectManager->create(OrderResource::class);

        $orderResource->load($order, '100000001', 'increment_id');

        /** @var CreditmemoCollection $creditmemosCollection */
        $creditmemosCollection = $order->getCreditmemosCollection()
            ?: $objectManager->create(CreditmemoCollection::class);

        /** @var Creditmemo $creditmemo */
        $creditmemo = $creditmemosCollection->getFirstItem();

        self::assertEquals(26.50, $creditmemo->getBaseGrandTotal());
        self::assertEquals(26.50, $creditmemo->getGrandTotal());
    }

    /**
     * @magentoDataFixture JosephLeedy_CustomFees::../test/Integration/_files/creditmemos_with_custom_fees.php
     */
    public function testCollectsCustomFeesTotalsForMultipleCreditMemos(): void
    {
        /** @var ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        /** @var OrderResource $orderResource */
        $orderResource = $objectManager->create(OrderResource::class);

        $orderResource->load($order, '100000001', 'increment_id');

        /** @var Creditmemo[] $creditmemos */
        $creditmemos = $order->getCreditmemosCollection()->getItems();

        foreach ($creditmemos as $creditmemo) {
            self::assertEquals(13.50, $creditmemo->getBaseGrandTotal());
            self::assertEquals(13.50, $creditmemo->getGrandTotal());
        }

        self::assertEquals(27.00, $order->getTotalRefunded());
    }

    /**
     * @magentoDataFixture JosephLeedy_CustomFees::../test/Integration/_files/creditmemo.php
     */
    public function testDoesNotCollectsCustomFeesTotals(): void
    {
        /** @var ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        /** @var OrderResource $orderResource */
        $orderResource = $objectManager->create(OrderResource::class);

        $orderResource->load($order, '100000001', 'increment_id');

        /** @var CreditmemoCollection $creditmemosCollection */
        $creditmemosCollection = $order->getCreditmemosCollection()
            ?: $objectManager->create(CreditmemoCollection::class);

        /** @var Creditmemo $creditmemo */
        $creditmemo = $creditmemosCollection->getFirstItem();

        self::assertEquals(20, $creditmemo->getGrandTotal());
    }
}
