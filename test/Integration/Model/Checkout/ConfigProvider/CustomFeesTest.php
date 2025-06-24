<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Model\Checkout\ConfigProvider;

use Magento\Checkout\Model\CompositeConfigProvider;
use Magento\Checkout\Model\DefaultConfigProvider;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;

final class CustomFeesTest extends TestCase
{
    /**
     * @magentoAppArea frontend
     * @phpcs:ignore Generic.Files.LineLength.TooLong
     * @magentoConfigFixture current_store sales/custom_order_fees/custom_fees [{"code":"test_fee_0","title":"Test Fee","type":"fixed","value":"4.00","advanced":"{\"show_percentage\":\"0\"}"},{"code":"test_fee_1","title":"Another Fee","type":"fixed","value":"1.00","advanced":"{\"show_percentage\":\"0\"}"}]
     */
    public function testProvidesCustomFeesConfig(): void
    {
        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        $defaultConfigProviderMock = $this->createMock(DefaultConfigProvider::class);
        /* The above mock is a work-around for a bug in `\Magento\Checkout\Model\DefaultConfigProvider::getConfig()`
           which does not check for a valid quote prior to executing its logic. */

        $objectManager->addSharedInstance($defaultConfigProviderMock, DefaultConfigProvider::class);

        /** @var CompositeConfigProvider $compositeConfigProvider */
        $compositeConfigProvider = $objectManager->create(CompositeConfigProvider::class);

        $defaultConfigProviderMock->method('getConfig')
            ->willReturn([]);

        $providedConfig = $compositeConfigProvider->getConfig();

        self::assertArrayHasKey('customFees', $providedConfig);
        self::assertEquals(
            [
                'codes' => [
                    'test_fee_0',
                    'test_fee_1',
                ],
            ],
            $providedConfig['customFees'],
        );
    }
}
