<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Cron;

use DateInterval;
use DateTime;
use InvalidArgumentException;
use JosephLeedy\CustomFees\Api\ConfigInterface;
use JosephLeedy\CustomFees\Cron\AggregateCustomOrderFeesReport;
use JosephLeedy\CustomFees\Model\ResourceModel\Report\CustomOrderFees as CustomOrderFeesReport;
use JosephLeedy\CustomFees\Model\ResourceModel\Report\CustomOrderFees\Collection as CustomOrderFeesReportCollection;
use JosephLeedy\CustomFees\Model\ResourceModel\Report\CustomOrderFeesFactory as CustomOrderFeesReportFactory;
use Magento\Cron\Model\ConfigInterface as CronConfiguration;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Reports\Model\Item;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\ResourceModel\Order\Collection as OrderCollection;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Zend_Db_Exception;

final class AggregateCustomOrderFeesReportTest extends TestCase
{
    public function testCronTaskIsConfiguredProperly(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var CronConfiguration $cronConfiguration */
        $cronConfiguration = $objectManager->get(CronConfiguration::class);
        /**
         * @var array{
         *     default: array{
         *         aggregate_custom_order_fees_report?: array{
         *             name: string,
         *             instance: class-string,
         *             method: string,
         *         },
         *     },
         * } $cronJobs
         */
        $cronJobs = $cronConfiguration->getJobs();

        self::assertArrayHasKey('aggregate_custom_order_fees_report', $cronJobs['default']);
        self::assertSame(
            [
                'name' => 'aggregate_custom_order_fees_report',
                'instance' => AggregateCustomOrderFeesReport::class,
                'method' => 'execute',
            ],
            $cronJobs['default']['aggregate_custom_order_fees_report'] ?? [],
        );
    }

    /**
     * @dataProvider savesCronExpressionDataProvider
     * @phpstan-param 'D'|'W'|'M' $frequency
     */
    #[DataFixture('JosephLeedy_CustomFees::../test/Integration/_files/orders_with_custom_fees.php')]
    public function testAggregateCustomOrderFeesReport(string $frequency): void
    {
        $timestamp = 1761061677;
        $objectManager = Bootstrap::getObjectManager();
        /** @var OrderCollection $orderCollection */
        $orderCollection = $objectManager->create(OrderCollection::class);
        $configStub = $this->createStub(ConfigInterface::class);
        $localeDate = $this->createStub(TimezoneInterface::class);
        /** @var AggregateCustomOrderFeesReport $aggregateCustomOrderFeesReport */
        $aggregateCustomOrderFeesReport = $objectManager->create(
            AggregateCustomOrderFeesReport::class,
            [
                'config' => $configStub,
                'localeDate' => $localeDate,
            ],
        );
        $october20ReportItems = [
            $objectManager->create(
                Item::class,
                [
                    'data' => [
                        'period' => '2025-10-20',
                        'fee_title' => 'Another Test Fee',
                        'base_fee_amount' => '1.5000',
                        'paid_fee_amount' => '1.5000',
                        'base_discount_amount' => '0.0000',
                        'paid_discount_amount' => '0.0000',
                        'base_tax_amount' => '0.0000',
                        'paid_tax_amount' => '0.0000',
                        'paid_order_currency' => 'USD',
                        'base_invoiced_fee_amount' => '0.0000',
                        'invoiced_fee_amount' => '0.0000',
                        'base_refunded_fee_amount' => '0.0000',
                        'refunded_fee_amount' => '0.0000',
                        'orig_data' => null,
                    ],
                ],
            ),
            $objectManager->create(
                Item::class,
                [
                    'data' => [
                        'period' => '2025-10-20',
                        'fee_title' => 'Test Fee',
                        'base_fee_amount' => '5.0000',
                        'paid_fee_amount' => '5.0000',
                        'base_discount_amount' => '0.0000',
                        'paid_discount_amount' => '0.0000',
                        'base_tax_amount' => '0.0000',
                        'paid_tax_amount' => '0.0000',
                        'paid_order_currency' => 'USD',
                        'base_invoiced_fee_amount' => '0.0000',
                        'invoiced_fee_amount' => '0.0000',
                        'base_refunded_fee_amount' => '0.0000',
                        'refunded_fee_amount' => '0.0000',
                        'orig_data' => null,
                    ],
                ],
            ),
        ];
        $october16ReportItems = [
            $objectManager->create(
                Item::class,
                [
                    'data' => [
                        'period' => '2025-10-16',
                        'fee_title' => 'Another Test Fee',
                        'base_fee_amount' => '1.5000',
                        'paid_fee_amount' => '1.5000',
                        'base_discount_amount' => '0.0000',
                        'paid_discount_amount' => '0.0000',
                        'base_tax_amount' => '0.0000',
                        'paid_tax_amount' => '0.0000',
                        'paid_order_currency' => '',
                        'base_invoiced_fee_amount' => '0.0000',
                        'invoiced_fee_amount' => '0.0000',
                        'base_refunded_fee_amount' => '0.0000',
                        'refunded_fee_amount' => '0.0000',
                        'orig_data' => null,
                    ],
                ],
            ),
            $objectManager->create(
                Item::class,
                [
                    'data' => [
                        'period' => '2025-10-16',
                        'fee_title' => 'Test Fee',
                        'base_fee_amount' => '5.0000',
                        'paid_fee_amount' => '5.0000',
                        'base_discount_amount' => '0.0000',
                        'paid_discount_amount' => '0.0000',
                        'base_tax_amount' => '0.0000',
                        'paid_tax_amount' => '0.0000',
                        'paid_order_currency' => '',
                        'base_invoiced_fee_amount' => '0.0000',
                        'invoiced_fee_amount' => '0.0000',
                        'base_refunded_fee_amount' => '0.0000',
                        'refunded_fee_amount' => '0.0000',
                        'orig_data' => null,
                    ],
                ],
            ),
        ];
        $october14ReportItems = [
            $objectManager->create(
                Item::class,
                [
                    'data' => [
                        'period' => '2025-10-14',
                        'fee_title' => 'Another Test Fee',
                        'base_fee_amount' => '1.5000',
                        'paid_fee_amount' => '1.5000',
                        'base_discount_amount' => '0.0000',
                        'paid_discount_amount' => '0.0000',
                        'base_tax_amount' => '0.0000',
                        'paid_tax_amount' => '0.0000',
                        'paid_order_currency' => '',
                        'base_invoiced_fee_amount' => '0.0000',
                        'invoiced_fee_amount' => '0.0000',
                        'base_refunded_fee_amount' => '0.0000',
                        'refunded_fee_amount' => '0.0000',
                        'orig_data' => null,
                    ],
                ],
            ),
            $objectManager->create(
                Item::class,
                [
                    'data' => [
                        'period' => '2025-10-14',
                        'fee_title' => 'Test Fee',
                        'base_fee_amount' => '5.0000',
                        'paid_fee_amount' => '5.0000',
                        'base_discount_amount' => '0.0000',
                        'paid_discount_amount' => '0.0000',
                        'base_tax_amount' => '0.0000',
                        'paid_tax_amount' => '0.0000',
                        'paid_order_currency' => '',
                        'base_invoiced_fee_amount' => '0.0000',
                        'invoiced_fee_amount' => '0.0000',
                        'base_refunded_fee_amount' => '0.0000',
                        'refunded_fee_amount' => '0.0000',
                        'orig_data' => null,
                    ],
                ],
            ),
        ];
        $september21ReportItems = [
            $objectManager->create(
                Item::class,
                [
                    'data' => [
                        'period' => '2025-09-21',
                        'fee_title' => 'Another Test Fee',
                        'base_fee_amount' => '1.5000',
                        'paid_fee_amount' => '1.5000',
                        'base_discount_amount' => '0.0000',
                        'paid_discount_amount' => '0.0000',
                        'base_tax_amount' => '0.0000',
                        'paid_tax_amount' => '0.0000',
                        'paid_order_currency' => '',
                        'base_invoiced_fee_amount' => '0.0000',
                        'invoiced_fee_amount' => '0.0000',
                        'base_refunded_fee_amount' => '0.0000',
                        'refunded_fee_amount' => '0.0000',
                        'orig_data' => null,
                    ],
                ],
            ),
            $objectManager->create(
                Item::class,
                [
                    'data' => [
                        'period' => '2025-09-21',
                        'fee_title' => 'Test Fee',
                        'base_fee_amount' => '5.0000',
                        'paid_fee_amount' => '5.0000',
                        'base_discount_amount' => '0.0000',
                        'paid_discount_amount' => '0.0000',
                        'base_tax_amount' => '0.0000',
                        'paid_tax_amount' => '0.0000',
                        'paid_order_currency' => '',
                        'base_invoiced_fee_amount' => '0.0000',
                        'invoiced_fee_amount' => '0.0000',
                        'base_refunded_fee_amount' => '0.0000',
                        'refunded_fee_amount' => '0.0000',
                        'orig_data' => null,
                    ],
                ],
            ),
        ];
        /** @var CustomOrderFeesReportCollection $aggregatedCustomOrderFeesCollection */
        $aggregatedCustomOrderFeesCollection = $objectManager->create(CustomOrderFeesReportCollection::class);

        $orderCollection
            ->addFieldToFilter(
                'increment_id',
                [
                    'in' => [
                        '100000001',
                        '100000002',
                        '100000003',
                        '100000004',
                    ],
                ],
            )->walk(
                static function (OrderInterface $order) use ($timestamp): void {
                    $duration = match ($order->getIncrementId()) {
                        '100000001' => 'P1D',
                        '100000002' => 'P5D',
                        '100000003' => 'P1W',
                        '100000004' => 'P1M',
                    };
                    $createdAt = (new DateTime("@$timestamp"))
                        ->sub(new DateInterval($duration))
                        ->format('Y-m-d H:i:s');

                    $order->setCreatedAt($createdAt);
                    $order->save();
                },
            );

        $configStub
            ->method('isCustomOrderFeesReportAggregationEnabled')
            ->willReturn(true);
        $configStub
            ->method('getCustomOrderFeesReportAggregationFrequency')
            ->willReturn($frequency);

        $localeDate
            ->method('date')
            ->willReturn(new DateTime("@$timestamp"));

        $aggregateCustomOrderFeesReport->execute();

        $expectedAggregatedCustomOrderFees = match ($frequency) {
            'D' => $october20ReportItems,
            'W' => array_merge($october20ReportItems, $october16ReportItems, $october14ReportItems),
            'M' => array_merge(
                $october20ReportItems,
                $october16ReportItems,
                $october14ReportItems,
                $september21ReportItems,
            ),
        };
        $actualAggregatedCustomOrderFees = $aggregatedCustomOrderFeesCollection
            ->addStoreFilter([0, 1])
            ->addOrder('period')
            ->getItems();

        self::assertEquals($expectedAggregatedCustomOrderFees, $actualAggregatedCustomOrderFees);
    }

    #[Config(ConfigInterface::CONFIG_PATH_REPORTS_CUSTOM_ORDER_FEES_ENABLE_AGGREGATION, 0)]
    public function testDoesNotAggregateCustomOrderFeesReportDataIfNotEnabled(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var AggregateCustomOrderFeesReport $aggregateCustomOrderFeesReport */
        $aggregateCustomOrderFeesReport = $objectManager->create(AggregateCustomOrderFeesReport::class);
        /** @var CustomOrderFeesReportCollection $aggregatedCustomOrderFeesCollection */
        $aggregatedCustomOrderFeesCollection = $objectManager->create(CustomOrderFeesReportCollection::class);

        $aggregateCustomOrderFeesReport->execute();

        $actualAggregatedCustomOrderFees = $aggregatedCustomOrderFeesCollection
            ->addStoreFilter([0, 1])
            ->addOrder('period')
            ->getItems();

        self::assertEmpty($actualAggregatedCustomOrderFees);
    }

    #[Config(ConfigInterface::CONFIG_PATH_REPORTS_CUSTOM_ORDER_FEES_ENABLE_AGGREGATION, 1)]
    #[Config(ConfigInterface::CONFIG_PATH_REPORTS_CUSTOM_ORDER_FEES_AGGREGATION_FREQUENCY, 'I')]
    public function testThrowsExceptionIfAggregationFrequencyIsInvalid(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $invalidFrequencyException = new InvalidArgumentException(
            'Invalid custom order fees report aggregation frequency "I".',
        );
        /** @var AggregateCustomOrderFeesReport $aggregateCustomOrderFeesReport */
        $aggregateCustomOrderFeesReport = $objectManager->create(AggregateCustomOrderFeesReport::class);

        $this->expectExceptionObject($invalidFrequencyException);

        $aggregateCustomOrderFeesReport->execute();
    }

    #[Config(ConfigInterface::CONFIG_PATH_REPORTS_CUSTOM_ORDER_FEES_ENABLE_AGGREGATION, 1)]
    #[Config(ConfigInterface::CONFIG_PATH_REPORTS_CUSTOM_ORDER_FEES_AGGREGATION_FREQUENCY, 'D')]
    public function testThrowsExceptionIfReportCannotBeAggregated(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $customOrderFeesReportFactoryStub = $this->createStub(CustomOrderFeesReportFactory::class);
        $customOrderFeesReportStub = $this->createStub(CustomOrderFeesReport::class);
        $databaseException = new Zend_Db_Exception('2006: MySQL server has gone away');
        /** @var LocalizedException $aggregationException */
        $aggregationException = $objectManager->create(
            LocalizedException::class,
            [
                'phrase' => __(
                    'Could not aggregate custom order fees report. Error: "%1"',
                    $databaseException->getMessage(),
                ),
                'cause' => $databaseException,
            ],
        );
        /** @var AggregateCustomOrderFeesReport $aggregateCustomOrderFeesReport */
        $aggregateCustomOrderFeesReport = $objectManager->create(
            AggregateCustomOrderFeesReport::class,
            [
                'customOrderFeesReportFactory' => $customOrderFeesReportFactoryStub,
            ],
        );

        $customOrderFeesReportFactoryStub
            ->method('create')
            ->willReturn($customOrderFeesReportStub);

        $customOrderFeesReportStub
            ->method('aggregate')
            ->willThrowException($databaseException);

        $this->expectExceptionObject($aggregationException);

        $aggregateCustomOrderFeesReport->execute();
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function savesCronExpressionDataProvider(): array
    {
        return [
            'daily' => [
                'frequency' => 'D',
            ],
            'weekly' => [
                'frequency' => 'W',
            ],
            'monthly' => [
                'frequency' => 'M',
            ],
        ];
    }
}
