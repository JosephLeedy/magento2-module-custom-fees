<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Model;

use JosephLeedy\CustomFees\Api\Data\FeeTypeInterface;
use Magento\Framework\Phrase;

use function __;

enum FeeType: string implements FeeTypeInterface
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

    public function equals(string|FeeTypeInterface $feeType): bool
    {
        if (!($feeType instanceof FeeTypeInterface)) {
            $feeType = self::tryFrom($feeType);
        }

        return $feeType === $this;
    }
}
