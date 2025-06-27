<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Model\Rule\Condition;

use Magento\Framework\Phrase;
use Magento\Rule\Model\Condition\Combine as CoreCombineConditionModel;
use Magento\Rule\Model\Condition\Context;

use function __;
use function array_keys;
use function array_map;
use function array_merge;
use function array_merge_recursive;
use function array_values;

/**
 * @method self setType(string $class)
 * @phpstan-method self setType(class-string $class)
 */
class Combine extends CoreCombineConditionModel
{
    /**
     * @param mixed[] $data
     */
    public function __construct(
        Context $context,
        private readonly Product $productCondition,
        private readonly Quote $quoteCondition,
        private readonly QuoteAddress $quoteAddressCondition,
        array $data = [],
    ) {
        parent::__construct($context, $data);

        $this->setType(self::class);
    }

    /**
     * @return array<int, array{value: class-string|array<int, array{value: string, label: Phrase}>, label: Phrase}>
     */
    public function getNewChildSelectOptions(): array
    {
        /** @var array<string, Phrase> $productAttributeOptions */
        $productAttributeOptions = $this->productCondition->loadAttributeOptions()->getAttributeOption();
        $productAttributes = array_map(
            static fn(string $code, Phrase|string $label): array => [
                'value' => Product::class . '|' . $code,
                'label' => $label,
            ],
            array_keys($productAttributeOptions),
            array_values($productAttributeOptions),
        );
        /** @var array<string, Phrase> $quoteAttributesOptions */
        $quoteAttributesOptions = $this->quoteCondition->loadAttributeOptions()->getAttributeOption();
        $quoteAttributes = array_map(
            static fn(string $code, Phrase $label): array => [
                'value' => Quote::class . '|' . $code,
                'label' => $label,
            ],
            array_keys($quoteAttributesOptions),
            array_values($quoteAttributesOptions),
        );
        /** @var array<string, Phrase> $quoteAddressAttributesOptions */
        $quoteAddressAttributesOptions = $this->quoteAddressCondition->loadAttributeOptions()->getAttributeOption();
        $quoteAddressAttributes = array_map(
            static fn(string $code, Phrase $label): array => [
                'value' => QuoteAddress::class . '|' . $code,
                'label' => $label,
            ],
            array_keys($quoteAddressAttributesOptions),
            array_values($quoteAddressAttributesOptions),
        );
        $conditions = array_merge_recursive(
            parent::getNewChildSelectOptions(),
            [
                [
                    'value' => self::class,
                    'label' => __('Conditions Combination'),
                ],
                [
                    'value' => $productAttributes,
                    'label' => __('Product Attribute'),
                ],
                [
                    'value' => array_merge($quoteAttributes, $quoteAddressAttributes),
                    'label' => __('Cart Attribute'),
                ],
            ],
        );

        return $conditions;
    }
}
