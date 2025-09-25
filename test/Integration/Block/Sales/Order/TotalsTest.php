<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Block\Sales\Order;

use JosephLeedy\CustomFees\Block\Sales\Order\Totals as CustomOrderFeesTotalsBlock;
use JosephLeedy\CustomFees\Service\CustomFeesRetriever;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Block\Order\Totals as OrderTotalsBlock;
use Magento\Sales\Model\Order;
use Magento\TestFramework\Annotation\DataFixture;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;
use PHPUnit\Framework\TestCase;

final class TotalsTest extends TestCase
{
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

        $resolver->setCurrentFixtureType(DataFixture::ANNOTATION);
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
}
