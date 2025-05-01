<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Block\Adminhtml\CustomOrderFees;

use Magento\Backend\Block\Widget\Grid\Container;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\HTTP\PhpEnvironment\Request;

use function __;

class Report extends Container
{
    protected function _construct()
    {
        $this->_blockGroup = 'JosephLeedy_CustomFees';
        $this->_controller = 'adminhtml_customOrderFees_report';
        $this->_headerText = (string) __('Total Custom Order Fees Report');

        parent::_construct();

        $this->buttonList->remove('add');
        $this->addButton(
            'filter_form_submit',
            [
                'label' => __('Show Report'),
                'onclick' => 'filterFormSubmit()',
                'class' => 'primary',
            ],
        );
    }

    public function getFilterUrl(): string
    {
        /** @var RequestInterface&Request $request */
        $request = $this->getRequest();

        $request->setParam('filter', null);

        return $this->getUrl('*/*/customOrderFees', ['_current' => true]);
    }
}
