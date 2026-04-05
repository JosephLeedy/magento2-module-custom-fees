<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Model\Config\Backend\CustomOrderFeesReport;

use JosephLeedy\CustomFees\Model\Config\Backend\CustomOrderFeesReport\AggregationCron;
use Magento\Config\Model\Config;
use Magento\Framework\App\Area;
use Magento\Framework\App\Config\Value;
use Magento\Framework\App\Config\ValueFactory;
use Magento\Framework\App\Config\ValueInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Zend_Db_Exception;

use function __;

#[AppArea(Area::AREA_ADMINHTML)]
#[AppIsolation(true)]
final class AggregationCronTest extends TestCase
{
    public function testSavesCronExpression(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var Config $configModel */
        $configModel = $objectManager->create(
            Config::class,
            [
                'data' => [
                    'section' => 'reports',
                    'website' => null,
                    'store' => null,
                    'groups' => [
                        'custom_order_fees' => [
                            'fields' => [
                                'enable_aggregation' => [
                                    'value' => '1',
                                ],
                                'aggregation_time' => [
                                    'value' => ['01', '00', '00'],
                                ],
                                'aggregation_frequency' => [
                                    'value' => 'D',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        );

        $configModel->save();

        /** @var ValueInterface&Value $cronExpressionConfigValue */
        $cronExpressionConfigValue = $objectManager->create(ValueInterface::class);
        $cronExpressionConfigValue->load(AggregationCron::CRON_EXPRESSION_PATH, 'path');

        self::assertEquals('0 1 * * *', $cronExpressionConfigValue->getValue());
    }

    public function testDoesNotSaveCronExpressionIfAggregationIsDisabled(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var Config $configModel */
        $configModel = $objectManager->create(
            Config::class,
            [
                'data' => [
                    'section' => 'reports',
                    'website' => null,
                    'store' => null,
                    'groups' => [
                        'custom_order_fees' => [
                            'fields' => [
                                'enable_aggregation' => [
                                    'value' => '0',
                                ],
                                'aggregation_time' => [
                                    'value' => ['01', '00', '00'],
                                ],
                                'aggregation_frequency' => [
                                    'value' => 'D',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        );

        $configModel->save();

        /** @var ValueInterface&Value $cronExpressionConfigValue */
        $cronExpressionConfigValue = $objectManager->create(ValueInterface::class);
        $cronExpressionConfigValue->load(AggregationCron::CRON_EXPRESSION_PATH, 'path');

        self::assertNull($cronExpressionConfigValue->getValue());
    }

    public function testThrowsExceptionIfCronExpressionCannotBeSaved(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $databaseException = new Zend_Db_Exception('2006: MySQL server has gone away');
        $cronExpressionConfigValue = $this->createStub(Value::class);
        $configValueFactoryStub = $this->createStub(ValueFactory::class);
        /** @var LocalizedException $couldNotSaveException */
        $couldNotSaveException = $objectManager->create(
            LocalizedException::class,
            [
                'phrase' => __('Could not save Cron expression for custom order fees report aggregation.'),
                'cause' => $databaseException,
            ],
        );
        /** @var Config $configModel */
        $configModel = $objectManager->create(
            Config::class,
            [
                'data' => [
                    'section' => 'reports',
                    'website' => null,
                    'store' => null,
                    'groups' => [
                        'custom_order_fees' => [
                            'fields' => [
                                'enable_aggregation' => [
                                    'value' => '1',
                                ],
                                'aggregation_time' => [
                                    'value' => ['01', '00', '00'],
                                ],
                                'aggregation_frequency' => [
                                    'value' => 'D',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        );

        $cronExpressionConfigValue->method('load')->willReturnSelf();
        $cronExpressionConfigValue->method('__call')->willReturnSelf(); // `setPath` and `setValue` methods
        $cronExpressionConfigValue->method('save')->willThrowException($databaseException);

        $configValueFactoryStub->method('create')->willReturn($cronExpressionConfigValue);

        $objectManager->addSharedInstance($configValueFactoryStub, ValueFactory::class);

        $this->expectExceptionObject($couldNotSaveException);

        $configModel->save();
    }
}
