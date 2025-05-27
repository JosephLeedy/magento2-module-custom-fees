<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Block\System\Config\Form\Field\CustomFees;

use Magento\Framework\View\Element\Template;

class Advanced extends Template
{
    protected $_template = 'JosephLeedy_CustomFees::system/config/form/field/custom_fees/advanced.phtml';

    public function getRowId(): string
    {
        /** @var string $inputId */
        $inputId = $this->getData('input_id');

        return str_replace('_advanced', '', $inputId);
    }

    public function getButtonId(): string
    {
        return 'custom-fees-advanced-button' . $this->getRowId();
    }

    public function getModalId(): string
    {
        return 'custom-fees-advanced-modal' . $this->getRowId();
    }
}
