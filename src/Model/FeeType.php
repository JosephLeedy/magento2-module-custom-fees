<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Model;

use Magento\Framework\Phrase;

use function __;

enum FeeType: string
{
    case Fixed = 'fixed';
    case Percent = 'percent';

    public function label(): Phrase
    {
        return match ($this) {
            self::Fixed => __('Fixed'),
            self::Percent => __('Percent'),
        };
    }

    public function description(): Phrase
    {
        return match ($this) {
            self::Fixed => __('Fixed fee amount'),
            self::Percent => __('Percentage of order total'),
        };
    }

    public function equals(string|self $feeType): bool
    {
        if (!($feeType instanceof self)) {
            $feeType = self::tryFrom($feeType);
        }

        return $feeType === $this;
    }
}
