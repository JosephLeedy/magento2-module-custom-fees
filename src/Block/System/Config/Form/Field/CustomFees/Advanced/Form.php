<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Block\System\Config\Form\Field\CustomFees\Advanced;

use InvalidArgumentException;
use JosephLeedy\CustomFees\Model\FeeType;
use JosephLeedy\CustomFees\Model\Rule\CustomFees as CustomFeesRule;
use JosephLeedy\CustomFees\Model\Rule\CustomFeesFactory as CustomFeesRuleFactory;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Form\Generic;
use Magento\Backend\Block\Widget\Form\Renderer\Fieldset;
use Magento\Config\Model\Config\Source\Yesno;
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
 * @method string getFeeType()
 * @phpstan-method value-of<FeeType> getFeeType()
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
        private readonly Yesno $yesNoSource,
        array $data = [],
    ) {
        parent::__construct($context, $registry, $formFactory, $data);
    }

    /**
     * @throws LocalizedException
     */
    protected function _prepareForm(): self
    {
        try {
            $advancedForm = $this->_formFactory->create();
        } catch (LocalizedException) {
            return $this;
        }

        $this->createConditionsFieldset($advancedForm);
        $this->createDisplayFieldset($advancedForm);

        return parent::_prepareForm();
    }

    /**
     * @throws LocalizedException
     */
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
                    'legend' => __('Apply the fee only if the following conditions are met:'),
                ],
            );
        $customFeesRule = $this->createCustomFeesRule();

        $customFeesRule->getConditions()->setFormName('system_config_custom_fees_advanced_form');
        $customFeesRule->getConditions()->setJsFormObject('conditions_fieldset');

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
            )->setRule($customFeesRule)
            ->setRenderer($this->conditions);

        $this->setForm($advancedForm);
    }

    private function createDisplayFieldset(\Magento\Framework\Data\Form $advancedForm): void
    {
        /** @var \Magento\Framework\Data\Form\Element\Fieldset $displayFieldset */
        $displayFieldset = $advancedForm
            ->addFieldset(
                'display_fieldset',
                [
                    'legend' => __('Display'),
                    'class' => 'admin__scope-old',
                ],
            )->setCollapsable(true);
        $config = [
            'name' => 'show_percentage',
            'label' => __('Show Percentage'),
            'title' => __('Show Percentage'),
            'values' => $this->yesNoSource->toOptionArray(),
            'value' => (int) ($this->getConfig()['show_percentage'] ?? true),
            'data-form-part' => 'system_config_custom_fees_advanced_form',
            'note' => __('If "Yes," the fee percentage will be shown next to its name (i.e. "Processing (10%)").'),
        ];

        if (!FeeType::Percent->equals($this->getFeeType())) {
            $config['readonly'] = 'readonly';
        }

        $displayFieldset->addField('show_percentage', 'select', $config);
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
     *     },
     *     show_percentage?: bool,
     * }
     * @throws LocalizedException
     */
    private function getConfig(): array
    {
        $config = $this->getAdvancedConfig();

        if (empty($config)) {
            $config = '{}';
        }

        try {
            return $this->serializer->unserialize($config) ?: [];
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw new LocalizedException(
                __(
                    'Could not process the advanced configuration data for custom fee "%1". Error: "%2"',
                    $this->getRowId(),
                    $invalidArgumentException->getMessage(),
                ),
                $invalidArgumentException,
            );
        }
    }

    /**
     * @throws LocalizedException
     */
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
