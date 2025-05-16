<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Block\System\Config\Form\Field\CustomFees\Advanced;

use Magento\Framework\View\Element\Template;

class Modal extends Template
{
    protected function _prepareLayout(): self
    {
        $this->getLayout()->addBlock(
            Form::class,
            'custom_fees_advanced_form_' . $this->getRowId(),
            $this->getNameInLayout(),
        );

        return parent::_prepareLayout();
    }
}
