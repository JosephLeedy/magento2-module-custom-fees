<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Model\CustomOrderFee;

use JosephLeedy\CustomFees\Api\Data\CustomOrderFee\InvoicedInterface;
use JosephLeedy\CustomFees\Model\CustomOrderFee;

class Invoiced extends CustomOrderFee implements InvoicedInterface
{
    public function setInvoiceId(int $invoiceId): static
    {
        $this->setData(self::INVOICE_ID, $invoiceId);

        return $this;
    }

    public function getInvoiceId(): int
    {
        return (int) $this->_get(self::INVOICE_ID);
    }
}
