<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Block\System\Config\Form\Field\CustomFees\Advanced;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Form\Generic;
use Magento\Backend\Block\Widget\Form\Renderer\Fieldset;
use Magento\Framework\Data\FormFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Registry;
use Magento\Rule\Block\Conditions;

use function __;

class Form extends Generic
{
    /**
     * @param mixed[] $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        FormFactory $formFactory,
        private readonly Conditions $conditions,
        array $data = [],
    ) {
        parent::__construct($context, $registry, $formFactory, $data);
    }

    protected function _prepareForm(): self
    {
        try {
            $advancedForm = $this->_formFactory->create();
        } catch (LocalizedException) {
            return $this;
        }

        $newChildUrl = $this->getUrl(
            'system_config_customFees_advanced/newConditionHtml/form/conditions_fieldset',
            [
                'form_namespace' => 'system_config_custom_fees_advanced_form',
            ],
        );
        $renderer = $this->getLayout()->createBlock(Fieldset::class);
        $conditionsFieldset = $advancedForm->addFieldset('conditions_fieldset', []);
        $model = $this->_coreRegistry->registry('current_custom_fee_rule');

        /** @var Fieldset $renderer */
        $renderer
            ->setTemplate(
                'JosephLeedy_CustomFees::system/config/form/field/custom_fees/advanced/form/fieldset/conditions.phtml',
            )
            ->setNewChildUrl($newChildUrl)
            ->setFieldSetId('conditions_fieldset');

        $conditionsFieldset->setRenderer($renderer);
        $conditionsFieldset
            ->addField(
                'conditions',
                'text',
                [
                    'name' => 'conditions',
                    'label' => __('Conditions'),
                    'title' => __('Conditions'),
                    'required' => false,
                    'data-form-part' => 'system_config_custom_fees_advanced_form',
                ],
            )
            ->setRule($model)
            ->setRenderer($this->conditions);

        $this->setForm($advancedForm);

        return parent::_prepareForm();
    }
}
