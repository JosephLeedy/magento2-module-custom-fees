<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Block\System\Config\Form\Field\CustomFees;

use Magento\Framework\Phrase;
use Magento\Framework\View\Element\AbstractBlock;

use function __;
use function sprintf;

/**
 * @method string getName()
 * @method string getInputId()
 * @method array{
 *      label: Phrase|string,
 *      class: string|null,
 *      size: false|string,
 *      style: string|null,
 *      renderer: false|AbstractBlock
 *  } getColumn()
 */
class FeeStatus extends AbstractBlock
{
    public function setInputName(string $inputName): self
    {
        return $this->setName($inputName);
    }

    protected function _toHtml(): string
    {
        $column = $this->getColumn();
        $classAttribute = '';

        if ($column['class'] !== null) {
            $classAttribute = ' class="' . $column['class'] . '"';
        }

        $html = sprintf(
            '<input type="hidden" id="%s" name="%s" value="<%%- status.length > 0 ? status : \'1\' %%>">',
            $this->getInputId(),
            $this->getName(),
        );
        $html .= sprintf(
            '<input type="checkbox" id="%s"%s title="%s"'
                . ' onclick="document.getElementById(\'%s\').value = this.checked ? 1 : 0;"'
                . '<%%= typeof(status) === "undefined" || status.length === 0 || status == "1" ? " checked=\"checked\""'
                . ' : "" %%>>',
            $this->getInputId() . '_toggle',
            $classAttribute,
            __('Enable'),
            $this->getInputId(),
        );

        return $html;
    }
}
