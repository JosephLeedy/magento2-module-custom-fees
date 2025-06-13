<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Plugin\Quote\Api\Data;

use JosephLeedy\CustomFees\Api\ConfigInterface;
use JosephLeedy\CustomFees\Model\Config;
use JosephLeedy\CustomFees\Plugin\Quote\Api\Data\TotalsInterfacePlugin;
use Magento\Framework\App\Area;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Interception\PluginList\PluginList;
use Magento\Framework\View\Result\Layout;
use Magento\Quote\Api\Data\TotalSegmentInterface;
use Magento\Quote\Api\Data\TotalsInterface;
use Magento\Quote\Model\Cart\TotalSegment;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\Config as ConfigFixture;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;

use function is_array;

#[AppIsolation(true)]
#[AppArea(Area::AREA_FRONTEND)]
final class TotalsInterfacePluginTest extends TestCase
{
    public function testIsConfiguredCorrectly(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var PluginList $pluginList */
        $pluginList = $objectManager->create(PluginList::class);
        /** @var array{add_custom_fees_total_segment?: array{sortOrder: int, instance: class-string}} $plugins */
        $plugins = $pluginList->get(TotalsInterface::class, []);

        self::assertArrayHasKey('add_custom_fees_total_segment', $plugins);
        self::assertSame(TotalsInterfacePlugin::class, $plugins['add_custom_fees_total_segment']['instance']);
    }

    #[ConfigFixture(
        ConfigInterface::CONFIG_PATH_CUSTOM_FEES,
        '[{"code":"test_fee_0","title":"Test Fee","value":"4.00"},{"code":"test_fee_1",'
         . '"title":"Another Fee","value":"1.00"}]',
        ScopeInterface::SCOPE_STORE,
        'default',
    )]
    public function testAddsCustomFeesTotalSegment(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var TotalSegmentInterface $testCustomFeeTotalSegment0 */
        $testCustomFeeTotalSegment0 = $objectManager->create(TotalSegmentInterface::class);
        /** @var TotalSegmentInterface $testCustomFeeTotalSegment1 */
        $testCustomFeeTotalSegment1 = $objectManager->create(TotalSegmentInterface::class);
        /** @var TotalsInterface $totals */
        $totals = $objectManager->create(TotalsInterface::class);
        /** @var Layout $layout */
        $layout = $objectManager->get(Layout::class);

        $testCustomFeeTotalSegment0->setCode('test_fee_0');
        $testCustomFeeTotalSegment0->setTitle('Test Fee 0');
        $testCustomFeeTotalSegment0->setValue('4.00');
        $testCustomFeeTotalSegment0->setArea();

        $testCustomFeeTotalSegment1->setCode('test_fee_1');
        $testCustomFeeTotalSegment1->setTitle('Test Fee 1');
        $testCustomFeeTotalSegment1->setValue('1.00');
        $testCustomFeeTotalSegment1->setArea();

        $totals->setTotalSegments(
            [
                'test_fee_0' => $testCustomFeeTotalSegment0,
                'test_fee_1' => $testCustomFeeTotalSegment1,
            ],
        );

        $layout->getLayout()->getUpdate()->addHandle('hyva_checkout');

        $totalSegments = $totals->getTotalSegments();

        self::assertNotNull($totalSegments);
        self::assertArrayHasKey('custom_fees', $totalSegments);
        self::assertEquals(
            [
                'test_fee_0' => $testCustomFeeTotalSegment0,
                'test_fee_1' => $testCustomFeeTotalSegment1,
            ],
            $totalSegments['custom_fees']->getExtensionAttributes()?->getCustomFeeSegments(),
        );
    }

    /**
     * @dataProvider doesNotAddCustomFeesTotalSegmentDataProvider
     */
    public function testDoesNotAddCustomFeesTotalSegment(string $layoutHandle, ?array $totalSegmentData): void
    {
        $totalSegments = null;
        $objectManager = Bootstrap::getObjectManager();
        /** @var Layout $layout */
        $layout = $objectManager->get(Layout::class);
        /** @var TotalsInterface $totals */
        $totals = $objectManager->create(TotalsInterface::class);

        $layout->getLayout()->getUpdate()->addHandle($layoutHandle);

        if (is_array($totalSegmentData)) {
            $totalSegments = [];

            foreach ($totalSegmentData as $totalSegmentCode => $totalSegmentDatum) {
                /** @var TotalSegmentInterface&TotalSegment $totalSegment */
                $totalSegment = $objectManager->create(TotalSegmentInterface::class);

                $totalSegment->setData($totalSegmentDatum);

                $totalSegments[$totalSegmentCode] = $totalSegment;
            }
        }

        $totals->setTotalSegments($totalSegments);

        $expectedTotalSegments = $totalSegments;
        $actualTotalSegments = $totals->getTotalSegments();

        self::assertSame($expectedTotalSegments, $actualTotalSegments);
    }

    public function testDoesNotAddCustomFeesTotalSegmentIfGetCustomFeesConfigurationThrowsException(): void
    {
        $configStub = $this->createStub(ConfigInterface::class);
        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var LocalizedException $localizedException */
        $localizedException = $objectManager->create(
            LocalizedException::class,
            [
                'phrase' => __(
                    'Could not get custom fees from configuration. Error: "%1"',
                    'Could not unserialize JSON string',
                ),
            ],
        );
        /** @var TotalsInterface $totals */
        $totals = $objectManager->create(TotalsInterface::class);
        /** @var Layout $layout */
        $layout = $objectManager->get(Layout::class);

        $configStub
            ->method('getCustomFees')
            ->willThrowException($localizedException);

        $objectManager->configure(
            [
                Config::class => [
                    'shared' => true,
                ],
            ],
        );
        $objectManager->addSharedInstance($configStub, Config::class);

        $layout->getLayout()->getUpdate()->addHandle('hyva_checkout');

        $totals->setTotalSegments(
            [
                'subtotal' => [
                    'code' => 'subtotal',
                    'title' => 'Subtotal',
                    'value' => '20.00',
                    'area' => 'footer',
                ],
            ],
        );

        $actualTotalSegments = $totals->getTotalSegments();

        self::assertArrayNotHasKey('custom_fees', $actualTotalSegments);
    }

    public function doesNotAddCustomFeesTotalSegmentDataProvider(): array
    {
        return [
            'no custom fees' => [
                'layoutHandle' => 'hyva_checkout',
                'totalSegmentData' => null,
            ],
            'not in Hyvä Checkout' => [
                'layoutHandle' => 'checkout',
                'totalSegmentData' => [
                    'subtotal' => [
                        'code' => 'subtotal',
                        'title' => 'Subtotal',
                        'value' => '20.00',
                        'area' => 'footer',
                    ],
                ],
            ],
            'custom fees total segment already exists' => [
                'layoutHandle' => 'hyva_checkout',
                'totalSegmentData' => [
                    'custom_fees' => [
                        'code' => 'custom_fees',
                        'title' => 'Custom Fees',
                        'value' => '0.00',
                        'area' => null,
                    ],
                ],
            ],
        ];
    }
}
