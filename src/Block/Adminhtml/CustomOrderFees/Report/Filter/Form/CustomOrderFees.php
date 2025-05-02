<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Block\Adminhtml\CustomOrderFees\Report\Filter\Form;

use Magento\Reports\Block\Adminhtml\Filter\Form;

class CustomOrderFees extends Form
{
    protected function _prepareForm(): self
    {
        parent::_prepareForm();

        $form = $this->getForm();
        $fieldset = $form->getElement('base_fieldset');

        $form->setData('action', $this->getUrl('*/*/customOrderFees'));

        if ($fieldset === null) {
            return $this;
        }

        $fieldset->removeField('report_type');
        $fieldset->addField(
            'show_base_amount',
            'select',
            [
                'name' => 'show_base_amount',
                'options' => [
                    '0' => __('No'),
                    '1' => __('Yes'),
                ],
                'label' => __('Show Base Amount'),
            ],
        );

        return $this;
    }
}
