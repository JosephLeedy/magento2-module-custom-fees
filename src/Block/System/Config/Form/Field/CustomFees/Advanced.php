<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Block\System\Config\Form\Field\CustomFees;

use Magento\Framework\View\Element\FormKey;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class Advanced extends Template
{
    protected $_template = 'JosephLeedy_CustomFees::system/config/form/field/custom_fees/advanced.phtml';

    /**
     * @param mixed[] $data
     */
    public function __construct(Context $context, private readonly FormKey $formKey, array $data = [])
    {
        parent::__construct($context, $data);
    }

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

    public function getFormUrl(): string
    {
        return $this->getUrl('custom_fees/system_config_customFees_advanced/form');
    }

    public function getFormKey(): string
    {
        return $this->formKey->getFormKey();
    }
}
