<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Observer;

use JosephLeedy\CustomFees\Api\Data\CustomOrderFeesInterface;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFeesInterfaceFactory;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * Observer for `sales_model_service_quote_submit_before` event
 *
 * @see \Magento\Quote\Model\QuoteManagement::submitQuote()
 */
class BeforeQuoteSubmitObserver implements ObserverInterface
{
    public function __construct(private readonly CustomOrderFeesInterfaceFactory $customOrderFeesFactory)
    {}

    public function execute(Observer $observer): void
    {
        $event = $observer->getEvent();
        /** @var CartInterface $quote */
        $quote = $event->getData('quote');
        /** @var OrderInterface $order */
        $order = $event->getData('order');
        $quoteExtension = $quote->getExtensionAttributes();
        $orderExtension = $order->getExtensionAttributes();

        if ($quoteExtension === null || $orderExtension === null || $quoteExtension->getCustomFees() === null) {
            return;
        }

        /** @var CustomOrderFeesInterface $customOrderFees */
        $customOrderFees = $this->customOrderFeesFactory->create();
        /** @var array<string, array{code: string, title: string, base_value: float, value: float}> $customFees */
        $customFees = $quoteExtension->getCustomFees();

        $customOrderFees->setCustomFees($customFees);

        $orderExtension->setCustomOrderFees($customOrderFees);
    }
}
