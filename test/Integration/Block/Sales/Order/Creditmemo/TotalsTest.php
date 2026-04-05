<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Block\Sales\Order\Creditmemo;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use JosephLeedy\CustomFees\Api\ConfigInterface;
use JosephLeedy\CustomFees\Block\Sales\Order\Creditmemo\Totals as CustomOrderFeesCreditMemoTotalsBlock;
use JosephLeedy\CustomFees\Model\DisplayType;
use JosephLeedy\CustomFees\Service\CustomFeesRetriever;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Block\Order\Creditmemo\Totals as CreditmemoTotalsBlock;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\TestFramework\Annotation\DataFixture as DataFixtureAnnotation;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;
use PHPUnit\Framework\TestCase;

use function __;
use function array_slice;

#[AppArea(Area::AREA_ADMINHTML)]
final class TotalsTest extends TestCase
{
    use ArraySubsetAsserts;

    /**
     * @dataProvider initTotalsDataProvider
     * @param 'does'|'does not' $condition
     */
    public function testInitTotals(string $condition): void
    {
        $filename = 'creditmemo';
        $assertion = 'assertArrayNotHasKey';

        if ($condition === 'does') {
            $filename .= '_with_partially_refunded_custom_fees';
            $assertion = 'assertArrayHasKey';
        }

        $resolver = Resolver::getInstance();

        $resolver->setCurrentFixtureType(DataFixtureAnnotation::ANNOTATION);
        $resolver->requireDataFixture("JosephLeedy_CustomFees::../test/Integration/_files/$filename.php");

        /** @var ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        $appState = $objectManager->get(State::class);

        $appState->setAreaCode(Area::AREA_ADMINHTML);

        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        /** @var CreditmemoTotalsBlock $creditMemoTotalsBlock */
        $creditMemoTotalsBlock = $objectManager->create(CreditmemoTotalsBlock::class);
        $customOrderFeesTotalsBlock = $this->getMockBuilder(CustomOrderFeesCreditMemoTotalsBlock::class)
            ->setConstructorArgs(
                [
                    'context' => $objectManager->get(Context::class),
                    'customFeesRetriever' => $objectManager->create(CustomFeesRetriever::class),
                    'config' => $objectManager->create(ConfigInterface::class),
                    'dataObjectFactory' => $objectManager->get(DataObjectFactory::class),
                    'data' => [],
                ],
            )->onlyMethods(
                [
                    'getParentBlock',
                ],
            )->getMock();

        $order->loadByIncrementId('100000001');

        $customOrderFeesTotalsBlock->method('getParentBlock')
            ->willReturn($creditMemoTotalsBlock);

        /** @var Creditmemo $creditMemo */
        $creditMemo = $order->getCreditmemosCollection()->getFirstItem();

        $creditMemoTotalsBlock->setOrder($order);
        $creditMemoTotalsBlock->setCreditmemo($creditMemo);
        $creditMemoTotalsBlock->toHtml();

        $customOrderFeesTotalsBlock->initTotals();

        $creditMemoTotals = $creditMemoTotalsBlock->getTotals();

        self::$assertion('test_fee_0', $creditMemoTotals);
        self::$assertion('test_fee_1', $creditMemoTotals);
    }

    /**
     * @dataProvider initializesTotalsByDisplayTypeDataProvider
     */
    #[DataFixture('JosephLeedy_CustomFees::../test/Integration/_files/creditmemo_with_custom_fees_taxed.php')]
    public function testInitializesTotalsByDisplayType(DisplayType $displayType): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        $configStub = $this->createStub(ConfigInterface::class);
        /** @var CreditmemoTotalsBlock $creditMemoTotalsBlock */
        $creditMemoTotalsBlock = $objectManager->create(CreditmemoTotalsBlock::class);
        $customCreditMemoFeesTotalsBlock = $this
            ->getMockBuilder(CustomOrderFeesCreditMemoTotalsBlock::class)
            ->setConstructorArgs(
                [
                    'context' => $objectManager->get(Context::class),
                    'customFeesRetriever' => $objectManager->create(CustomFeesRetriever::class),
                    'config' => $configStub,
                    'dataObjectFactory' => $objectManager->get(DataObjectFactory::class),
                    'data' => [],
                ],
            )->onlyMethods(
                [
                    'getParentBlock',
                ],
            )->getMock();

        $order->loadByIncrementId('100000001');

        /** @var Creditmemo $creditMemo */
        $creditMemo = $order->getCreditmemosCollection()->getFirstItem();

        $configStub
            ->method('getSalesDisplayType')
            ->willReturn($displayType);

        $customCreditMemoFeesTotalsBlock
            ->method('getParentBlock')
            ->willReturn($creditMemoTotalsBlock);

        $creditMemoTotalsBlock->setOrder($order);
        $creditMemoTotalsBlock->setCreditmemo($creditMemo);
        $creditMemoTotalsBlock->toHtml();

        $customCreditMemoFeesTotalsBlock->initTotals();

        $expectedCreditMemoTotals = [
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
            $expectedCreditMemoTotals['test_fee_0']->setData('label', __('%1 Excl. Tax', __('Test Fee')));
            $expectedCreditMemoTotals['test_fee_0']->setData('base_value', 5.00);
            $expectedCreditMemoTotals['test_fee_0']->setData('value', 5.00);

            $expectedCreditMemoTotals['test_fee_1']->setData('label', __('%1 Excl. Tax', __('Another Test Fee')));
            $expectedCreditMemoTotals['test_fee_1']->setData('base_value', 1.50);
            $expectedCreditMemoTotals['test_fee_1']->setData('value', 1.50);

            $expectedCreditMemoTotals = array_slice($expectedCreditMemoTotals, 0, 1, true)
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
                ] + array_slice(array: $expectedCreditMemoTotals, offset: 1, preserve_keys: true);
            $expectedCreditMemoTotals += [
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

        $actualCreditMemoTotals = $creditMemoTotalsBlock->getTotals();

        self::assertArraySubset($expectedCreditMemoTotals, $actualCreditMemoTotals);
    }

    /**
     * @return array<string, array{'condition': 'does'|'does not'}>
     */
    public function initTotalsDataProvider(): array
    {
        return [
            'does initialize totals for creditmemo with custom fees' => [
                'condition' => 'does',
            ],
            'does not initialize totals for creditmemo without custom fees' => [
                'condition' => 'does not',
            ],
        ];
    }

    /**
     * @return array<string, array{displayType: DisplayType}>
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
