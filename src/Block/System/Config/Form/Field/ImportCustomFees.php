<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Block\System\Config\Form\Field;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class ImportCustomFees extends Field
{
    protected function _isInheritCheckboxRequired(AbstractElement $element)
    {
        return false;
    }
}
