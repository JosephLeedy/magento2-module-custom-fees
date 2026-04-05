<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Plugin\SalesRule\Model\Rule\Condition\Product;

use JosephLeedy\CustomFees\Model\Rule\Condition\CustomFee;
use Magento\Framework\Phrase;
use Magento\SalesRule\Model\Rule\Condition\Product\Combine;

use function __;

class CombinePlugin
{
    /**
     * @param array<int, array{
     *     label: Phrase,
     *     value: class-string|string|array<int, array{label: Phrase, value: string}>,
     * }> $result
     * @return array<int, array{
     *     label: Phrase,
     *     value: class-string|string|array<int, array{label: Phrase, value: string}>,
     * }>
     */
    public function afterGetNewChildSelectOptions(Combine $subject, array $result): array
    {
        $result[] = [
            'label' => __('Custom Fee'),
            'value' => CustomFee::class . '|custom_fee',
        ];

        return $result;
    }
}
