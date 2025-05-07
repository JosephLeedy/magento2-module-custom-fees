<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Block\System\Config\Form\Field;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

use function str_replace;

class ImportCustomFees extends Field
{
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
}
