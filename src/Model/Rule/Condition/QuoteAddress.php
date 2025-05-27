<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Model\Rule\Condition;

use Magento\Directory\Model\Config\Source\Allregion;
use Magento\Directory\Model\Config\Source\Country;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Phrase;
use Magento\Rule\Model\Condition\AbstractCondition;
use Magento\Rule\Model\Condition\Context;
use Magento\Shipping\Model\Config\Source\Allmethods;

use function __;

class QuoteAddress extends AbstractCondition
{
    /**
     * @param mixed[] $data
     */
    public function __construct(
        Context $context,
        private readonly Country $allCountries,
        private readonly Allregion $allRegions,
        private readonly Allmethods $allShippingMethods,
        array $data = [],
    ) {
        parent::__construct($context, $data);
    }

    public function loadAttributeOptions(): self
    {
        $attributes = [
            'base_subtotal' => __('Subtotal'),
            'base_subtotal_total_incl_tax' => __('Subtotal (Incl. Tax)'),
            'total_qty' => __('Total Items Quantity'),
            'weight' => __('Total Weight'),
            'shipping_method' => __('Shipping Method'),
            'postcode' => __('Shipping Postcode'),
            'region' => __('Shipping Region'),
            'region_id' => __('Shipping State/Province'),
            'country_id' => __('Shipping Country'),
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
            'base_subtotal', 'base_subtotal_total_incl_tax', 'weight', 'total_qty' => 'numeric',
            'shipping_method', 'country_id', 'region_id' => 'select',
            default => 'string',
        };
    }

    public function getValueElementType(): string
    {
        return match ($this->getAttribute()) {
            'shipping_method', 'country_id', 'region_id' => 'select',
            default => 'text',
        };
    }

    /**
     * @return array{
     *     country_id?: array{value: string, label: Phrase},
     *     region_id?: array{value: string, label: Phrase},
     *     shipping_method?: array{value: string, label: Phrase}
     * }
     */
    public function getValueSelectOptions(): array
    {
        if ($this->hasData('value_select_options')) {
            return $this->getData('value_select_options');
        }

        $options = match ($this->getAttribute()) {
            'country_id' => $this->allCountries->toOptionArray(),
            'region_id' => $this->allRegions->toOptionArray(),
            'shipping_method' => $this->allShippingMethods->toOptionArray(),
            default => [],
        };

        $this->setData('value_select_options', $options);

        return $this->getData('value_select_options');
    }
}
