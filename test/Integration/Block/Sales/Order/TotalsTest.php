<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Block\Sales\Order;

use JosephLeedy\CustomFees\Block\Sales\Order\Totals as CustomOrderFeesTotalsBlock;
use JosephLeedy\CustomFees\Service\CustomFeesRetriever;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Block\Order\Creditmemo\Totals as CreditmemoTotalsBlock;
use Magento\Sales\Block\Order\Invoice\Totals as InvoiceTotalsBlock;
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
     * @param 'order'|'invoice'|'creditmemo' $totalsType
     * @param 'does'|'does not' $condition
     */
    public function testInitTotals(string $totalsType, string $condition): void
    {
        $filename = $totalsType;
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
        /** @var OrderTotalsBlock|InvoiceTotalsBlock|CreditmemoTotalsBlock $totalsBlock */
        $totalsBlock = match ($totalsType) {
            'order' => $objectManager->create(OrderTotalsBlock::class),
            'invoice' => $objectManager->create(InvoiceTotalsBlock::class),
            'creditmemo' => $objectManager->create(CreditmemoTotalsBlock::class),
        };
        $customOrderFeesTotalsBlock = $this->getMockBuilder(CustomOrderFeesTotalsBlock::class)
            ->setConstructorArgs(
                [
                    'context' => $objectManager->get(Context::class),
                    'customFeesRetriever' => $objectManager->create(CustomFeesRetriever::class),
                    'dataObjectFactory' => $objectManager->get(DataObjectFactory::class),
                    'data' => []
                ]
            )->onlyMethods(
                [
                    'getParentBlock',
                ]
            )->getMock();

        $order->loadByIncrementId('100000001');

        $customOrderFeesTotalsBlock->method('getParentBlock')
            ->willReturn($totalsBlock);

        $totalsBlock->setOrder($order);

        if ($totalsType === 'invoice') {
            $totalsBlock->setInvoice($order->getInvoiceCollection()->getFirstItem());
        }

        if ($totalsType === 'creditmemo') {
            $totalsBlock->setCreditmemo($order->getCreditmemosCollection()->getFirstItem());
        }

        $totalsBlock->toHtml();

        $customOrderFeesTotalsBlock->initTotals();

        $orderTotals = $totalsBlock->getTotals();

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
                'totalsType' => 'order',
                'condition' => 'does'
            ],
            'does initialize totals for invoice with custom fees' => [
                'totalsType' => 'invoice',
                'condition' => 'does'
            ],
            'does initialize totals for creditmemo with custom fees' => [
                'totalsType' => 'creditmemo',
                'condition' => 'does'
            ],
            'does not initialize totals for order without custom fees' => [
                'totalsType' => 'order',
                'condition' => 'does not'
            ],
            'does not initialize totals for invoice without custom fees' => [
                'totalsType' => 'invoice',
                'condition' => 'does not'
            ],
            'does not initialize totals for creditmemo without custom fees' => [
                'totalsType' => 'creditmemo',
                'condition' => 'does not'
            ]
        ];
    }
}
