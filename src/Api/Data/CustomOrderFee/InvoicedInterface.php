<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Api\Data\CustomOrderFee;

use JosephLeedy\CustomFees\Api\Data\CustomOrderFeeInterface;
use JosephLeedy\CustomFees\Model\FeeType;
use Magento\Framework\App\State;

interface InvoicedInterface extends CustomOrderFeeInterface
{
    public const INVOICE_ID = 'invoice_id';

    /**
     * @phpstan-param array{}|array{
     *     invoice_id: int,
     *     code: string,
     *     title: string,
     *     type: value-of<FeeType>,
     *     percent: float|null,
     *     show_percentage: bool,
     *     base_value: float,
     *     value: float,
     * } $data
     */
    public function __construct(State $state, array $data = []);

    /**
     * @param int $invoiceId
     * @return InvoicedInterface
     */
    public function setInvoiceId(int $invoiceId): InvoicedInterface;

    /**
     * @return int
     */
    public function getInvoiceId(): int;
}
