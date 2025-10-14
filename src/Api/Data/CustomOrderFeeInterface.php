<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Api\Data;

use InvalidArgumentException;
use Magento\Framework\Phrase;

interface CustomOrderFeeInterface
{
    public const CODE = 'code';
    public const TITLE = 'title';
    public const TYPE = 'type';
    public const PERCENT = 'percent';
    public const SHOW_PERCENTAGE = 'show_percentage';
    public const BASE_VALUE = 'base_value';
    public const VALUE = 'value';

    /**
     * @param string $code
     * @return CustomOrderFeeInterface
     */
    public function setCode(string $code): CustomOrderFeeInterface;

    /**
     * @return string
     */
    public function getCode(): string;

    /**
     * @param string $title
     * @return CustomOrderFeeInterface
     */
    public function setTitle(string $title): CustomOrderFeeInterface;

    /**
     * @return string
     */
    public function getTitle(): string;

    /**
     * @param string $type
     * @phpstan-param \JosephLeedy\CustomFees\Api\Data\FeeTypeInterface|string $type
     * @return CustomOrderFeeInterface
     * @throws InvalidArgumentException
     */
    public function setType(FeeTypeInterface|string $type): CustomOrderFeeInterface;

    /**
     * @return string
     * @phpstan-return \JosephLeedy\CustomFees\Api\Data\FeeTypeInterface|string Returns a string when called from the
     * REST, SOAP or GraphQL API; otherwise returns an instance of `FeeTypeInterface`.
     */
    public function getType(): FeeTypeInterface|string;

    /**
     * @param float|null $percent
     * @return CustomOrderFeeInterface
     */
    public function setPercent(?float $percent): CustomOrderFeeInterface;

    /**
     * @return float|null
     */
    public function getPercent(): ?float;

    /**
     * @param bool $showPercentage
     * @return CustomOrderFeeInterface
     */
    public function setShowPercentage(bool|int $showPercentage): CustomOrderFeeInterface;

    /**
     * @return bool
     */
    public function getShowPercentage(): bool;

    /**
     * @param float $baseValue
     * @return CustomOrderFeeInterface
     */
    public function setBaseValue(float $baseValue): CustomOrderFeeInterface;

    /**
     * @return float
     */
    public function getBaseValue(): float;

    /**
     * @param float $value
     * @return CustomOrderFeeInterface
     */
    public function setValue(float $value): CustomOrderFeeInterface;

    /**
     * @return float
     */
    public function getValue(): float;

    /**
     * @return Phrase
     */
    public function formatLabel(string $prefix = ''): Phrase;

    /**
     * @return array
     * @phpstan-return CustomOrderFeeData
     */
    // phpcs:ignore PHPCompatibility.FunctionNameRestrictions.ReservedFunctionNames.MethodDoubleUnderscore
    public function __toArray();
}
