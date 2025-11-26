<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Api\Data;

use InvalidArgumentException;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFee\InvoicedInterface;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFee\RefundedInterface;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * @method CustomOrderFeesInterface setId(int|string $id)
 * @method int|string|null getId()
 * @method bool hasDataChanges()
 */
interface CustomOrderFeesInterface
{
    public const ORDER_ID = 'order_entity_id';
    public const CUSTOM_FEES_ORDERED = 'custom_fees_ordered';
    public const CUSTOM_FEES_INVOICED = 'custom_fees_invoiced';
    public const CUSTOM_FEES_REFUNDED = 'custom_fees_refunded';

    /**
     * @param int|string $orderId
     * @return \JosephLeedy\CustomFees\Api\Data\CustomOrderFeesInterface
     */
    public function setOrderId(int|string $orderId): CustomOrderFeesInterface;

    /**
     * @return int|null
     */
    public function getOrderId(): ?int;

    /**
     * @param string|\JosephLeedy\CustomFees\Api\Data\CustomOrderFeeInterface[] $customFeesOrdered
     * @phpstan-param string|array<string, CustomOrderFeeInterface> $customFeesOrdered
     * @return \JosephLeedy\CustomFees\Api\Data\CustomOrderFeesInterface
     * @throws InvalidArgumentException
     */
    public function setCustomFeesOrdered(string|array $customFeesOrdered): CustomOrderFeesInterface;

    /**
     * @return \JosephLeedy\CustomFees\Api\Data\CustomOrderFeeInterface[]
     * @phpstan-return array<string, CustomOrderFeeInterface>
     */
    public function getCustomFeesOrdered(): array;

    /**
     * @param string|\JosephLeedy\CustomFees\Api\Data\CustomOrderFee\InvoicedInterface[][] $customFeesInvoiced
     * @phpstan-param string|array<int, array<string, InvoicedInterface>> $customFeesInvoiced
     * @return \JosephLeedy\CustomFees\Api\Data\CustomOrderFeesInterface
     * @throws InvalidArgumentException
     */
    public function setCustomFeesInvoiced(string|array $customFeesInvoiced): CustomOrderFeesInterface;

    /**
     * @return \JosephLeedy\CustomFees\Api\Data\CustomOrderFee\InvoicedInterface[][]
     * @phpstan-return array<int, array<string, InvoicedInterface>>
     */
    public function getCustomFeesInvoiced(): array;

    /**
     * @param string|\JosephLeedy\CustomFees\Api\Data\CustomOrderFee\RefundedInterface[][] $customFeesRefunded
     * @phpstan-param string|array<int, array<string, RefundedInterface>> $customFeesRefunded
     * @return \JosephLeedy\CustomFees\Api\Data\CustomOrderFeesInterface
     * @throws InvalidArgumentException
     */
    public function setCustomFeesRefunded(string|array $customFeesRefunded): CustomOrderFeesInterface;

    /**
     * @return \JosephLeedy\CustomFees\Api\Data\CustomOrderFee\RefundedInterface[][]
     * @phpstan-return array<int, array<string, RefundedInterface>>
     */
    public function getCustomFeesRefunded(): array;

    /**
     * @return \Magento\Sales\Api\Data\OrderInterface|null
     */
    public function getOrder(): ?OrderInterface;
}
