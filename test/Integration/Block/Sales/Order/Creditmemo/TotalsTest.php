<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Block\Sales\Order\Creditmemo;

use JosephLeedy\CustomFees\Block\Sales\Order\Creditmemo\Totals as CustomOrderFeesCreditMemoTotalsBlock;
use JosephLeedy\CustomFees\Service\CustomFeesRetriever;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Block\Order\Creditmemo\Totals as CreditmemoTotalsBlock;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\TestFramework\Annotation\DataFixture;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;
use PHPUnit\Framework\TestCase;

#[AppArea(Area::AREA_ADMINHTML)]
final class TotalsTest extends TestCase
{
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

        $resolver->setCurrentFixtureType(DataFixture::ANNOTATION);
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
}
