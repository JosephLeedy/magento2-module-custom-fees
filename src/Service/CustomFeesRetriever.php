<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Service;

use Deprecated;
use JosephLeedy\CustomFees\Api\CustomOrderFeesRepositoryInterface;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFee\InvoicedInterface as InvoicedCustomFee;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFee\RefundedInterface as RefundedCustomFee;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFeeInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order;

/**
 * @api
 */
class CustomFeesRetriever
{
    public function __construct(private readonly CustomOrderFeesRepositoryInterface $customOrderFeesRepository) {}

    /**
     * @return array<string, CustomOrderFeeInterface>
     */
    #[Deprecated('Use `retrieveOrderedCustomFees()` instead', '1.3.0')]
    public function retrieve(Order $order): array
    {
        return $this->retrieveOrderedCustomFees($order);
    }

    /**
     * @return array<string, CustomOrderFeeInterface>
     */
    public function retrieveOrderedCustomFees(Order $order): array
    {
        $orderExtension = $order->getExtensionAttributes();

        if ($orderExtension === null) {
            return [];
        }

        /**
         * @var array<string, CustomOrderFeeInterface>|null $customFees
         */
        $customFees = $orderExtension->getCustomOrderFees()
            ?->getCustomFeesOrdered();

        if ($customFees === null) {
            try {
                /** @var int|string|null $orderId */
                $orderId = $order->getEntityId();
                $customFees = $this->customOrderFeesRepository->getByOrderId($orderId ?? 0)
                    ->getCustomFeesOrdered();
            } catch (NoSuchEntityException) {
                $customFees = [];
            }
        }

        return $customFees;
    }

    /**
     * @return array<int, array<string, InvoicedCustomFee>>
     */
    public function retrieveInvoicedCustomFees(Order $order): array
    {
        /** @var int|string|null $orderId */
        $orderId = $order->getEntityId();

        if ($orderId === null) {
            return [];
        }

        try {
            $customFeesInvoiced = $this->customOrderFeesRepository
                ->getByOrderId($orderId)
                ->getCustomFeesInvoiced();
        } catch (NoSuchEntityException) {
            $customFeesInvoiced = [];
        }

        return $customFeesInvoiced;
    }

    /**
     * @return array<int, array<string, RefundedCustomFee>>
     */
    public function retrieveRefundedCustomFees(Order $order): array
    {
        /** @var int|string|null $orderId */
        $orderId = $order->getEntityId();

        if ($orderId === null) {
            return [];
        }

        try {
            $customFeesRefunded = $this->customOrderFeesRepository
                ->getByOrderId($orderId)
                ->getCustomFeesRefunded();
        } catch (NoSuchEntityException) {
            $customFeesRefunded = [];
        }

        return $customFeesRefunded;
    }
}
