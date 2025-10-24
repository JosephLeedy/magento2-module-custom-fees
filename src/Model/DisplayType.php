<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Model;

use Magento\Framework\Phrase;

use function __;

enum DisplayType: int
{
    case ExcludingTax = 1;
    case IncludingTax = 2;
    case Both = 3;

    public function label(): Phrase
    {
        return match ($this) {
            self::ExcludingTax => __('Excluding Tax'),
            self::IncludingTax => __('Including Tax'),
            self::Both => __('Both'),
        };
    }

    public function equals(string|int|self $displayType): bool
    {
        if (!($displayType instanceof self)) {
            $displayType = self::tryFrom((int) $displayType);
        }

        return $displayType === $this;
    }
}
