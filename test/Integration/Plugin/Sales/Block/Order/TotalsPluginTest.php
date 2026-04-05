<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Plugin\Sales\Block\Order;

use JosephLeedy\CustomFees\Api\Data\CustomOrderFeesInterface;
use JosephLeedy\CustomFees\Block\Sales\Order\Totals as CustomFeesTotalsBlock;
use JosephLeedy\CustomFees\Plugin\Sales\Block\Order\TotalsPlugin;
use Magento\Framework\App\Area;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Interception\PluginList\PluginList;
use Magento\Framework\View\Layout\ProcessorInterface;
use Magento\Framework\View\LayoutInterface;
use Magento\Sales\Api\Data\OrderExtension;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Block\Order\Totals;
use Magento\Sales\Model\Order;
use Magento\Tax\Block\Sales\Order\Tax;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

use function __;
use function array_keys;

#[AppIsolation(true)]
#[AppArea(Area::AREA_FRONTEND)]
final class TotalsPluginTest extends TestCase
{
    public function testIsConfiguredCorrectly(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var PluginList $pluginList */
        $pluginList = $objectManager->create(PluginList::class);
        /**
         * @var array{
         *     reorder_custom_fees_total_segments_in_hyva?: array{sortOrder: int, instance: class-string}
         * } $plugins
         */
        $plugins = $pluginList->get(Totals::class, []);

        self::assertArrayHasKey('reorder_custom_fees_total_segments_in_hyva', $plugins);
        self::assertSame(TotalsPlugin::class, $plugins['reorder_custom_fees_total_segments_in_hyva']['instance']);
    }

    public function testReordersCustomFeesTotalSegments(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var Totals $totalsBlock */
        $totalsBlock = $objectManager->create(Totals::class);
        $layout = $totalsBlock->getLayout();
        /** @var CustomFeesTotalsBlock $customFeesTotalsBlock */
        $customFeesTotalsBlock = $objectManager->create(CustomFeesTotalsBlock::class);
        /** @var Tax $taxTotalsBlock */
        $taxTotalsBlock = $objectManager->create(Tax::class);
        /** @var CustomOrderFeesInterface $customOrderFees */
        $customOrderFees = $objectManager->create(
            CustomOrderFeesInterface::class,
            [
                'data' => [
                    'custom_fees_ordered' => [
                        'test_fee_0' => [
                            'code' => 'test_fee_0',
                            'title' => 'Test Fee',
                            'type' => 'fixed',
                            'percent' => null,
                            'show_percentage' => false,
                            'base_value' => 4.00,
                            'value' => 4.00,
                            'base_value_with_tax' => 4.00,
                            'value_with_tax' => 4.00,
                            'base_tax_amount' => 0.00,
                            'tax_amount' => 0.00,
                            'tax_rate' => 0.00,
                        ],
                        'test_fee_1' => [
                            'code' => 'test_fee_1',
                            'title' => 'Another Fee',
                            'type' => 'fixed',
                            'percent' => null,
                            'show_percentage' => false,
                            'base_value' => 1.00,
                            'value' => 1.00,
                            'base_value_with_tax' => 1.00,
                            'value_with_tax' => 1.00,
                            'base_tax_amount' => 0.00,
                            'tax_amount' => 0.00,
                            'tax_rate' => 0.00,
                        ],
                    ],
                ],
            ],
        );
        /** @var OrderExtension $orderExtensionAttributes */
        $orderExtensionAttributes = $objectManager->create(
            OrderExtension::class,
            [
                'data' => [
                    'custom_order_fees' => $customOrderFees,
                ],
            ],
        );
        /** @var OrderInterface&Order $order */
        $order = $objectManager->create(
            OrderInterface::class,
            [
                'data' => [
                    'base_subtotal' => 19.99,
                    'subtotal' => 19.99,
                    'base_grand_total' => 24.99,
                    'grand_total' => 24.99,
                    'tax_amount' => 5.00,
                    'extension_attributes' => $orderExtensionAttributes,
                ],
            ],
        );

        $layout->getUpdate()->addHandle('hyva_sales_order_view');
        $layout->addBlock($totalsBlock, 'order_totals');
        $layout->addBlock($customFeesTotalsBlock, 'custom_fees', 'order_totals');
        $layout->addBlock($taxTotalsBlock, 'tax', 'order_totals');

        $totalsBlock->setOrder($order);
        $totalsBlock->toHtml();

        $totals = $totalsBlock->getTotals();
        $expectedTotalCodes = [
            'subtotal',
            'tax',
            'test_fee_0',
            'test_fee_1',
            'grand_total',
        ];
        $actualTotalCodes = array_keys($totals);

        self::assertSame($expectedTotalCodes, $actualTotalCodes);
    }

    public function testDoesNotReorderCustomFeesTotalSegmentsIfNoTotalsExist(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var Totals $totalsBlock */
        $totalsBlock = $objectManager->create(Totals::class);

        $totals = $totalsBlock->getTotals();

        self::assertNull($totals);
    }

    public function testDoesNotReorderCustomFeesTotalSegmentsIfTaxTotalNotPresent(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var Totals $totalsBlock */
        $totalsBlock = $objectManager->create(Totals::class);
        $layout = $totalsBlock->getLayout();
        /** @var CustomFeesTotalsBlock $customFeesTotalsBlock */
        $customFeesTotalsBlock = $objectManager->create(CustomFeesTotalsBlock::class);
        /** @var CustomOrderFeesInterface $customOrderFees */
        $customOrderFees = $objectManager->create(
            CustomOrderFeesInterface::class,
            [
                'data' => [
                    'custom_fees_ordered' => [
                        'test_fee_0' => [
                            'code' => 'test_fee_0',
                            'title' => 'Test Fee',
                            'type' => 'fixed',
                            'percent' => null,
                            'show_percentage' => false,
                            'base_value' => 4.00,
                            'value' => 4.00,
                            'base_value_with_tax' => 4.00,
                            'value_with_tax' => 4.00,
                            'base_tax_amount' => 0.00,
                            'tax_amount' => 0.00,
                            'tax_rate' => 0.00,
                        ],
                        'test_fee_1' => [
                            'code' => 'test_fee_1',
                            'title' => 'Another Fee',
                            'type' => 'fixed',
                            'percent' => null,
                            'show_percentage' => false,
                            'base_value' => 1.00,
                            'value' => 1.00,
                            'base_value_with_tax' => 1.00,
                            'value_with_tax' => 1.00,
                            'base_tax_amount' => 0.00,
                            'tax_amount' => 0.00,
                            'tax_rate' => 0.00,
                        ],
                    ],
                ],
            ],
        );
        /** @var OrderExtension $orderExtensionAttributes */
        $orderExtensionAttributes = $objectManager->create(
            OrderExtension::class,
            [
                'data' => [
                    'custom_order_fees' => $customOrderFees,
                ],
            ],
        );
        /** @var OrderInterface&Order $order */
        $order = $objectManager->create(
            OrderInterface::class,
            [
                'data' => [
                    'base_subtotal' => 19.99,
                    'subtotal' => 19.99,
                    'base_grand_total' => 24.99,
                    'grand_total' => 24.99,
                    'extension_attributes' => $orderExtensionAttributes,
                ],
            ],
        );

        $layout->getUpdate()->addHandle('hyva_sales_order_view');
        $layout->addBlock($totalsBlock, 'order_totals');
        $layout->addBlock($customFeesTotalsBlock, 'custom_fees', 'order_totals');

        $totalsBlock->setOrder($order);
        $totalsBlock->toHtml();

        $totals = $totalsBlock->getTotals();
        $expectedTotalCodes = [
            'subtotal',
            'test_fee_0',
            'test_fee_1',
            'grand_total',
        ];
        $actualTotalCodes = array_keys($totals);

        self::assertSame($expectedTotalCodes, $actualTotalCodes);
    }

    public function testDoesNotReorderCustomFeesTotalSegmentsIfRetrievingLayoutHandlesThrowsException(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var Totals $totalsBlock */
        $totalsBlock = $objectManager->create(Totals::class);
        $layout = $totalsBlock->getLayout();
        /** @var CustomFeesTotalsBlock $customFeesTotalsBlock */
        $customFeesTotalsBlock = $objectManager->create(CustomFeesTotalsBlock::class);
        /** @var Tax $taxTotalsBlock */
        $taxTotalsBlock = $objectManager->create(Tax::class);
        /** @var CustomOrderFeesInterface $customOrderFees */
        $customOrderFees = $objectManager->create(
            CustomOrderFeesInterface::class,
            [
                'data' => [
                    'custom_fees_ordered' => [
                        'test_fee_0' => [
                            'code' => 'test_fee_0',
                            'title' => 'Test Fee',
                            'type' => 'fixed',
                            'percent' => null,
                            'show_percentage' => false,
                            'base_value' => 4.00,
                            'value' => 4.00,
                            'base_value_with_tax' => 4.00,
                            'value_with_tax' => 4.00,
                            'base_tax_amount' => 0.00,
                            'tax_amount' => 0.00,
                            'tax_rate' => 0.00,
                        ],
                        'test_fee_1' => [
                            'code' => 'test_fee_1',
                            'title' => 'Another Fee',
                            'type' => 'fixed',
                            'percent' => null,
                            'show_percentage' => false,
                            'base_value' => 1.00,
                            'value' => 1.00,
                            'base_value_with_tax' => 1.00,
                            'value_with_tax' => 1.00,
                            'base_tax_amount' => 0.00,
                            'tax_amount' => 0.00,
                            'tax_rate' => 0.00,
                        ],
                    ],
                ],
            ],
        );
        /** @var OrderExtension $orderExtensionAttributes */
        $orderExtensionAttributes = $objectManager->create(
            OrderExtension::class,
            [
                'data' => [
                    'custom_order_fees' => $customOrderFees,
                ],
            ],
        );
        /** @var OrderInterface&Order $order */
        $order = $objectManager->create(
            OrderInterface::class,
            [
                'data' => [
                    'base_subtotal' => 19.99,
                    'subtotal' => 19.99,
                    'base_grand_total' => 24.99,
                    'grand_total' => 24.99,
                    'extension_attributes' => $orderExtensionAttributes,
                    'tax_amount' => 5.00,
                ],
            ],
        );
        $getLayoutHandlesException = $objectManager->create(
            LocalizedException::class,
            [
                'phrase' => __('Could not retrieve layout handles.'),
            ],
        );
        $updateStub = $this->createStub(ProcessorInterface::class);
        $layoutStub = $this->createStub(LayoutInterface::class);

        $layout->getUpdate()->addHandle('hyva_sales_order_view');
        $layout->addBlock($totalsBlock, 'order_totals');
        $layout->addBlock($customFeesTotalsBlock, 'custom_fees', 'order_totals');
        $layout->addBlock($taxTotalsBlock, 'tax', 'order_totals');

        $totalsBlock->setOrder($order);
        $totalsBlock->toHtml();

        $updateStub->method('getHandles')->willThrowException($getLayoutHandlesException);

        $layoutStub->method('getUpdate')->willReturn($updateStub);

        $totalsBlock->setLayout($layoutStub);

        $totals = $totalsBlock->getTotals();
        $expectedTotalCodes = [
            'subtotal',
            'test_fee_0',
            'test_fee_1',
            'tax',
            'grand_total',
        ];
        $actualTotalCodes = array_keys($totals);

        self::assertSame($expectedTotalCodes, $actualTotalCodes);
    }

    public function testDoesNotReorderCustomFeesTotalSegmentsIfLayoutHandleIsNotHyvaHandle(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var Totals $totalsBlock */
        $totalsBlock = $objectManager->create(Totals::class);
        $layout = $totalsBlock->getLayout();
        /** @var CustomFeesTotalsBlock $customFeesTotalsBlock */
        $customFeesTotalsBlock = $objectManager->create(CustomFeesTotalsBlock::class);
        /** @var Tax $taxTotalsBlock */
        $taxTotalsBlock = $objectManager->create(Tax::class);
        /** @var CustomOrderFeesInterface $customOrderFees */
        $customOrderFees = $objectManager->create(
            CustomOrderFeesInterface::class,
            [
                'data' => [
                    'custom_fees_ordered' => [
                        'test_fee_0' => [
                            'code' => 'test_fee_0',
                            'title' => 'Test Fee',
                            'type' => 'fixed',
                            'percent' => null,
                            'show_percentage' => false,
                            'base_value' => 4.00,
                            'value' => 4.00,
                            'base_value_with_tax' => 4.00,
                            'value_with_tax' => 4.00,
                            'base_tax_amount' => 0.00,
                            'tax_amount' => 0.00,
                            'tax_rate' => 0.00,
                        ],
                        'test_fee_1' => [
                            'code' => 'test_fee_1',
                            'title' => 'Another Fee',
                            'type' => 'fixed',
                            'percent' => null,
                            'show_percentage' => false,
                            'base_value' => 1.00,
                            'value' => 1.00,
                            'base_value_with_tax' => 1.00,
                            'value_with_tax' => 1.00,
                            'base_tax_amount' => 0.00,
                            'tax_amount' => 0.00,
                            'tax_rate' => 0.00,
                        ],
                    ],
                ],
            ],
        );
        /** @var OrderExtension $orderExtensionAttributes */
        $orderExtensionAttributes = $objectManager->create(
            OrderExtension::class,
            [
                'data' => [
                    'custom_order_fees' => $customOrderFees,
                ],
            ],
        );
        /** @var OrderInterface&Order $order */
        $order = $objectManager->create(
            OrderInterface::class,
            [
                'data' => [
                    'base_subtotal' => 19.99,
                    'subtotal' => 19.99,
                    'base_grand_total' => 24.99,
                    'grand_total' => 24.99,
                    'extension_attributes' => $orderExtensionAttributes,
                    'tax_amount' => 5.00,
                ],
            ],
        );

        $layout->getUpdate()->addHandle('sales_order_view');
        $layout->addBlock($totalsBlock, 'order_totals');
        $layout->addBlock($customFeesTotalsBlock, 'custom_fees', 'order_totals');
        $layout->addBlock($taxTotalsBlock, 'tax', 'order_totals');

        $totalsBlock->setOrder($order);
        $totalsBlock->toHtml();

        $totals = $totalsBlock->getTotals();
        $expectedTotalCodes = [
            'subtotal',
            'test_fee_0',
            'test_fee_1',
            'tax',
            'grand_total',
        ];
        $actualTotalCodes = array_keys($totals);

        self::assertSame($expectedTotalCodes, $actualTotalCodes);
    }

    /**
     * @dataProvider orderDoesNotHaveCustomFeesDataProvider
     */
    public function testDoesNotReorderCustomFeesTotalSegmentsIfOrderDoesNotHaveCustomFees(
        bool $hasCustomOrderFees,
    ): void {
        $objectManager = Bootstrap::getObjectManager();
        /** @var OrderInterface&Order $order */
        $order = $objectManager->create(
            OrderInterface::class,
            [
                'data' => [
                    'base_subtotal' => 19.99,
                    'subtotal' => 19.99,
                    'base_grand_total' => 24.99,
                    'grand_total' => 24.99,
                    'tax_amount' => 5.00,
                ],
            ],
        );
        /** @var Totals $totalsBlock */
        $totalsBlock = $objectManager->create(Totals::class);
        $layout = $totalsBlock->getLayout();
        /** @var Tax $taxTotalsBlock */
        $taxTotalsBlock = $objectManager->create(Tax::class);

        if ($hasCustomOrderFees) {
            $customFees = [
                'test_fee_0' => [
                    'code' => 'test_fee_0',
                    'title' => 'Test Fee',
                    'type' => 'fixed',
                    'percent' => null,
                    'show_percentage' => false,
                    'base_value' => 4.00,
                    'value' => 4.00,
                    'base_value_with_tax' => 4.00,
                    'value_with_tax' => 4.00,
                    'base_tax_amount' => 0.00,
                    'tax_amount' => 0.00,
                    'tax_rate' => 0.00,
                ],
                'test_fee_1' => [
                    'code' => 'test_fee_1',
                    'title' => 'Another Fee',
                    'type' => 'fixed',
                    'percent' => null,
                    'show_percentage' => false,
                    'base_value' => 1.00,
                    'value' => 1.00,
                    'base_value_with_tax' => 1.00,
                    'value_with_tax' => 1.00,
                    'base_tax_amount' => 0.00,
                    'tax_amount' => 0.00,
                    'tax_rate' => 0.00,
                ],
            ];

            /** @var CustomOrderFeesInterface $customOrderFees */
            $customOrderFees = $objectManager->create(
                CustomOrderFeesInterface::class,
                [
                    'data' => [
                        'custom_fees_ordered' => $customFees,
                    ],
                ],
            );
            /** @var OrderExtension $orderExtensionAttributes */
            $orderExtensionAttributes = $objectManager->create(
                OrderExtension::class,
                [
                    'data' => [
                        'custom_order_fees' => $customOrderFees,
                    ],
                ],
            );

            $order->setExtensionAttributes($orderExtensionAttributes);
        }

        $layout->getUpdate()->addHandle('hyva_sales_order_view');
        $layout->addBlock($totalsBlock, 'order_totals');
        $layout->addBlock($taxTotalsBlock, 'tax', 'order_totals');

        $totalsBlock->setOrder($order);
        $totalsBlock->toHtml();

        $totals = $totalsBlock->getTotals();
        $expectedTotalCodes = [
            'subtotal',
            'tax',
            'grand_total',
        ];
        $actualTotalCodes = array_keys($totals);

        self::assertSame($expectedTotalCodes, $actualTotalCodes);
    }

    /**
     * @return array<string, array<string, bool>>
     */
    public function orderDoesNotHaveCustomFeesDataProvider(): array
    {
        return [
            'no custom order fees' => [
                'hasCustomOrderFees' => false,
            ],
            'no custom fees totals' => [
                'hasCustomOrderFees' => true,
            ],
        ];
    }
}
