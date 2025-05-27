<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Controller\Adminhtml\System\Config\CustomFees\Advanced;

use Magento\Framework\App\Area;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Exception\LocalizedException;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\TestCase\AbstractBackendController;

use function __;

#[AppArea(Area::AREA_ADMINHTML)]
final class FormTest extends AbstractBackendController
{
    public function testReturnsRawFormHtml(): void
    {
        $this
            ->getRequest()
            ->setMethod('POST')
            ->setPostValue(
                [
                    'row_id' => '_1748378523777_777',
                ],
            );

        $this->dispatch('backend/custom_fees/system_config_customFees_advanced/form');

        /** @var HttpResponse $response */
        $response = $this->getResponse();

        self::assertEquals(200, $response->getHttpResponseCode());
        self::assertStringContainsString('<div class="entry-edit form-inline">', $response->getBody());
    }

    public function testThrowsExceptionIfRowIdIsNotProvided(): void
    {
        $objectManager = $this->_objectManager;
        $rowIdException = $objectManager->create(
            LocalizedException::class,
            [
                'phrase' => __('Row ID is required.'),
            ],
        );

        $this->expectExceptionObject($rowIdException);

        $this
            ->getRequest()
            ->setMethod('POST');

        $this->dispatch('backend/custom_fees/system_config_customFees_advanced/form');

        $this->getResponse();
    }
}
