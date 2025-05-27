<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Block\System\Config\Form\Field\CustomFees\Advanced;

use JosephLeedy\CustomFees\Model\Rule\CustomFees as CustomFeesRule;
use JosephLeedy\CustomFees\Model\Rule\CustomFeesFactory as CustomFeesRuleFactory;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Form\Generic;
use Magento\Backend\Block\Widget\Form\Renderer\Fieldset;
use Magento\Framework\Data\FormFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Rule\Block\Conditions;

use function __;
use function array_key_exists;

/**
 * @method self setRowId(string $rowId)
 * @method string getRowId()
 * @method string|null getAdvancedConfig()
 */
class Form extends Generic
{
    /**
     * @param mixed[] $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        FormFactory $formFactory,
        private readonly CustomFeesRuleFactory $customFeesRuleFactory,
        private readonly SerializerInterface $serializer,
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

        $this->createConditionsFieldset($advancedForm);

        return parent::_prepareForm();
    }

    private function createConditionsFieldset(\Magento\Framework\Data\Form $advancedForm): void
    {
        $newChildUrl = $this->getUrl(
            'custom_fees/system_config_customFees_advanced/newConditionHtml/form/conditions_fieldset',
            [
                'form_namespace' => 'system_config_custom_fees_advanced_form',
            ],
        );
        $renderer = $this->getLayout()->createBlock(Fieldset::class);
        $conditionsFieldset = $advancedForm
            ->addFieldset(
                'conditions_container',
                [
                    'legend' => __('Conditions'),
                    'class' => 'admin__scope-old',
                ],
            )->setCollapsable(true);
        $conditionsApplyToFieldset = $conditionsFieldset
            ->addFieldset(
                'conditions_fieldset',
                [
                    'legend' => __('Apply the rule only if the following conditions are met:'),
                ],
            );
        /** @var Fieldset $renderer */
        $renderer
            ->setTemplate(
                'JosephLeedy_CustomFees::system/config/form/field/custom_fees/advanced/form/fieldset/conditions.phtml',
            )->setNewChildUrl($newChildUrl)
            ->setFieldsetId('conditions_fieldset');

        $conditionsApplyToFieldset->setRenderer($renderer);
        $conditionsApplyToFieldset
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
            )->setRule($this->createCustomFeesRule())
            ->setRenderer($this->conditions);

        $this->setForm($advancedForm);
    }

    /**
     * @return array{
     *     conditions?: array{
     *         type: class-string,
     *         aggregator: string,
     *         value: '0'|'1',
     *         conditions: array<
     *             int,
     *             array{
     *                 type: class-string,
     *                 attribute: string,
     *                 operator: string,
     *                 value: string,
     *             }
     *         >
     *     }
     * }
     */
    private function getConfig(): array
    {
        $config = $this->getAdvancedConfig() ?? '{}';

        return $this->serializer->unserialize($config) ?: [];
    }

    private function createCustomFeesRule(): CustomFeesRule
    {
        /** @var CustomFeesRule $customFeesRule */
        $customFeesRule = $this->customFeesRuleFactory->create();
        $config = $this->getConfig();

        if (!array_key_exists('conditions', $config)) {
            return $customFeesRule;
        }

        $customFeesRule->getConditions()->setConditions([])->loadArray($config['conditions']);

        return $customFeesRule;
    }
}
