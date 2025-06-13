<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Block\System\Config\Form\Field\CustomFees;

use Magento\Framework\View\Element\Html\Select;

/**
 * @method self setName(string $name)
 */
class FeeType extends Select
{
    public function setInputName(string $inputName): self
    {
        return $this->setName($inputName);
    }

    protected function _toHtml(): string
    {
        $options = [
            [
                'value' => 'fixed',
                'label' => __('Fixed'),
            ],
            [
                'value' => 'percent',
                'label' => __('Percent'),
            ],
        ];

        $this->setOptions($options);

        return parent::_toHtml();
    }
}
