<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Api\Data\CustomOrderFee;

use JosephLeedy\CustomFees\Api\Data\CustomOrderFeeInterface;

interface InvoicedInterface extends CustomOrderFeeInterface
{
    public const INVOICE_ID = 'invoice_id';

    /**
     * @param int|null $invoiceId
     * @return InvoicedInterface
     */
    public function setInvoiceId(?int $invoiceId): InvoicedInterface;

    /**
     * @return int|null
     */
    public function getInvoiceId(): ?int;

    /**
     * @return array
     * @phpstan-return CustomInvoiceFeeData
     */
    // phpcs:ignore PHPCompatibility.FunctionNameRestrictions.ReservedFunctionNames.MethodDoubleUnderscore
    public function __toArray();
}
