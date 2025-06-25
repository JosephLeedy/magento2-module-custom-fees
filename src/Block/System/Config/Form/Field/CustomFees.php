<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Block\System\Config\Form\Field;

use DomainException;
use JosephLeedy\CustomFees\Block\System\Config\Form\Field\CustomFees\Advanced;
use JosephLeedy\CustomFees\Block\System\Config\Form\Field\CustomFees\FeeType;
use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;

use function __;

class CustomFees extends AbstractFieldArray
{
    private FeeType $feeTypeFieldRenderer;

    protected function _prepareToRender(): void
    {
        $store = $this->getStore();
        $baseCurrency = $store?->getBaseCurrency()->getCurrencyCode() ?? '';
        $valueColumnLabel = (string) __('Amount');

        if ($baseCurrency !== '') {
            $valueColumnLabel .= ' (' . $baseCurrency . ')';
        }

        $this->addColumn(
            'code',
            [
                'label' => __('Code'),
                'class' => 'required-entry validate-code',
            ],
        );
        $this->addColumn(
            'title',
            [
                'label' => __('Name'),
                'class' => 'required-entry',
            ],
        );
        $this->addColumn(
            'type',
            [
                'label' => __('Type'),
                'class' => 'required-entry',
                'renderer' => $this->getFeeTypeFieldRenderer(),
            ],
        );
        $this->addColumn(
            'value',
            [
                'label' => $valueColumnLabel,
                'class' => 'required-entry validate-number validate-zero-or-greater',
            ],
        );
        $this->addColumn(
            'advanced',
            [
                'label' => '&nbsp;',
                'renderer' => $this->getLayout()->createBlock(Advanced::class),
            ],
        );

        $this->_addAfter = false;
        $this->_addButtonLabel = (string) __('Add Custom Fee');
    }

    protected function _prepareArrayRow(DataObject $row): void
    {
        if (!$row->hasData('advanced')) {
            $row->setData('advanced', '{}');
        }

        if (!$row->hasData('type')) {
            $row->setData('type', \JosephLeedy\CustomFees\Model\FeeType::Fixed->value);
        }

        /** @var string $feeType */
        $feeType = $row->getData('type');
        $optionsExtraAttributes = [
            "option_{$this->getFeeTypeFieldRenderer()->calcOptionHash($feeType)}" => 'selected="selected"',
        ];

        $row->setData('option_extra_attrs', $optionsExtraAttributes);
    }

    private function getStore(): ?StoreInterface
    {
        /** @var int $storeId */
        $storeId = $this->_request->getParam('store');

        if ($storeId !== null) {
            try {
                $store = $this->_storeManager->getStore($storeId);
            } catch (NoSuchEntityException) {
                $store = null;
            }
        } else {
            /** @var int $websiteId */
            $websiteId = $this->_request->getParam('website', 0);

            try {
                $store = $this->_storeManager->getWebsite($websiteId)->getDefaultStore();
            } catch (NoSuchEntityException | DomainException) {
                $store = null;
            }
        }

        return $store;
    }

    private function getFeeTypeFieldRenderer(): FeeType
    {
        if (isset($this->feeTypeFieldRenderer)) {
            return $this->feeTypeFieldRenderer;
        }

        /** @var FeeType $feeTypeFieldRenderer */
        $feeTypeFieldRenderer = $this
            ->getLayout()
            ->createBlock(
                FeeType::class,
                '',
                [
                    'data' => [
                        'is_render_to_js_template' => true,
                    ],
                ],
            );
        $this->feeTypeFieldRenderer = $feeTypeFieldRenderer;

        return $this->feeTypeFieldRenderer;
    }
}
