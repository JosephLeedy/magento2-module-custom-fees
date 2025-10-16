<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Observer;

use JosephLeedy\CustomFees\Api\Data\CustomOrderFeeInterface;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFeesInterface;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFeesInterfaceFactory;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Api\Data\CartExtensionInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Api\Data\OrderExtensionInterface;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * Observer for `sales_model_service_quote_submit_before` event
 *
 * @see \Magento\Quote\Model\QuoteManagement::submitQuote()
 */
class BeforeQuoteSubmitObserver implements ObserverInterface
{
    public function __construct(private readonly CustomOrderFeesInterfaceFactory $customOrderFeesFactory) {}

    public function execute(Observer $observer): void
    {
        $event = $observer->getEvent();
        /** @var CartInterface $quote */
        $quote = $event->getData('quote');
        /** @var OrderInterface $order */
        $order = $event->getData('order');
        /** @var CartExtensionInterface $quoteExtension */
        $quoteExtension = $quote->getExtensionAttributes();
        /** @var array<string, CustomOrderFeeInterface>|null $customFees */
        $customFees = $quoteExtension->getCustomFees();
        /** @var OrderExtensionInterface $orderExtension */
        $orderExtension = $order->getExtensionAttributes();

        if ($customFees === null || $customFees === []) {
            return;
        }

        /** @var CustomOrderFeesInterface $customOrderFees */
        $customOrderFees = $this->customOrderFeesFactory->create();

        $customOrderFees->setCustomFeesOrdered($customFees);

        $orderExtension->setCustomOrderFees($customOrderFees);
    }
}
