<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Model\ResourceModel\Report;

use JosephLeedy\CustomFees\Model\ResourceModel\Report\CustomOrderFees;
use Magento\Framework\App\Area;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[AppArea(Area::AREA_ADMINHTML)]
final class CustomOrderFeesTest extends TestCase
{
    #[DataFixture('JosephLeedy_CustomFees::../test/Integration/_files/orders_with_custom_fees.php')]
    public function testAggregatesCustomOrderFeesReportDataSuccessfully(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var CustomOrderFees $customOrderFeesReport */
        $customOrderFeesReport = $objectManager->create(CustomOrderFees::class);

        $customOrderFeesReport->aggregate();

        /** @var AdapterInterface $connection */
        $connection = $customOrderFeesReport->getConnection();
        $query = $connection->select()->from(CustomOrderFees::TABLE_NAME);
        $aggregatedCustomOrderFees = $connection->fetchAll($query);

        self::assertNotEmpty($aggregatedCustomOrderFees);
    }

    public function testThrowsExceptionIfDatabaseServerVersionIsUnsupported(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Unsupported database server version "10.4.34-MariaDB".');

        $connectionStub = $this->createStub(AdapterInterface::class);
        $resourcesStub = $this->createStub(ResourceConnection::class);
        $contextStub = $this->createStub(Context::class);

        $connectionStub
            ->method('fetchOne')
            ->willReturn('10.4.34-MariaDB');

        $resourcesStub
            ->method('getConnection')
            ->willReturn($connectionStub);

        $contextStub
            ->method('getResources')
            ->willReturn($resourcesStub);

        $objectManager = Bootstrap::getObjectManager();
        /** @var CustomOrderFees $customOrderFeesReport */
        $customOrderFeesReport = $objectManager->create(
            CustomOrderFees::class,
            [
                'context' => $contextStub,
            ]
        );

        $customOrderFeesReport->aggregate();
    }

    /**
     * @dataProvider checksIfDatabaseServerVersionIsSupportedDataProvider
     */
    public function testChecksIfDatabaseServerVersionIsSupported(
        string $databaseServerVersion,
        bool $expectedResult
    ): void {
        $connectionStub = $this->createStub(AdapterInterface::class);
        $resourcesStub = $this->createStub(ResourceConnection::class);
        $contextStub = $this->createStub(Context::class);

        $connectionStub
            ->method('fetchOne')
            ->willReturn($databaseServerVersion);

        $resourcesStub
            ->method('getConnection')
            ->willReturn($connectionStub);

        $contextStub
            ->method('getResources')
            ->willReturn($resourcesStub);

        $objectManager = Bootstrap::getObjectManager();
        /** @var CustomOrderFees $customOrderFeesReport */
        $customOrderFeesReport = $objectManager->create(
            CustomOrderFees::class,
            [
                'context' => $contextStub,
            ]
        );

        $actualResult = $customOrderFeesReport->isDatabaseServerSupported($databaseServerVersion);

        self::assertSame($expectedResult, $actualResult);
    }

    /**
     * @return array<string, array<string, string|bool>>
     */
    public static function checksIfDatabaseServerVersionIsSupportedDataProvider(): array
    {
        return [
            'MySQL 8.0 is supported' => [
                'databaseServerVersion' => '8.0.43',
                'expectedResult' => true,
            ],
            'MySQL 5.7 is unsupported' => [
                'databaseServerVersion' => '5.7.44',
                'expectedResult' => false,
            ],
            'MariaDB 10.6 is supported' => [
                'databaseServerVersion' => '10.6.21-MariaDB',
                'expectedResult' => true,
            ],
            'MariaDB 10.4 is unsupported' => [
                'databaseServerVersion' => '10.4.34-MariaDB',
                'expectedResult' => false,
            ],
            'AWS Aurora MySQL 3.04 is supported' => [
                'databaseServerVersion' => '8.0.mysql_aurora.3.04.0',
                'expectedResult' => true,
            ],
            'AWS Aurora MySQL 2.11 is unsupported' => [
                'databaseServerVersion' => '5.7.mysql_aurora.2.11.2',
                'expectedResult' => false,
            ],
            'Percona MySQL 8.0 is supported' => [
                'databaseServerVersion' => '8.0.41-32',
                'expectedResult' => true,
            ],
            'Percona MySQL 5.7 is unsupported' => [
                'databaseServerVersion' => '5.7.10-3',
                'expectedResult' => false,
            ],
        ];
    }
}
