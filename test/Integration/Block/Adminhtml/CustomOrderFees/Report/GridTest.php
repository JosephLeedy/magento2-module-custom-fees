<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Block\Adminhtml\CustomOrderFees\Report;

use JosephLeedy\CustomFees\Block\Adminhtml\CustomOrderFees\Report\Grid;
use Magento\Framework\App\Area;
use Magento\Framework\DataObject;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Magento\Framework\View\LayoutInterface;

use function array_keys;
use function array_map;

#[AppArea(Area::AREA_ADMINHTML)]
final class GridTest extends TestCase
{
    public function testAddsGridColumnsAndExportTypes(): void
    {
        $objectManger = Bootstrap::getObjectManager();
        /** @var Grid $grid */
        $grid = $objectManger->create(Grid::class);
        /** @var LayoutInterface $layout */
        $layout = $objectManger->get(LayoutInterface::class);
        /** @var DataObject $filterData */
        $filterData = $objectManger->create(
            DataObject::class,
            [
                'data' => [
                    'show_base_amount' => 1,
                    'show_base_invoiced_amount' => 1,
                    'show_base_refunded_amount' => 1,
                ],
            ],
        );

        $layout->addBlock($grid, 'custom_order_fees.report.grid');

        $grid->setFilterData($filterData);
        $grid->toHtml();

        $expectedColumns = [
            'period',
            'fee_title',
            'base_fee_amount',
            'paid_fee_amount',
            'base_invoiced_fee_amount',
            'invoiced_fee_amount',
            'base_refunded_fee_amount',
            'refunded_fee_amount',
        ];
        $actualColumns = array_keys($grid->getColumns());
        $expectedExportTypes = [
            'CSV',
            'Excel XML',
        ];
        $actualExportTypes = array_map(
            static fn(DataObject $exportType): string => $exportType->getLabel(),
            $grid->getExportTypes() ?: [],
        );

        self::assertSame($expectedColumns, $actualColumns);
        self::assertEquals($expectedExportTypes, $actualExportTypes);
    }

    #[DataFixture('JosephLeedy_CustomFees::../test/Integration/_files/aggregated_custom_order_fees.php')]
    public function testSetsTotalsWithMultipleCurrencies(): void
    {
        $objectManger = Bootstrap::getObjectManager();
        /** @var Grid $grid */
        $grid = $objectManger->create(Grid::class);
        /** @var LayoutInterface $layout */
        $layout = $objectManger->get(LayoutInterface::class);
        /** @var DataObject $filterData */
        $filterData = $objectManger->create(
            DataObject::class,
            [
                'data' => [
                    'show_base_amount' => 1,
                    'show_base_invoiced_amount' => 1,
                    'show_base_refunded_amount' => 1,
                    'period_type' => 'day',
                    'from' => '2025-01-01',
                    'to' => '2025-12-31',
                ],
            ],
        );

        $layout->addBlock($grid, 'custom_order_fees.report.grid');

        $grid->setFilterData($filterData);
        $grid->toHtml();

        $totals = $grid->getTotals();
        $expectedTotalData = [
            'base_fee_amount' => '19.5',
            'paid_fee_amount' => '6.5000, 9.1872',
            'base_invoiced_fee_amount' => '0.0000, 0.0000',
            'invoiced_fee_amount' => '0.0000, 0.0000',
            'base_refunded_fee_amount' => '0.0000, 0.0000',
            'refunded_fee_amount' => '0.0000, 0.0000',
            'paid_order_currency' => 'USD, EUR',
            'orig_data' => null,
        ];
        $actualTotalData = $totals->getData();

        self::assertSame($expectedTotalData, $actualTotalData);
    }
}
