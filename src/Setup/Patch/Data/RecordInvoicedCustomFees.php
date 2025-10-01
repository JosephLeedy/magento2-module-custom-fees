<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Setup\Patch\Data;

use JosephLeedy\CustomFees\Service\InvoicedCustomFeesRecorder;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class RecordInvoicedCustomFees implements DataPatchInterface
{
    public function __construct(private readonly InvoicedCustomFeesRecorder $invoicedCustomFeesRecorder) {}

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }

    public function apply(): self
    {
        $this->invoicedCustomFeesRecorder->recordForExistingInvoices();

        return $this;
    }
}
