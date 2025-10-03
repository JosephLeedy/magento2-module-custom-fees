<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Api\Data;

use Magento\Framework\Phrase;

interface FeeTypeInterface
{
    public function label(): Phrase;

    public function description(): Phrase;

    public function equals(string|FeeTypeInterface $feeType): bool;
}
