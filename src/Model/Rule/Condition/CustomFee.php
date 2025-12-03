<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Model\Rule\Condition;

use JosephLeedy\CustomFees\Api\ConfigInterface;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFeeInterface;
use JosephLeedy\CustomFees\Model\FeeStatus;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Phrase;
use Magento\Quote\Model\Quote\Item;
use Magento\Rule\Model\Condition\AbstractCondition;
use Magento\Rule\Model\Condition\Context;
use Magento\SalesRule\Model\Rule;

use function __;
use function array_filter;
use function array_map;
use function array_values;
use function usort;

/**
 * @method setAttributeOption(array<string, Phrase> $attributes)
 * @method Rule getRule()
 * @method string getAttribute()
 * @method string getValue()
 */
class CustomFee extends AbstractCondition
{
    /**
     * @param mixed[] $data
     */
    public function __construct(Context $context, private readonly ConfigInterface $config, array $data = [])
    {
        parent::__construct($context, $data);
    }

    public function loadAttributeOptions(): self
    {
        $attributes = [
            'custom_fee' => __('Custom Fee'),
        ];

        $this->setAttributeOption($attributes);

        return $this;
    }

    public function loadOperatorOptions(): self
    {
        $this->setOperatorOption(
            [
                '==' => __('is'),
                '!=' => __('is not'),
            ],
        );

        return $this;
    }

    public function getInputType(): string
    {
        return 'select';
    }

    /**
     * @return array<int, array{value: string, label: Phrase}>
     */
    public function getValueSelectOptions(): array
    {
        if ($this->hasData('value_select_options')) {
            return $this->getData('value_select_options');
        }

        $customFeeOptions = array_values(
            array_filter(
                array_map(
                    static function (array $customFee): array {
                        if (FeeStatus::Disabled->equals($customFee['status'])) {
                            return [];
                        }

                        return [
                            'value' => $customFee['code'],
                            'label' => __($customFee['title']),
                        ];
                    },
                    $this->config->getCustomFees(),
                ),
            ),
        );

        usort($customFeeOptions, static fn(array $a, array $b): int => (string) $a['label'] <=> (string) $b['label']);

        $this->setData('value_select_options', $customFeeOptions);

        return $this->getData('value_select_options');
    }

    public function getAttributeElement(): AbstractElement
    {
        /** @var AbstractElement $element */
        $element = parent::getAttributeElement();

        $element->setShowAsText(true);

        return $element;
    }

    public function getValueElementType(): string
    {
        return 'select';
    }

    public function validate(AbstractModel $model): bool
    {
        if ($model instanceof Item) {
            return false; // This rule is not valid for cart items
        }

        /** @var CustomOrderFeeInterface $customFee */
        $customFee = $model->getCustomFee();
        $isFeeApplicable = $this->getValue() === $customFee->getCode();

        return $this->getOperator() === '==' ? $isFeeApplicable : !$isFeeApplicable;
    }
}
