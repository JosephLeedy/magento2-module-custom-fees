<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Model\CustomOrderFee;

use JosephLeedy\CustomFees\Api\Data\CustomOrderFee\InvoicedInterface;
use JosephLeedy\CustomFees\Metadata\PropertyType;
use JosephLeedy\CustomFees\Model\CustomOrderFee;

/**
 * Invoiced custom order fee data model
 *
 * @phpstan-method CustomInvoiceFeeData jsonSerialize()
 */
class Invoiced extends CustomOrderFee implements InvoicedInterface
{
    #[PropertyType('int')]
    public function setInvoiceId(?int $invoiceId): static
    {
        $this->setData(self::INVOICE_ID, $invoiceId);

        return $this;
    }

    public function getInvoiceId(): ?int
    {
        return $this->_get(self::INVOICE_ID);
    }
}
