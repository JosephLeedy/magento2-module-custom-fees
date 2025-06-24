<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Api\Data;

use InvalidArgumentException;
use JosephLeedy\CustomFees\Model\FeeType;
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
     * @param string|string[]|float[] $customFees
     * @phpstan-param string|array<string, array{
     *     code: string,
     *     title: string,
     *     type: value-of<FeeType>,
     *     percent: float|null,
     *     show_percentage: bool,
     *     base_value: float,
     *     value: float
     * }> $customFees
     * @throws InvalidArgumentException
     */
    public function setCustomFees(string|array $customFees): CustomOrderFeesInterface;

    /**
     * @return string[]|float[]
     * @phpstan-return array{}|array<string, array{
     *     code: string,
     *     title: string,
     *     type: value-of<FeeType>,
     *     percent: float|null,
     *     show_percentage: bool,
     *     base_value: float,
     *     value: float
     * }>
     */
    public function getCustomFees(): array;

    /**
     * @return \Magento\Sales\Api\Data\OrderInterface|null
     */
    public function getOrder(): ?OrderInterface;
}
