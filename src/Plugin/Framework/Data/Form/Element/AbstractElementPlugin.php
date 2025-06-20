<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Plugin\Framework\Data\Form\Element;

use Magento\Framework\Data\Form\Element\AbstractElement;

class AbstractElementPlugin
{
    public function afterGetElementHtml(AbstractElement $subject, string $result): string
    {
        if (
            !$subject->hasData('data-form-part')
            || $subject->getData('data-form-part') !== 'system_config_custom_fees_advanced_form'
            || !$subject->hasData('comment')
        ) {
            return $result;
        }

        $result .= '<p class="note"><span>' . $subject->getData('comment') . "</span></p>\n";

        $subject->unsetData('comment');

        return $result;
    }
}
