<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Block\System\Config\Form\Field;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

use function __;
use function str_replace;

class ImportCustomFees extends Field
{
    protected function _getElementHtml(AbstractElement $element): string
    {
        $elementHtml = '<label class="import-fees-label" for="' . $element->getHtmlId() . '">'
            . __('Import Custom Fees') . "</span></label>\n";
        $elementHtml .= parent::_getElementHtml($element);
        $elementHtml .= $this->renderReplaceExistingCheckbox($element);

        return $elementHtml;
    }

    protected function _renderValue(AbstractElement $element)
    {
        $element->setComment(
            str_replace(
                '{{exampleCsvUrl}}',
                $this->_urlBuilder->getUrl('custom_fees/config/export_exampleCsv'),
                (string) $element->getComment(),
            ),
        );

        return parent::_renderValue($element);
    }

    protected function _isInheritCheckboxRequired(AbstractElement $element)
    {
        return false;
    }

    private function renderReplaceExistingCheckbox(AbstractElement $element): string
    {
        $label = __('Replace Existing Custom Fees');
        $disabledAttribute = ((bool) $element->getData('disabled')) ? 'disabled' : '';
        $inputName = str_replace('[value]', '[replace_existing]', (string) $element->getName());

        return <<<HTML
        <div>
            <input
                type="checkbox"
                name="$inputName"
                value="1"
                class="checkbox"
                id="{$element->getHtmlId()}_replace_existing"
                $disabledAttribute
            />
            <label for="{$element->getHtmlId()}_replace_existing" class="$disabledAttribute">$label</label>
        </div>
        HTML;
    }
}
