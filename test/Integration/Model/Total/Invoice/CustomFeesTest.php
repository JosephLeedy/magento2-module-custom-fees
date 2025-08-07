<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Model\Total\Invoice;

use Magento\Framework\ObjectManagerInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\ResourceModel\Order as OrderResource;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

final class CustomFeesTest extends TestCase
{
    /**
     * @magentoDataFixture JosephLeedy_CustomFees::../test/Integration/_files/invoice_with_custom_fees.php
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

        /** @var Invoice $invoice */
        $invoice = $order->getInvoiceCollection()->getFirstItem();

        self::assertEquals(26.50, $invoice->getGrandTotal());
    }

    /**
     * @magentoDataFixture JosephLeedy_CustomFees::../test/Integration/_files/invoices_with_custom_fees.php
     */
    public function testCollectsCustomFeesTotalsForMultipleInvoices(): void
    {
        /** @var ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        /** @var OrderResource $orderResource */
        $orderResource = $objectManager->create(OrderResource::class);

        $orderResource->load($order, '100000001', 'increment_id');

        /** @var Invoice[] $invoices */
        $invoices = $order->getInvoiceCollection();

        foreach ($invoices as $invoice) {
            self::assertEquals(13.50, $invoice->getBaseGrandTotal());
            self::assertEquals(13.50, $invoice->getGrandTotal());
        }

        self::assertEquals(27.00, $order->getTotalPaid());
    }

    /**
     * @magentoDataFixture JosephLeedy_CustomFees::../test/Integration/_files/invoice.php
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

        /** @var Invoice $invoice */
        $invoice = $order->getInvoiceCollection()->getFirstItem();

        self::assertEquals(20, $invoice->getGrandTotal());
    }
}
