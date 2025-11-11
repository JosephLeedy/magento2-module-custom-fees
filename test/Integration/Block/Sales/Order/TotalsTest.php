<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Block\Sales\Order;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use JosephLeedy\CustomFees\Api\ConfigInterface;
use JosephLeedy\CustomFees\Block\Sales\Order\Totals as CustomOrderFeesTotalsBlock;
use JosephLeedy\CustomFees\Model\DisplayType;
use JosephLeedy\CustomFees\Service\CustomFeesRetriever;
use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Block\Order\Totals as OrderTotalsBlock;
use Magento\Sales\Model\Order;
use Magento\TestFramework\Annotation\DataFixture as DataFixtureAnnotation;
use Magento\TestFramework\Fixture\DataFixture as DataFixtureAttribute;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;
use PHPUnit\Framework\TestCase;

use function __;

final class TotalsTest extends TestCase
{
    use ArraySubsetAsserts;

    /**
     * @dataProvider initTotalsDataProvider
     * @param 'does'|'does not' $condition
     */
    public function testInitTotals(string $condition): void
    {
        $filename = 'order';
        $assertion = 'assertArrayNotHasKey';

        if ($condition === 'does') {
            $filename .= '_with_custom_fees';
            $assertion = 'assertArrayHasKey';
        }

        $resolver = Resolver::getInstance();

        $resolver->setCurrentFixtureType(DataFixtureAnnotation::ANNOTATION);
        $resolver->requireDataFixture("JosephLeedy_CustomFees::../test/Integration/_files/$filename.php");

        /** @var ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        /** @var OrderTotalsBlock $orderTotalsBlock */
        $orderTotalsBlock = $objectManager->create(OrderTotalsBlock::class);
        $customOrderFeesTotalsBlock = $this->getMockBuilder(CustomOrderFeesTotalsBlock::class)
            ->setConstructorArgs(
                [
                    'context' => $objectManager->get(Context::class),
                    'customFeesRetriever' => $objectManager->create(CustomFeesRetriever::class),
                    'dataObjectFactory' => $objectManager->get(DataObjectFactory::class),
                    'config' => $objectManager->create(ConfigInterface::class),
                    'data' => [],
                ],
            )->onlyMethods(
                [
                    'getParentBlock',
                ],
            )->getMock();

        $order->loadByIncrementId('100000001');

        $customOrderFeesTotalsBlock->method('getParentBlock')
            ->willReturn($orderTotalsBlock);

        $orderTotalsBlock->setOrder($order);
        $orderTotalsBlock->toHtml();

        $customOrderFeesTotalsBlock->initTotals();

        $orderTotals = $orderTotalsBlock->getTotals();

        self::$assertion('test_fee_0', $orderTotals);
        self::$assertion('test_fee_1', $orderTotals);
    }

    /**
     * @dataProvider initializesTotalsByDisplayTypeDataProvider
     */
    #[DataFixtureAttribute('JosephLeedy_CustomFees::../test/Integration/_files/order_with_custom_fees_taxed.php')]
    public function testInitializesTotalsByDisplayType(DisplayType $displayType): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        $configStub = $this->createStub(ConfigInterface::class);
        /** @var OrderTotalsBlock $orderTotalsBlock */
        $orderTotalsBlock = $objectManager->create(OrderTotalsBlock::class);
        $customOrderFeesTotalsBlock = $this
            ->getMockBuilder(CustomOrderFeesTotalsBlock::class)
            ->setConstructorArgs(
                [
                    'context' => $objectManager->get(Context::class),
                    'customFeesRetriever' => $objectManager->create(CustomFeesRetriever::class),
                    'dataObjectFactory' => $objectManager->get(DataObjectFactory::class),
                    'config' => $configStub,
                    'data' => [],
                ],
            )->onlyMethods(
                [
                    'getParentBlock',
                ],
            )->getMock();

        $order->loadByIncrementId('100000001');

        $configStub
            ->method('getSalesDisplayType')
            ->willReturn($displayType);

        $customOrderFeesTotalsBlock
            ->method('getParentBlock')
            ->willReturn($orderTotalsBlock);

        $orderTotalsBlock->setOrder($order);
        $orderTotalsBlock->toHtml();

        $customOrderFeesTotalsBlock->initTotals();

        $expectedOrderTotals = [
            'test_fee_0' => $objectManager->create(
                DataObject::class,
                [
                    'data' => [
                        'code' => 'test_fee_0',
                        'label' => __('Test Fee'),
                        'base_value' => 5.30,
                        'value' => 5.30,
                    ],
                ],
            ),
            'test_fee_1' => $objectManager->create(
                DataObject::class,
                [
                    'data' => [
                        'code' => 'test_fee_1',
                        'label' => __('Another Test Fee'),
                        'base_value' => 1.59,
                        'value' => 1.59,
                    ],
                ],
            ),
        ];

        if ($displayType === DisplayType::Both) {
            $expectedOrderTotals['test_fee_0']->setData('label', __('%1 Excl. Tax', __('Test Fee')));
            $expectedOrderTotals['test_fee_0']->setData('base_value', 5.00);
            $expectedOrderTotals['test_fee_0']->setData('value', 5.00);

            $expectedOrderTotals['test_fee_1']->setData('label', __('%1 Excl. Tax', __('Another Test Fee')));
            $expectedOrderTotals['test_fee_1']->setData('base_value', 1.50);
            $expectedOrderTotals['test_fee_1']->setData('value', 1.50);

            $expectedOrderTotals = array_slice(array: $expectedOrderTotals, offset: 0, length: 1, preserve_keys: true)
                + [
                    'test_fee_0_with_tax' => $objectManager->create(
                        DataObject::class,
                        [
                            'data' => [
                                'code' => 'test_fee_0_with_tax',
                                'label' => __('%1 Incl. Tax', __('Test Fee')),
                                'base_value' => 5.30,
                                'value' => 5.30,
                            ],
                        ],
                    ),
                ] + array_slice(array: $expectedOrderTotals, offset: 1, preserve_keys: true);
            $expectedOrderTotals += [
                'test_fee_1_with_tax' => $objectManager->create(
                    DataObject::class,
                    [
                        'data' => [
                            'code' => 'test_fee_1_with_tax',
                            'label' => __('%1 Incl. Tax', __('Another Test Fee')),
                            'base_value' => 1.59,
                            'value' => 1.59,
                        ],
                    ],
                ),
            ];
        }

        $actualOrderTotals = $orderTotalsBlock->getTotals();

        self::assertArraySubset($expectedOrderTotals, $actualOrderTotals);
    }

    /**
     * @return string[][]
     */
    public function initTotalsDataProvider(): array
    {
        return [
            'does initialize totals for order with custom fees' => [
                'condition' => 'does',
            ],
            'does not initialize totals for order without custom fees' => [
                'condition' => 'does not',
            ],
        ];
    }

    /**
     * @return array<string, array{'displayType': DisplayType}>
     */
    public static function initializesTotalsByDisplayTypeDataProvider(): array
    {
        return [
            'including tax' => [
                'displayType' => DisplayType::IncludingTax,
            ],
            'including and excluding tax' => [
                'displayType' => DisplayType::Both,
            ],
        ];
    }
}
