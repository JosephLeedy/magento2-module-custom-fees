<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Controller\Adminhtml\System\Config\CustomFees\Advanced;

use JosephLeedy\CustomFees\Model\Rule\Condition\QuoteAddress;
use Magento\Framework\App\Area;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\TestCase\AbstractBackendController;

#[AppArea(Area::AREA_ADMINHTML)]
final class NewConditionHtmlTest extends AbstractBackendController
{
    public function testReturnsRawFormHtml(): void
    {
        $this
            ->getRequest()
            ->setMethod('POST')
            ->setPostValue(
                [
                    'id' => 42,
                    'form_namespace' => 'system_config_custom_fees_advanced_form',
                    'type' => QuoteAddress::class . '|' . 'weight',
                ],
            );

        $this->dispatch(
            'backend/custom_fees/system_config_customFees_advanced/newConditionHtml/form/conditions_fieldset',
        );

        /** @var HttpResponse $response */
        $response = $this->getResponse();

        self::assertEquals(200, $response->getHttpResponseCode());
        self::assertStringContainsString(
            '<input id="conditions__42__type" name="rule[conditions][42][type]"',
            $response->getBody(),
        );
    }
}
