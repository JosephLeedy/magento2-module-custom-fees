<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Block\Adminhtml\Sales\Order\Creditmemo\Create;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use JosephLeedy\CustomFees\Block\Adminhtml\Sales\Order\Creditmemo\Create\Totals as CreateCreditMemoTotalsBlock;
use JosephLeedy\CustomFees\Model\FeeType;
use JosephLeedy\CustomFees\Service\CustomFeesRetriever;
use Magento\Framework\App\Area;
use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Phrase;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Block\Order\Creditmemo\Totals as CreditMemoTotalsBlock;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

use function array_map;

#[AppArea(Area::AREA_ADMINHTML)]
final class TotalsTest extends TestCase
{
    use ArraySubsetAsserts;

    #[DataFixture('JosephLeedy_CustomFees::../test/Integration/_files/invoice_with_custom_fees.php')]
    public function testInitializesAndReplacesRefundedCustomFeeTotals(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        /** @var CreditmemoInterface&Creditmemo $creditMemo */
        $creditMemo = $objectManager->create(CreditmemoInterface::class);
        /** @var CreditMemoTotalsBlock $creditMemoTotalsBlock */
        $creditMemoTotalsBlock = $objectManager->create(CreditMemoTotalsBlock::class);
        $createCreditMemoTotalsBlock = $this->getMockBuilder(CreateCreditMemoTotalsBlock::class)
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

        $creditMemo->setOrder($order);
        $creditMemo->getExtensionAttributes()->setRefundedCustomFees(
            [
                'test_fee_0' => '0.00',
                'test_fee_1' => '1.50',
            ],
        );

        $creditMemoTotalsBlock->setOrder($order);
        $creditMemoTotalsBlock->setCreditmemo($creditMemo);
        $creditMemoTotalsBlock->toHtml();

        $createCreditMemoTotalsBlock
            ->method('getParentBlock')
            ->willReturn($creditMemoTotalsBlock);

        $createCreditMemoTotalsBlock->initTotals();

        $expectedCreditMemoTotalData = [
            'custom_fees' => new DataObject(
                [
                    'code' => 'custom_fees',
                    'block_name' => $createCreditMemoTotalsBlock->getNameInLayout(),
                ],
            ),
        ];
        $actualCreditMemoTotalData = $creditMemoTotalsBlock->getTotals();
        $expectedCustomFeeTotalData = [
            'test_fee_0' => [
                'code' => 'test_fee_0',
                'label' => 'Refund Test Fee',
                'type' => 'fixed',
                'percent' => null,
                'show_percentage' => false,
                'base_value' => 0.00,
                'value' => 0.00,
            ],
            'test_fee_1' => [
                'code' => 'test_fee_1',
                'label' => 'Refund Another Test Fee',
                'type' => 'fixed',
                'percent' => null,
                'show_percentage' => false,
                'base_value' => 1.50,
                'value' => 1.50,
            ],
        ];
        $actualCustomFeeTotalData = $this->getCustomFeeTotalData($createCreditMemoTotalsBlock->getCustomFeeTotals());

        self::assertArraySubset($expectedCreditMemoTotalData, $actualCreditMemoTotalData);
        self::assertEquals($expectedCustomFeeTotalData, $actualCustomFeeTotalData);
    }

    #[DataFixture('JosephLeedy_CustomFees::../test/Integration/_files/invoice.php')]
    public function testDoesNotReplaceCustomFeeTotalsIfCreditMemoDoesNotHaveRefundedCustomFees(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        /** @var CreditmemoInterface&Creditmemo $creditMemo */
        $creditMemo = $objectManager->create(CreditmemoInterface::class);
        /** @var CreditMemoTotalsBlock $creditMemoTotalsBlock */
        $creditMemoTotalsBlock = $objectManager->create(CreditMemoTotalsBlock::class);
        $createCreditMemoTotalsBlock = $this->getMockBuilder(CreateCreditMemoTotalsBlock::class)
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

        $creditMemo->setOrder($order);

        $creditMemoTotalsBlock->setOrder($order);
        $creditMemoTotalsBlock->setCreditmemo($creditMemo);
        $creditMemoTotalsBlock->toHtml();

        $createCreditMemoTotalsBlock
            ->method('getParentBlock')
            ->willReturn($creditMemoTotalsBlock);

        $createCreditMemoTotalsBlock->initTotals();

        self::assertEmpty($createCreditMemoTotalsBlock->getCustomFeeTotals());
    }

    /**
     * @param DataObject[] $customFeeTotals
     * @return array<string, array{
     *     code: string,
     *     label: string,
     *     type: value-of<FeeType>,
     *     percent: null,
     *     show_percentage: bool,
     *     base_value: float,
     *     value: float,
     * }>
     */
    private function getCustomFeeTotalData(array $customFeeTotals): array
    {
        return array_map(
            static function (DataObject $customFeeTotal) {
                /**
                 * @var array{
                 *     code: string,
                 *     label: Phrase,
                 *     type: value-of<FeeType>,
                 *     percent: null,
                 *     show_percentage: bool,
                 *     base_value: float,
                 *     value: float,
                 * } $customFeeTotalData
                 */
                $customFeeTotalData = $customFeeTotal->getData();
                $customFeeTotalData['label'] = (string) $customFeeTotalData['label'];

                return $customFeeTotalData;
            },
            $customFeeTotals,
        );
    }
}
