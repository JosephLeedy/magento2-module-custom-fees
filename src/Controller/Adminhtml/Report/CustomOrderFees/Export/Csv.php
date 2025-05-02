<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Controller\Adminhtml\Report\CustomOrderFees\Export;

use JosephLeedy\CustomFees\Block\Adminhtml\CustomOrderFees\Report\Grid;
use JosephLeedy\CustomFees\Controller\Adminhtml\Report\CustomOrderFees;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResponseInterface;

class Csv extends CustomOrderFees
{
    public function execute(): ResponseInterface
    {
        $fileName = 'custom-order-fees.csv';
        /** @var Grid $grid */
        $grid = $this->_view->getLayout()->createBlock(Grid::class);

        $this->_initReportAction($grid);

        return $this->_fileFactory->create($fileName, $grid->getCsvFile(), DirectoryList::VAR_DIR);
    }
}
