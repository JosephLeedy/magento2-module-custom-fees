<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Block\System\Config\Form\Field\CustomFees;

use JosephLeedy\CustomFees\Block\System\Config\Form\Field\CustomFees\Advanced\Form;
use JosephLeedy\CustomFees\Model\Rule\CustomFeesFactory as CustomFeesRuleFactory;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

use function str_replace;

class Advanced extends Template
{
    protected $_template = 'JosephLeedy_CustomFees::system/config/form/field/custom_fees/advanced.phtml';

    /**
     * @param mixed[] $data
     */
    public function __construct(
        Context $context,
        private readonly CustomFeesRuleFactory $customFeesRuleFactory,
        private readonly Registry $registry,
        array $data = [],
    ) {
        parent::__construct($context, $data);
    }

    public function getRowId(): string
    {
        /** @var string $inputId */
        $inputId = $this->getData('input_id') ?? '';

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

    /*protected function _prepareLayout(): self
    {
        $this->getLayout()->setChild($this->getNameInLayout(), 'system_config_custom_fees_advanced_form', '');

        parent::_prepareLayout();

        return $this;
    }*/

    private function populateConditionsModels(): void
    {
        /** @var Form $advancedFormBlock */
        $advancedFormBlock = $this->getLayout()->getBlock('system_config_custom_fees_advanced_form');
        $customFeesRule = $this->customFeesRuleFactory->create();

        // TODO: Populate rule model with data from config

        $advancedFormBlock->setRowId($this->getRowId());
        $advancedFormBlock->setRuleModel($customFeesRule);

        //$this->registry->register('current_custom_fee_rule', $customFeesRule);
    }
}
