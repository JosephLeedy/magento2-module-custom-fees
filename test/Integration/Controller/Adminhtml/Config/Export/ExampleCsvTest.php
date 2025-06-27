<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Controller\Adminhtml\Config\Export;

use Magento\Framework\App\Area;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\TestCase\AbstractBackendController;

#[AppArea(Area::AREA_ADMINHTML)]
final class ExampleCsvTest extends AbstractBackendController
{
    protected $uri = 'backend/custom_fees/config/export_exampleCsv';

    public function testExportsExampleCustomFeesCsvFile(): void
    {
        $this->dispatch($this->uri);

        /** @var HttpResponse $response */
        $response = $this->getResponse();

        self::assertEquals(302, $response->getHttpResponseCode());
    }
}
