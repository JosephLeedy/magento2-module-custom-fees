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
    public const BASE_VALUE_WITH_TAX = 'base_value_with_tax';
    public const VALUE_WITH_TAX = 'value_with_tax';
    public const BASE_TAX_AMOUNT = 'base_tax_amount';
    public const TAX_AMOUNT = 'tax_amount';
    public const TAX_RATE = 'tax_rate';

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
     * @param float $baseValueWithTax
     * @return CustomOrderFeeInterface
     */
    public function setBaseValueWithTax(float $baseValueWithTax): CustomOrderFeeInterface;

    /**
     * @return float
     */
    public function getBaseValueWithTax(): float;

    /**
     * @param float $valueWithTax
     * @return CustomOrderFeeInterface
     */
    public function setValueWithTax(float $valueWithTax): CustomOrderFeeInterface;

    /**
     * @return float
     */
    public function getValueWithTax(): float;

    /**
     * @param float $baseTaxAmount
     * @return CustomOrderFeeInterface
     */
    public function setBaseTaxAmount(float $baseTaxAmount): CustomOrderFeeInterface;

    /**
     * @return float
     */
    public function getBaseTaxAmount(): float;

    /**
     * @param float $taxAmount
     * @return CustomOrderFeeInterface
     */
    public function setTaxAmount(float $taxAmount): CustomOrderFeeInterface;

    /**
     * @return float
     */
    public function getTaxAmount(): float;

    /**
     * @param float $taxRate
     * @return CustomOrderFeeInterface
     */
    public function setTaxRate(float $taxRate): CustomOrderFeeInterface;

    /**
     * @return float
     */
    public function getTaxRate(): float;

    /**
     * @return Phrase
     */
    public function formatLabel(string $prefix = '', string $suffix = ''): Phrase;

    /**
     * @return array
     * @phpstan-return CustomOrderFeeData
     */
    // phpcs:ignore PHPCompatibility.FunctionNameRestrictions.ReservedFunctionNames.MethodDoubleUnderscore
    public function __toArray();
}
