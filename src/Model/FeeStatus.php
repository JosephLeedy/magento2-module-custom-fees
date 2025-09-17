<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Model;

use Magento\Framework\Phrase;

enum FeeStatus: int
{
    case Disabled = 0;
    case Enabled = 1;

    public function label(): Phrase
    {
        return match ($this) {
            self::Disabled => __('Disabled'),
            self::Enabled => __('Enabled'),
        };
    }

    public function equals(string|int|self $feeStatus): bool
    {
        if (!($feeStatus instanceof self)) {
            $feeStatus = self::tryFrom((int) $feeStatus);
        }

        return $feeStatus === $this;
    }
}
