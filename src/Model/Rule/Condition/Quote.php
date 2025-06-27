<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Model\Rule\Condition;

use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Model\AbstractModel;
use Magento\Quote\Model\Quote\Item;
use Magento\Rule\Model\Condition\AbstractCondition;

use function __;

class Quote extends AbstractCondition
{
    public function loadAttributeOptions(): self
    {
        $attributes = [
            'items_qty' => __('Total Items Quantity'),
        ];

        $this->setAttributeOption($attributes);

        return $this;
    }

    public function getAttributeElement(): AbstractElement
    {
        /** @var AbstractElement $element */
        $element = parent::getAttributeElement();

        $element->setShowAsText(true);

        return $element;
    }

    public function getInputType(): string
    {
        return match ($this->getAttribute()) {
            'items_qty' => 'numeric',
            default => 'string',
        };
    }

    public function getValueElementType(): string
    {
        return match ($this->getAttribute()) {
            default => 'text',
        };
    }

    public function validate(AbstractModel $model): bool
    {
        if ($model instanceof Item) {
            $model = $model->getQuote();
        }

        return parent::validate($model);
    }
}
