<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Block\Sales\Cart\Totals;

use Magento\Framework\View\Element\Template;

class CustomFees extends Template
{
    protected function _beforeToHtml(): self
    {
        parent::_beforeToHtml();

        if (
            $this->_design->getDesignTheme()->getCode() !== 'Hyva/default-csp'
            && $this->_design->getDesignTheme()->getParentTheme()?->getCode() !== 'Hyva/default-csp'
        ) {
            return $this;
        }

        $this->setTemplate('JosephLeedy_CustomFees::hyva/php-cart/totals/custom_fees-csp.phtml');

        /** @var Template $cspJsBlock */
        $cspJsBlock = $this->getLayout()->addBlock(Template::class, 'custom_fees.js', 'checkout.cart.totals.scripts');

        $cspJsBlock->setTemplate('JosephLeedy_CustomFees::hyva/php-cart/totals/js/custom_fees-js.phtml');

        return $this;
    }
}
