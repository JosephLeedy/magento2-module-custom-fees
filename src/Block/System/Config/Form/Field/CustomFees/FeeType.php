<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Block\System\Config\Form\Field\CustomFees;

use JosephLeedy\CustomFees\Model\FeeType as FeeTypeEnum;
use Magento\Framework\View\Element\Html\Select;

use function array_map;

/**
 * @method self setName(string $name)
 */
class FeeType extends Select
{
    public function setInputName(string $inputName): self
    {
        return $this->setName($inputName);
    }

    protected function _toHtml(): string
    {
        $options = array_map(
            static fn(FeeTypeEnum $feeType): array => [
                'value' => $feeType->value,
                'label' => $feeType->label(),
            ],
            FeeTypeEnum::cases(),
        );

        $this->setOptions($options);

        return parent::_toHtml();
    }
}
