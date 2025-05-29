<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Observer;

use Magento\Config\Model\Config\Source\Yesno;
use Magento\Framework\Data\Form;
use Magento\Framework\Data\Form\Element\Fieldset;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;

use function __;

/**
 * Observes the `adminhtml_catalog_product_attribute_edit_frontend_prepare_form` event
 *
 * @see \Magento\Catalog\Block\Adminhtml\Product\Attribute\Edit\Tab\Front::_prepareForm
 */
class AddFieldToProductAttributeEditForm implements ObserverInterface
{
    public function __construct(private readonly Yesno $yesNoSource) {}

    public function execute(Observer $observer)
    {
        $event = $observer->getEvent();
        /** @var Form $form */
        $form = $event->getForm();
        /** @var Fieldset $fieldset */
        $fieldset = $form->getElement('front_fieldset');
        $yesNoValues = $this->yesNoSource->toOptionArray();

        $fieldset->addField(
            'is_used_for_custom_fee_rules',
            'select',
            [
                'name' => 'is_used_for_custom_fee_rules',
                'label' => __('Use for Custom Fee Rule Conditions'),
                'title' => __('Use for Custom Fee Rule Conditions'),
                'values' => $yesNoValues,
            ],
            'is_used_for_promo_rules',
        );
    }
}
