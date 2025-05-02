<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Controller\Adminhtml\Report\CustomOrderFees\Export;

use JosephLeedy\CustomFees\Controller\Adminhtml\Report\CustomOrderFees;
use Magento\Framework\App\Area;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\TestCase\AbstractBackendController;

#[AppArea(Area::AREA_ADMINHTML)]
final class ExcelTest extends AbstractBackendController
{
    protected $resource = CustomOrderFees::ADMIN_RESOURCE;
    protected $uri = 'backend/custom_fees/report/customOrderFees_export_excel';

    public function testExportsReportAsExcelXml(): void
    {
        $this->dispatch($this->uri);

        /** @var HttpResponse $response */
        $response = $this->getResponse();

        self::assertEquals(302, $response->getHttpResponseCode());
    }
}
