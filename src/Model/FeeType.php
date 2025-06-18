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
}
