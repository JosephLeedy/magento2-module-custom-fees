<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Model\ResourceModel\Report\CustomOrderFees;

use JosephLeedy\CustomFees\Model\ResourceModel\Report\CustomOrderFees\Collection;
use Magento\Framework\App\Area;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Zend_Db_Expr;

use function array_column;

#[AppArea(Area::AREA_ADMINHTML)]
final class CollectionTest extends TestCase
{
    /**
     * @dataProvider selectsAndGroupsCorrectFieldsDataProvider
     * @param array<string, string> $aggregatedColumns
     * @param array<int, string> $expectedColumns
     * @param array<int, string|Zend_Db_Expr> $expectedGroups
     */
    public function testSelectsAndGroupsCorrectFields(
        array $aggregatedColumns,
        array $expectedColumns,
        array $expectedGroups,
    ): void {
        $objectManager = Bootstrap::getObjectManager();
        /** @var Collection $collection */
        $collection = $objectManager->create(Collection::class);

        if (count($aggregatedColumns) > 0) {
            $collection->setAggregatedColumns($aggregatedColumns);
            $collection->isTotals(true);
        }

        $collection->load();

        $select = $collection->getSelect();
        $actualColumns = array_column((array) $select->getPart('columns'), 2);
        $actualGroups = $select->getPart('group');

        self::assertSame($expectedColumns, $actualColumns);
        self::assertEquals($expectedGroups, $actualGroups);
    }

    /**
     * @return array<string, array<string, array<int|string, string|Zend_Db_Expr>>>
     */
    public static function selectsAndGroupsCorrectFieldsDataProvider(): array
    {
        return [
            'without totals' => [
                'aggregatedColumns' => [],
                'expectedColumns' => [
                    'period',
                    'fee_title',
                    'base_fee_amount',
                    'paid_fee_amount',
                    'paid_order_currency',
                    'base_invoiced_fee_amount',
                    'invoiced_fee_amount',
                    'base_refunded_fee_amount',
                    'refunded_fee_amount',
                ],
                'expectedGroups' => [
                    new Zend_Db_Expr('DATE_FORMAT(period, \'%Y-%m-%d\')'),
                    'fee_title',
                    'paid_order_currency',
                ],
            ],
            'with totals' => [
                'aggregatedColumns' => [
                    'base_fee_amount' => 'sum(base_fee_amount)',
                    'paid_fee_amount' => 'sum(paid_fee_amount)',
                    'paid_order_currency' => 'paid_order_currency',
                    'base_invoiced_fee_amount' => 'sum(base_invoiced_fee_amount)',
                    'invoiced_fee_amount' => 'sum(invoiced_fee_amount)',
                    'base_refunded_fee_amount' => 'sum(base_refunded_fee_amount)',
                    'refunded_fee_amount' => 'sum(refunded_fee_amount)',
                ],
                'expectedColumns' => [
                    'base_fee_amount',
                    'paid_fee_amount',
                    'paid_order_currency',
                    'base_invoiced_fee_amount',
                    'invoiced_fee_amount',
                    'base_refunded_fee_amount',
                    'refunded_fee_amount',
                ],
                'expectedGroups' => [
                    'paid_order_currency',
                ],
            ],
        ];
    }
}
