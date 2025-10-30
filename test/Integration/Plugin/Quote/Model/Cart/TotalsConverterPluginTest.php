<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Plugin\Quote\Model\Cart;

use JosephLeedy\CustomFees\Api\ConfigInterface;
use JosephLeedy\CustomFees\Api\Data\CustomFeeTaxDetailsInterface;
use JosephLeedy\CustomFees\Plugin\Quote\Model\Cart\TotalsConverterPlugin;
use Magento\Framework\Interception\PluginList\PluginList;
use Magento\Quote\Api\Data\TotalSegmentExtension;
use Magento\Quote\Api\Data\TotalSegmentInterface;
use Magento\Quote\Model\Cart\TotalsConverter;
use Magento\Quote\Model\Quote\Address\Total as AddressTotal;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

final class TotalsConverterPluginTest extends TestCase
{
    public function testIsConfiguredCorrectly(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var PluginList $pluginList */
        $pluginList = $objectManager->create(PluginList::class);
        /** @var array{add_custom_fee_taxes?: array{sortOrder: int, instance: class-string}} $plugins */
        $plugins = $pluginList->get(TotalsConverter::class, []);

        self::assertArrayHasKey('add_custom_fee_taxes', $plugins);
        self::assertSame(TotalsConverterPlugin::class, $plugins['add_custom_fee_taxes']['instance'] ?? null);
    }

    #[Config(
        ConfigInterface::CONFIG_PATH_CUSTOM_FEES,
        '[{"code":"test_fee_0","title":"Test Fee","type":"fixed","value":"5.00","status":"1"},{"code":"test_fee_1",'
        . '"title":"Another Fee","type":"fixed","value":"1.50","status":"1"}]',
        ScopeInterface::SCOPE_STORE,
        'default',
    )]
    public function testAddsCustomFeeTaxesToTotalSegments(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var TotalsConverter $totalsConverter */
        $totalsConverter = $objectManager->create(TotalsConverter::class);
        /** @var array<string, AddressTotal> $addressTotals */
        $addressTotals = [
            'test_fee_0' => $objectManager->create(
                AddressTotal::class,
                [
                    'data' => [
                        'code' => 'test_fee_0',
                        'value' => '5.00',
                        'tax_details' => [
                            'value_with_tax' => '5.25',
                            'tax_amount' => '0.25',
                        ],
                    ],
                ],
            ),
            'test_fee_1' => $objectManager->create(
                AddressTotal::class,
                [
                    'data' => [
                        'code' => 'test_fee_1',
                        'value' => '1.50',
                        'tax_details' => [
                            'value_with_tax' => '1.58',
                            'tax_amount' => '0.08',
                        ],
                    ],
                ],
            ),
        ];

        /** @var array<string, TotalSegmentInterface> $expectedTotalSegments */
        $expectedTotalSegments = [
            'test_fee_0' => $objectManager->create(TotalSegmentInterface::class),
            'test_fee_1' => $objectManager->create(TotalSegmentInterface::class),
        ];

        /* Set the expected data using `setData()` rather than in the constructor to produce objects identical to the
           actual results. */
        $expectedTotalSegments['test_fee_0']->setData(
            [
                'code' => 'test_fee_0',
                'title' => '',
                'value' => '5.00',
                'area' => null,
                'extension_attributes' => $objectManager->create(
                    TotalSegmentExtension::class,
                    [
                        'data' => [
                            'custom_fee_tax_details' => $objectManager->create(
                                CustomFeeTaxDetailsInterface::class,
                                [
                                    'data' => [
                                        'value_with_tax' => '5.25',
                                        'tax_amount' => '0.25',
                                    ],
                                ],
                            ),
                        ],
                    ],
                ),
            ],
        );
        $expectedTotalSegments['test_fee_1']->setData(
            [
                'code' => 'test_fee_1',
                'title' => '',
                'value' => '1.50',
                'area' => null,
                'extension_attributes' => $objectManager->create(
                    TotalSegmentExtension::class,
                    [
                        'data' => [
                            'custom_fee_tax_details' => $objectManager->create(
                                CustomFeeTaxDetailsInterface::class,
                                [
                                    'data' => [
                                        'value_with_tax' => '1.58',
                                        'tax_amount' => '0.08',
                                    ],
                                ],
                            ),
                        ],
                    ],
                ),
            ],
        );

        $actualTotalSegments = $totalsConverter->process($addressTotals);

        self::assertEquals($expectedTotalSegments, $actualTotalSegments);
    }

    #[Config(
        ConfigInterface::CONFIG_PATH_CUSTOM_FEES,
        '[]',
        ScopeInterface::SCOPE_STORE,
        'default',
    )]
    public function testDoesNotAddCustomFeeTaxesToTotalSegmentsIfNoCustomFeesAreConfigured(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var TotalsConverter $totalsConverter */
        $totalsConverter = $objectManager->create(TotalsConverter::class);
        /** @var array<string, AddressTotal> $addressTotals */
        $addressTotals = [
            'test_fee_0' => $objectManager->create(
                AddressTotal::class,
                [
                    'data' => [
                        'code' => 'test_fee_0',
                        'value' => '5.00',
                        'tax_details' => [
                            'value_with_tax' => '5.25',
                            'tax_amount' => '0.25',
                        ],
                    ],
                ],
            ),
            'test_fee_1' => $objectManager->create(
                AddressTotal::class,
                [
                    'data' => [
                        'code' => 'test_fee_1',
                        'value' => '1.50',
                        'tax_details' => [
                            'value_with_tax' => '1.58',
                            'tax_amount' => '0.08',
                        ],
                    ],
                ],
            ),
        ];

        $actualTotalSegments = $totalsConverter->process($addressTotals);

        self::assertNull($actualTotalSegments['test_fee_0']->getExtensionAttributes()?->getCustomFeeTaxDetails());
        self::assertNull($actualTotalSegments['test_fee_1']->getExtensionAttributes()?->getCustomFeeTaxDetails());
    }

    #[Config(
        ConfigInterface::CONFIG_PATH_CUSTOM_FEES,
        '[{"code":"test_fee_0","title":"Test Fee","type":"fixed","value":"5.00","status":"1"},{"code":"test_fee_1",'
        . '"title":"Another Fee","type":"fixed","value":"1.50","status":"1"}]',
        ScopeInterface::SCOPE_STORE,
        'default',
    )]
    public function testDoesNotAddCustomFeeTaxesToTotalSegmentsIfNoCustomFeeTotalsExist(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var TotalsConverter $totalsConverter */
        $totalsConverter = $objectManager->create(TotalsConverter::class);
        /** @var array<string, AddressTotal> $addressTotals */
        $addressTotals = [
            'subtotal' => $objectManager->create(
                AddressTotal::class,
                [
                    'data' => [
                        'code' => 'subtotal',
                        'value' => '50.00',
                    ],
                ],
            ),
            'grand_total' => $objectManager->create(
                AddressTotal::class,
                [
                    'data' => [
                        'code' => 'grand_total',
                        'value' => '50.00',
                    ],
                ],
            ),
        ];

        $actualTotalSegments = $totalsConverter->process($addressTotals);

        self::assertNull($actualTotalSegments['subtotal']->getExtensionAttributes()?->getCustomFeeTaxDetails());
        self::assertNull($actualTotalSegments['grand_total']->getExtensionAttributes()?->getCustomFeeTaxDetails());
    }

    #[Config(
        ConfigInterface::CONFIG_PATH_CUSTOM_FEES,
        '[{"code":"test_fee_0","title":"Test Fee","type":"fixed","value":"5.00","status":"1"},{"code":"test_fee_1",'
        . '"title":"Another Fee","type":"fixed","value":"1.50","status":"1"}]',
        ScopeInterface::SCOPE_STORE,
        'default',
    )]
    public function testDoesNotAddCustomFeeTaxesToTotalSegmentsIfCustomFeeTotalsDoNotHaveTaxDetails(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var TotalsConverter $totalsConverter */
        $totalsConverter = $objectManager->create(TotalsConverter::class);
        /** @var array<string, AddressTotal> $addressTotals */
        $addressTotals = [
            'test_fee_0' => $objectManager->create(
                AddressTotal::class,
                [
                    'data' => [
                        'code' => 'test_fee_0',
                        'value' => '5.00',
                    ],
                ],
            ),
            'test_fee_1' => $objectManager->create(
                AddressTotal::class,
                [
                    'data' => [
                        'code' => 'test_fee_1',
                        'value' => '1.50',
                    ],
                ],
            ),
        ];

        $actualTotalSegments = $totalsConverter->process($addressTotals);

        self::assertNull($actualTotalSegments['test_fee_0']->getExtensionAttributes()?->getCustomFeeTaxDetails());
        self::assertNull($actualTotalSegments['test_fee_1']->getExtensionAttributes()?->getCustomFeeTaxDetails());
    }
}
