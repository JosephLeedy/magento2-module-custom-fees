<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Model\Rule\Condition;

use Magento\Framework\Phrase;
use Magento\Rule\Model\Condition\Combine as CoreCombineConditionModel;
use Magento\Rule\Model\Condition\Context;

use function __;
use function array_keys;
use function array_map;
use function array_merge_recursive;
use function array_values;

/**
 * @method self setType(string $class)
 */
class Combine extends CoreCombineConditionModel
{
    /**
     * @param mixed[] $data
     */
    public function __construct(Context $context, private readonly Cart $cartCondition, array $data = [])
    {
        parent::__construct($context, $data);

        $this->setType(self::class);
    }

    /**
     * @return array<int, array{value: class-string|array<int, array{value: string, label: Phrase}>, label: Phrase}>
     */
    public function getNewChildSelectOptions(): array
    {
        /** @var array<string, Phrase> $cartAttributesOptions */
        $cartAttributesOptions = $this->cartCondition->loadAttributeOptions()->getAttributeOption();
        $cartAttributes = array_map(
            static fn(string $code, Phrase $label): array => [
                'value' => Cart::class . '|' . $code,
                'label' => $label,
            ],
            array_keys($cartAttributesOptions),
            array_values($cartAttributesOptions),
        );
        $conditions = array_merge_recursive(
            parent::getNewChildSelectOptions(),
            [
                [
                    'value' => self::class,
                    'label' => __('Conditions Combination'),
                ],
                [
                    'value' => $cartAttributes,
                    'label' => __('Cart Attribute'),
                ],
            ],
        );

        return $conditions;
    }
}
