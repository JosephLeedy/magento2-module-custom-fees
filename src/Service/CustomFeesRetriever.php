<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Service;

use Deprecated;
use JosephLeedy\CustomFees\Api\CustomOrderFeesRepositoryInterface;
use JosephLeedy\CustomFees\Model\FeeType;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order;

/**
 * @api
 */
class CustomFeesRetriever
{
    public function __construct(private readonly CustomOrderFeesRepositoryInterface $customOrderFeesRepository) {}

    /**
     * @return array{}|array<string, array{
     *     code: string,
     *     title: string,
     *     type: value-of<FeeType>,
     *     percent: float|null,
     *     show_percentage: bool,
     *     base_value: float,
     *     value: float
     * }>
     */
    #[Deprecated('Use `retrieveOrderedCustomFees()` instead', '1.3.0')]
    public function retrieve(Order $order): array
    {
        return $this->retrieveOrderedCustomFees($order);
    }

    /**
     * @return array{}|array<string, array{
     *     code: string,
     *     title: string,
     *     type: value-of<FeeType>,
     *     percent: float|null,
     *     show_percentage: bool,
     *     base_value: float,
     *     value: float
     * }>
     */
    public function retrieveOrderedCustomFees(Order $order): array
    {
        $orderExtension = $order->getExtensionAttributes();

        if ($orderExtension === null) {
            return [];
        }

        /**
         * @var array<string, array{
         *     code: string,
         *     title: string,
         *     type: value-of<FeeType>,
         *     percent: float|null,
         *     show_percentage: bool,
         *     base_value: float,
         *     value: float
         * }> $customFees
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
     * @return array{}|array<string, array{
     *     invoice_id: int,
     *     code: string,
     *     title: string,
     *     type: value-of<FeeType>,
     *     percent: float|null,
     *     show_percentage: bool,
     *     base_value: float,
     *     value: float
     * }>[]
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
     * @return array{}|array<string, array{
     *     credit_memo_id: int,
     *     code: string,
     *     title: string,
     *     type: value-of<FeeType>,
     *     percent: float|null,
     *     show_percentage: bool,
     *     base_value: float,
     *     value: float
     * }>[]
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
