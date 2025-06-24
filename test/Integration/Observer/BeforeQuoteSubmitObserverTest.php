<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Observer;

use JosephLeedy\CustomFees\Api\Data\CustomOrderFeesInterface;
use JosephLeedy\CustomFees\Observer\BeforeQuoteSubmitObserver;
use Magento\Framework\Event\ConfigInterface as EventObserverConfig;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\Sales\Api\Data\OrderExtension;
use Magento\Sales\Model\Order;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

final class BeforeQuoteSubmitObserverTest extends TestCase
{
    /**
     * @magentoAppArea frontend
     */
    public function testIsConfiguredCorrectly(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var EventObserverConfig $observerConfig */
        $observerConfig = $objectManager->create(EventObserverConfig::class);
        $observers = $observerConfig->getObservers('sales_model_service_quote_submit_before');

        self::assertArrayHasKey('add_custom_fees_to_order', $observers);
        self::assertSame(
            ltrim(BeforeQuoteSubmitObserver::class, '\\'),
            $observers['add_custom_fees_to_order']['instance'],
        );
    }

    /**
     * @phpcs:ignore Generic.Files.LineLength.TooLong
     * @magentoConfigFixture current_store sales/custom_order_fees/custom_fees [{"code":"test_fee_0","title":"Test Fee","type":"fixed","value":"4.00","advanced":"{\"show_percentage\":\"0\"}"},{"code":"test_fee_1","title":"Another Fee","type":"fixed","value":"1.00","advanced":"{\"show_percentage\":\"0\"}"}]
     * @magentoDataFixture Magento/Checkout/_files/quote_with_shipping_method.php
     */
    public function testAddsCustomFeesToOrder(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var Quote $quote */
        $quote = $objectManager->create(Quote::class);
        /** @var QuoteResource $quoteResource */
        $quoteResource = $objectManager->create(QuoteResource::class);
        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        /** @var OrderExtension $orderExtension */
        $orderExtension = $objectManager->create(OrderExtension::class);
        /** @var EventManager $eventManager */
        $eventManager = $objectManager->create(EventManager::class);

        $quoteResource->load($quote, 'test_order_1', 'reserved_order_id');

        $quote->collectTotals();

        $order->setExtensionAttributes($orderExtension);

        $eventManager->dispatch(
            'sales_model_service_quote_submit_before',
            [
                'quote' => $quote,
                'order' => $order,
            ],
        );

        self::assertInstanceOf(
            CustomOrderFeesInterface::class,
            $order->getExtensionAttributes()?->getCustomOrderFees(),
        );
    }
}
