<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Block\System\Config\Form\Field;

use DomainException;
use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;

use function __;

class CustomFees extends AbstractFieldArray
{
    protected function _prepareToRender(): void
    {
        $store = $this->getStore();
        $baseCurrency = $store?->getBaseCurrency()->getCurrencyCode() ?? '';
        $valueColumnLabel = (string)__('Fee Amount');

        if ($baseCurrency !== '') {
            $valueColumnLabel .= ' (' . $baseCurrency . ')';
        }

        $this->addColumn(
            'code',
            [
                'label' => __('Code'),
                'class' => 'required-entry validate-code'
            ]
        );
        $this->addColumn(
            'title',
            [
                'label' => __('Fee Name'),
                'class' => 'required-entry'
            ]
        );
        $this->addColumn(
            'value',
            [
                'label' => $valueColumnLabel,
                'class' => 'required-entry validate-number validate-zero-or-greater'
            ]
        );

        $this->_addAfter = false;
        $this->_addButtonLabel = (string)__('Add Custom Fee');
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
}
