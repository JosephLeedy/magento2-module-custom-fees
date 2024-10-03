<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Api\Data;

use InvalidArgumentException;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * @method CustomOrderFeesInterface setId(int|string $id)
 * @method int|string|null getId()
 * @method bool hasDataChanges()
 */
interface CustomOrderFeesInterface
{
    public const ORDER_ID = 'order_entity_id';
    public const CUSTOM_FEES = 'custom_fees';

    public function setOrderId(int|string $orderId): CustomOrderFeesInterface;

    public function getOrderId(): ?int;

    /**
     * @param string|array<string, array{code: string, title: string, base_value: float, value: float}> $customFees
     * @throws InvalidArgumentException
     */
    public function setCustomFees(string|array $customFees): CustomOrderFeesInterface;

    /**
     * @return array{}|array<string, array{code: string, title: string, base_value: float, value: float}>
     */
    public function getCustomFees(): array;

    public function getOrder(): ?OrderInterface;
}
