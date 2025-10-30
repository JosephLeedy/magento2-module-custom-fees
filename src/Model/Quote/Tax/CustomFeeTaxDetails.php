<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Model\Quote\Tax;

use JosephLeedy\CustomFees\Api\Data\CustomFeeTaxDetailsInterface;
use Magento\Framework\Api\AbstractSimpleObject;

class CustomFeeTaxDetails extends AbstractSimpleObject implements CustomFeeTaxDetailsInterface
{
    public function setValueWithTax(float $valueWithTax): CustomFeeTaxDetailsInterface
    {
        $this->setData(self::VALUE_WITH_TAX, $valueWithTax);

        return $this;
    }

    public function getValueWithTax(): float
    {
        return (float) $this->_get(self::VALUE_WITH_TAX);
    }

    public function setTaxAmount(float $taxAmount): CustomFeeTaxDetailsInterface
    {
        $this->setData(self::TAX_AMOUNT, $taxAmount);

        return $this;
    }

    public function getTaxAmount(): float
    {
        return (float) $this->_get(self::TAX_AMOUNT);
    }
}
