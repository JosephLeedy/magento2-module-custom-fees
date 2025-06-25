<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Model\Config\Backend\File;

use DateTimeImmutable;
use DateTimeImmutableFactory;
use Exception;
use JosephLeedy\CustomFees\Api\ConfigInterface;
use JosephLeedy\CustomFees\Model\Config\Backend\File\ImportCustomFees;
use Magento\Config\Model\Config\Backend\File\RequestData\RequestDataInterface;
use Magento\Framework\App\Area;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\File\Csv;
use Magento\Store\Model\ScopeInterface as StoreScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\Config as ConfigFixture;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Zend_Db_Statement_Exception;

use function __;
use function json_decode;

use const JSON_THROW_ON_ERROR;

#[AppArea(Area::AREA_ADMINHTML)]
final class ImportCustomFeesTest extends TestCase
{
    public function testImportsCustomFees(): void
    {
        $requestDataStub = $this->createStub(RequestDataInterface::class);
        $csvStub = $this->createStub(Csv::class);
        $dateTimeImmutableFactoryStub = $this->createStub(DateTimeImmutableFactory::class);
        $dateTimeImmutableStub = $this->createStub(DateTimeImmutable::class);
        $objectManager = Bootstrap::getObjectManager();
        /** @var ImportCustomFees $importCustomFees */
        $importCustomFees = $objectManager->create(
            ImportCustomFees::class,
            [
                'requestData' => $requestDataStub,
                'csv' => $csvStub,
                'dateTimeFactory' => $dateTimeImmutableFactoryStub,
            ],
        );

        $requestDataStub
            ->method('getTmpName')
            ->willReturn('/tmp/217ac83d');
        $requestDataStub
            ->method('getName')
            ->willReturn('custom-fees.csv');

        $csvStub
            ->method('getData')
            ->willReturn(
                [
                    [
                        'code',
                        'title',
                        'type',
                        'show_percentage',
                        'value',
                    ],
                    [
                        'test_fee_0',
                        'Test Fee',
                        'fixed',
                        'no',
                        '4.00',
                    ],
                    [
                        'test_fee_1',
                        'Another Fee',
                        'percent',
                        'yes',
                        '1.00',
                    ],
                ],
            );

        $dateTimeImmutableFactoryStub
            ->method('create')
            ->willReturn($dateTimeImmutableStub);

        $dateTimeImmutableStub
            ->method('format')
            ->willReturn('_1746570703638_638', '_1746570704381_381');

        $importCustomFees->setHasDataChanges(true);
        $importCustomFees->save();

        $expectedCustomFees = [
            '_1746570703638_638' => [
                'code' => 'test_fee_0',
                'title' => 'Test Fee',
                'type' => 'fixed',
                'value' => '4.00',
                'advanced' => '{"show_percentage":"0"}',
            ],
            '_1746570704381_381' => [
                'code' => 'test_fee_1',
                'title' => 'Another Fee',
                'type' => 'percent',
                'value' => '1.00',
                'advanced' => '{"show_percentage":"1"}',
            ],
        ];
        $actualCustomFees = $this->getCustomFees();

        self::assertSame($expectedCustomFees, $actualCustomFees);
    }

    #[AppIsolation(true)]
    #[ConfigFixture(
        ConfigInterface::CONFIG_PATH_CUSTOM_FEES,
        '{"_1727299833817_817":{"code":"test_fee_0","title":"Test Fee","type":"fixed","value":"4.00","advanced":'
        . '"{\\"show_percentage\\":\\"0\\"}"},"_1727299843197_197":{"code":"test_fee_1","title":"Another Fee","type":'
        . '"percent","value":"1.00","advanced":"{\\"show_percentage\\":\\"1\\"}"}}',
        StoreScopeInterface::SCOPE_STORE,
        'default',
    )]
    public function testImportsCustomFeesAndReplacesExistingConfiguration(): void
    {
        $requestDataStub = $this->createStub(RequestDataInterface::class);
        $csvStub = $this->createStub(Csv::class);
        $dateTimeImmutableFactoryStub = $this->createStub(DateTimeImmutableFactory::class);
        $dateTimeImmutableStub = $this->createStub(DateTimeImmutable::class);
        $objectManager = Bootstrap::getObjectManager();
        /** @var RequestInterface $request */
        $request = $objectManager->get(RequestInterface::class);
        /** @var StoreManagerInterface $storeManager */
        $storeManager = $objectManager->get(StoreManagerInterface::class);
        $storeId = $storeManager->getStore()->getId();
        /** @var ImportCustomFees $importCustomFees */
        $importCustomFees = $objectManager->create(
            ImportCustomFees::class,
            [
                'requestData' => $requestDataStub,
                'csv' => $csvStub,
                'dateTimeFactory' => $dateTimeImmutableFactoryStub,
                'request' => $request,
            ],
        );

        $requestDataStub
            ->method('getTmpName')
            ->willReturn('/tmp/729d84ac');
        $requestDataStub
            ->method('getName')
            ->willReturn('custom-fees.csv');

        $csvStub
            ->method('getData')
            ->willReturn(
                [
                    [
                        'code',
                        'title',
                        'type',
                        'show_percentage',
                        'value',
                    ],
                    [
                        'processing_fee',
                        'Processing Fee',
                        'fixed',
                        'no',
                        '5.00',
                    ],
                    [
                        'tariff',
                        'Tariff',
                        'fixed',
                        'no',
                        '20.00',
                    ],
                ],
            );

        $dateTimeImmutableFactoryStub
            ->method('create')
            ->willReturn($dateTimeImmutableStub);

        $dateTimeImmutableStub
            ->method('format')
            ->willReturn('_1746639728881_881', '_1746639729624_624');

        $request->setParams(
            [
                'groups' => [
                    'custom_order_fees' => [
                        'fields' => [
                            'import_custom_fees' => [
                                'replace_existing' => '1',
                            ],
                        ],
                    ],
                ],
            ],
        );

        $importCustomFees->setScope(StoreScopeInterface::SCOPE_STORES);
        $importCustomFees->setScopeId($storeId);
        $importCustomFees->setHasDataChanges(true);
        $importCustomFees->save();

        $expectedCustomFees = [
            '_1746639728881_881' => [
                'code' => 'processing_fee',
                'title' => 'Processing Fee',
                'type' => 'fixed',
                'value' => '5.00',
                'advanced' => '{"show_percentage":"0"}',
            ],
            '_1746639729624_624' => [
                'code' => 'tariff',
                'title' => 'Tariff',
                'type' => 'fixed',
                'value' => '20.00',
                'advanced' => '{"show_percentage":"0"}',
            ],
        ];
        $actualCustomFees = $this->getCustomFees(StoreScopeInterface::SCOPE_STORES, (int) $storeId);

        self::assertSame($expectedCustomFees, $actualCustomFees);
    }

    #[AppIsolation(true)]
    #[ConfigFixture(
        ConfigInterface::CONFIG_PATH_CUSTOM_FEES,
        '{"_1727299833817_817":{"code":"test_fee_0","title":"Test Fee","type":"fixed","value":"4.00","advanced":'
        . '"{\\"show_percentage\\":\\"0\\"}"},"_1727299843197_197":{"code":"test_fee_1","title":"Another Fee","type":'
        . '"percent","value":"1.00","advanced":"{\\"show_percentage\\":\\"1\\"}"}}',
        StoreScopeInterface::SCOPE_STORE,
        'default',
    )]
    public function testImportsCustomFeesAndDoesNotReplaceExistingConfiguration(): void
    {
        $requestDataStub = $this->createStub(RequestDataInterface::class);
        $csvStub = $this->createStub(Csv::class);
        $dateTimeImmutableFactoryStub = $this->createStub(DateTimeImmutableFactory::class);
        $dateTimeImmutableStub = $this->createStub(DateTimeImmutable::class);
        $objectManager = Bootstrap::getObjectManager();
        /** @var StoreManagerInterface $storeManager */
        $storeManager = $objectManager->get(StoreManagerInterface::class);
        $storeId = $storeManager->getStore()->getId();
        /** @var ImportCustomFees $importCustomFees */
        $importCustomFees = $objectManager->create(
            ImportCustomFees::class,
            [
                'requestData' => $requestDataStub,
                'csv' => $csvStub,
                'dateTimeFactory' => $dateTimeImmutableFactoryStub,
            ],
        );

        $requestDataStub
            ->method('getTmpName')
            ->willReturn('/tmp/729d84ac');
        $requestDataStub
            ->method('getName')
            ->willReturn('custom-fees.csv');

        $csvStub
            ->method('getData')
            ->willReturn(
                [
                    [
                        'code',
                        'title',
                        'type',
                        'show_percentage',
                        'value',
                    ],
                    [
                        'processing_fee',
                        'Processing Fee',
                        'fixed',
                        'no',
                        '5.00',
                    ],
                    [
                        'tariff',
                        'Tariff',
                        'fixed',
                        'no',
                        '20.00',
                    ],
                ],
            );

        $dateTimeImmutableFactoryStub
            ->method('create')
            ->willReturn($dateTimeImmutableStub);

        $dateTimeImmutableStub
            ->method('format')
            ->willReturn('_1746639728881_881', '_1746639729624_624');

        $importCustomFees->setScope(StoreScopeInterface::SCOPE_STORES);
        $importCustomFees->setScopeId($storeId);
        $importCustomFees->setHasDataChanges(true);
        $importCustomFees->save();

        $expectedCustomFees = [
            '_1727299833817_817' => [
                'code' => 'test_fee_0',
                'title' => 'Test Fee',
                'type' => 'fixed',
                'value' => '4.00',
                'advanced' => '{"show_percentage":"0"}',
            ],
            '_1727299843197_197' => [
                'code' => 'test_fee_1',
                'title' => 'Another Fee',
                'type' => 'percent',
                'value' => '1.00',
                'advanced' => '{"show_percentage":"1"}',
            ],
            '_1746639728881_881' => [
                'code' => 'processing_fee',
                'title' => 'Processing Fee',
                'type' => 'fixed',
                'value' => '5.00',
                'advanced' => '{"show_percentage":"0"}',
            ],
            '_1746639729624_624' => [
                'code' => 'tariff',
                'title' => 'Tariff',
                'type' => 'fixed',
                'value' => '20.00',
                'advanced' => '{"show_percentage":"0"}',
            ],
        ];
        $actualCustomFees = $this->getCustomFees(StoreScopeInterface::SCOPE_STORES, (int) $storeId);

        self::assertSame($expectedCustomFees, $actualCustomFees);
    }

    public function testDoesNotImportCustomFeesIfFileWasNotUploaded(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var ImportCustomFees $importCustomFees */
        $importCustomFees = $objectManager->create(ImportCustomFees::class);

        $importCustomFees->setHasDataChanges(true);
        $importCustomFees->save();

        self::assertEmpty($this->getCustomFees());
    }

    public function testThrowsExceptionIfUploadedSpreadsheetIsNotCsvFile(): void
    {
        $requestDataStub = $this->createStub(RequestDataInterface::class);
        $objectManager = Bootstrap::getObjectManager();
        /** @var LocalizedException $invalidFileTypeException */
        $invalidFileTypeException = $objectManager->create(
            LocalizedException::class,
            [
                'phrase' => __('Custom Fees spreadsheet must be a CSV file.'),
            ],
        );
        /** @var ImportCustomFees $importCustomFees */
        $importCustomFees = $objectManager->create(
            ImportCustomFees::class,
            [
                'requestData' => $requestDataStub,
            ],
        );

        $requestDataStub
            ->method('getTmpName')
            ->willReturn('/tmp/ca527ebf');
        $requestDataStub
            ->method('getName')
            ->willReturn('custom-fees.xls');

        $this->expectExceptionObject($invalidFileTypeException);

        $importCustomFees->setHasDataChanges(true);
        $importCustomFees->save();
    }

    public function testThrowsExceptionIfUploadedSpreadsheetIsCannotBeProcessed(): void
    {
        $requestDataStub = $this->createStub(RequestDataInterface::class);
        $csvStub = $this->createStub(Csv::class);
        $objectManager = Bootstrap::getObjectManager();
        $fileDoesNotExistException = new Exception("File \"/tmp/460d2a7f\" does not exist.");
        /** @var LocalizedException $unreadableFileException */
        $unreadableFileException = $objectManager->create(
            LocalizedException::class,
            [
                'phrase' => __('Could not read Custom Fees spreadsheet.'),
                'cause' => $fileDoesNotExistException,
            ],
        );
        /** @var ImportCustomFees $importCustomFees */
        $importCustomFees = $objectManager->create(
            ImportCustomFees::class,
            [
                'requestData' => $requestDataStub,
                'csv' => $csvStub,
            ],
        );

        $requestDataStub
            ->method('getTmpName')
            ->willReturn('/tmp/460d2a7f');
        $requestDataStub
            ->method('getName')
            ->willReturn('custom-fees.csv');

        $csvStub
            ->method('getData')
            ->willThrowException($fileDoesNotExistException);

        $this->expectExceptionObject($unreadableFileException);

        $importCustomFees->setHasDataChanges(true);
        $importCustomFees->save();
    }

    public function testThrowsExceptionIfUploadedSpreadsheetHasIncorrectHeaders(): void
    {
        $requestDataStub = $this->createStub(RequestDataInterface::class);
        $csvStub = $this->createStub(Csv::class);
        $objectManager = Bootstrap::getObjectManager();
        /** @var LocalizedException $invalidFileException */
        $invalidFileException = $objectManager->create(
            LocalizedException::class,
            [
                'phrase' => __('Invalid Custom Fees spreadsheet.'),
            ],
        );
        /** @var ImportCustomFees $importCustomFees */
        $importCustomFees = $objectManager->create(
            ImportCustomFees::class,
            [
                'requestData' => $requestDataStub,
                'csv' => $csvStub,
            ],
        );

        $requestDataStub
            ->method('getTmpName')
            ->willReturn('/tmp/fc3aeb7');
        $requestDataStub
            ->method('getName')
            ->willReturn('custom-fees.csv');

        $csvStub
            ->method('getData')
            ->willReturn(
                [
                    [
                        'key',
                        'name',
                        'fee_type',
                        'show_percent',
                        'amount',
                    ],
                    [
                        'test_fee_0',
                        'Test Fee',
                        'fixed',
                        'no',
                        '4.00',
                    ],
                    [
                        'test_fee_1',
                        'Another Fee',
                        'percent',
                        'no',
                        '1.00',
                    ],
                ],
            );

        $this->expectExceptionObject($invalidFileException);

        $importCustomFees->setHasDataChanges(true);
        $importCustomFees->save();
    }

    public function testThrowsExceptionIfUploadedSpreadsheetHasInvalidFeeType(): void
    {
        $requestDataStub = $this->createStub(RequestDataInterface::class);
        $csvStub = $this->createStub(Csv::class);
        $objectManager = Bootstrap::getObjectManager();
        /** @var LocalizedException $invalidFeeTypeException */
        $invalidFeeTypeException = $objectManager->create(
            LocalizedException::class,
            [
                'phrase' => __('Invalid custom fee type "%1".', 'invalid'),
            ],
        );
        /** @var ImportCustomFees $importCustomFees */
        $importCustomFees = $objectManager->create(
            ImportCustomFees::class,
            [
                'requestData' => $requestDataStub,
                'csv' => $csvStub,
            ],
        );

        $requestDataStub
            ->method('getTmpName')
            ->willReturn('/tmp/16cf42b');
        $requestDataStub
            ->method('getName')
            ->willReturn('custom-fees.csv');

        $csvStub
            ->method('getData')
            ->willReturn(
                [
                    [
                        'code',
                        'title',
                        'type',
                        'show_percentage',
                        'value',
                    ],
                    [
                        'processing_fee',
                        'Processing Fee',
                        'fixed',
                        'no',
                        '5.00',
                    ],
                    [
                        'invalid_fee',
                        'Invalid Fee',
                        'invalid',
                        'no',
                        '2.00',
                    ],
                ],
            );

        $this->expectExceptionObject($invalidFeeTypeException);

        $importCustomFees->setHasDataChanges(true);
        $importCustomFees->save();
    }

    /**
     * @return array{}|array<string, array{code: string, title: string, value: string}>
     * @throws Zend_Db_Statement_Exception
     */
    private function getCustomFees(string $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT, int $scopeId = 0): array
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var ResourceConnection $resourceConnection */
        $resourceConnection = $objectManager->get(ResourceConnection::class);
        $connection = $resourceConnection->getConnection();
        /* This query is used as a work-around for the Scope Config model not returning the correct data, even after
           clearing the config cache. */
        $select = $connection
            ->select()
            ->from($resourceConnection->getTableName('core_config_data'), 'value')
            ->where('scope = ?', $scope)
            ->where('scope_id = ?', $scopeId)
            ->where('path = ?', ConfigInterface::CONFIG_PATH_CUSTOM_FEES)
            ->limit(1);
        $rawCustomFees = $connection->query($select)->fetchColumn() ?: '[]';
        $customFees = json_decode($rawCustomFees, true, JSON_THROW_ON_ERROR);

        return $customFees;
    }
}
