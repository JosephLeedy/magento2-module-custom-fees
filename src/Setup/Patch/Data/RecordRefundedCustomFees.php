<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Setup\Patch\Data;

use JosephLeedy\CustomFees\Service\RefundedCustomFeesRecorder;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class RecordRefundedCustomFees implements DataPatchInterface
{
    public function __construct(private readonly RefundedCustomFeesRecorder $refundedCustomFeesRecorder) {}

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
        $this->refundedCustomFeesRecorder->recordForExistingCreditMemos();

        return $this;
    }
}
