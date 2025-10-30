<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Api\Data;

interface CustomFeeTaxDetailsInterface
{
    public const VALUE_WITH_TAX = 'value_with_tax';
    public const TAX_AMOUNT = 'tax_amount';

    /**
     * @param float $valueWithTax
     * @return CustomFeeTaxDetailsInterface
     */
    public function setValueWithTax(float $valueWithTax): CustomFeeTaxDetailsInterface;

    /**
     * @return float
     */
    public function getValueWithTax(): float;

    /**
     * @param float $taxAmount
     * @return CustomFeeTaxDetailsInterface
     */
    public function setTaxAmount(float $taxAmount): CustomFeeTaxDetailsInterface;

    /**
     * @return float
     */
    public function getTaxAmount(): float;
}
