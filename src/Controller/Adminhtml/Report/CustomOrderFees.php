<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Controller\Adminhtml\Report;

use JosephLeedy\CustomFees\Model\ResourceModel\Report\CustomOrderFees as CustomOrderFeesReport;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Helper\Data as BackendHelper;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Stdlib\DateTime\Filter\Date;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Reports\Controller\Adminhtml\Report\AbstractReport;

use function __;

class CustomOrderFees extends AbstractReport
{
    public const ADMIN_RESOURCE = 'JosephLeedy_CustomFees::report_custom_order_fees';

    public function __construct(
        Context $context,
        FileFactory $fileFactory,
        Date $dateFilter,
        TimezoneInterface $timezone,
        private readonly CustomOrderFeesReport $customOrderFeesReport,
        ?BackendHelper $backendHelperData = null,
    ) {
        parent::__construct($context, $fileFactory, $dateFilter, $timezone, $backendHelperData);
    }

    public function execute()
    {
        $this->showDatabaseServerVersionWarning();
        $this->_showLastExecutionTime('report_custom_order_fees_aggregated', 'custom_order_fees');

        $this
            ->_initAction()
            ->_setActiveMenu('JosephLeedy_CustomFees::custom_order_fees_report')
            ->_addBreadcrumb((string) __('Sales'), (string) __('Sales'))
            ->_addBreadcrumb((string) __('Custom Order Fees Report'), (string) __('Custom Order Fees Report'));
        $this
            ->_view
            ->getPage()
            ->getConfig()
            ->getTitle()
            ->prepend((string) __('Custom Order Fees Report'));

        $gridBlock = $this
            ->_view
            ->getLayout()
            ->getBlock('adminhtml_customOrderFees_report.grid');
        $filterFormBlock = $this
            ->_view
            ->getLayout()
            ->getBlock('grid.filter.form');

        $this->_initReportAction([$gridBlock, $filterFormBlock]);
        $this->_view->renderLayout();
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed(self::ADMIN_RESOURCE);
    }

    private function showDatabaseServerVersionWarning(): void
    {
        $databaseServerVersion = $this->customOrderFeesReport->getDatabaseServerVersion();
        $isDatabaseServerCompatible = $this->customOrderFeesReport->isDatabaseServerSupported($databaseServerVersion);

        if ($isDatabaseServerCompatible) {
            return;
        }

        $this->messageManager->addWarningMessage(
            (string) __(
                'This report requires MySQL 8.0.4 or greater, MariaDB 10.6.0 or greater OR a MySQL 8.0-compatible '
                . 'database server to generate properly. Your database server version "%1" is not compatible.',
                $databaseServerVersion,
            ),
        );
    }
}
