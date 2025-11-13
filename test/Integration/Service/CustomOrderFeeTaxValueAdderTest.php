<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Service;

use JosephLeedy\CustomFees\Api\CustomOrderFeesRepositoryInterface;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFeeInterface;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFeesInterface;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFeesInterfaceFactory;
use JosephLeedy\CustomFees\Model\CustomOrderFeesRepository;
use JosephLeedy\CustomFees\Model\FeeType;
use JosephLeedy\CustomFees\Model\ResourceModel\CustomOrderFees as CustomOrderFeesResourceModel;
use JosephLeedy\CustomFees\Model\ResourceModel\CustomOrderFees\CollectionFactory as CustomOrderFeesCollectionFactory;
use JosephLeedy\CustomFees\Service\CustomOrderFeeTaxValueAdder;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

use function __;

final class CustomOrderFeeTaxValueAdderTest extends TestCase
{
    #[DataFixture('JosephLeedy_CustomFees::../test/Integration/_files/custom_order_fees_without_taxes.php')]
    public function testAddsTaxValuesToExistingCustomOrderFees(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var CustomOrderFeeTaxValueAdder $customOrderFeeTaxValueAdder */
        $customOrderFeeTaxValueAdder = $objectManager->create(CustomOrderFeeTaxValueAdder::class);
        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        /** @var CustomOrderFeesRepositoryInterface $customOrderFeesRepository */
        $customOrderFeesRepository = $objectManager->create(CustomOrderFeesRepositoryInterface::class);

        $customOrderFeeTaxValueAdder->addTaxValues();

        $order->loadByIncrementId('100000001');

        /** @var CustomOrderFeesInterface $expectedCustomOrderFees */
        $expectedCustomOrderFees = $objectManager->create(
            CustomOrderFeesInterface::class,
            [
                'data' => [
                    'id' => '1',
                    'order_entity_id' => $order->getEntityId(),
                    'custom_fees_ordered' => [
                        'test_fee_0' => [
                            'code' => 'test_fee_0',
                            'title' => 'Test Fee',
                            'type' => FeeType::Fixed->value,
                            'percent' => null,
                            'show_percentage' => false,
                            'base_value' => 10,
                            'value' => 10,
                            'base_value_with_tax' => 10,
                            'value_with_tax' => 10,
                            'base_tax_amount' => 0,
                            'tax_amount' => 0,
                            'tax_rate' => 0,
                        ],
                        'test_fee_1' => [
                            'code' => 'test_fee_1',
                            'title' => 'Another Test Fee',
                            'type' => FeeType::Percent->value,
                            'percent' => 5,
                            'show_percentage' => true,
                            'base_value' => 4,
                            'value' => 4,
                            'base_value_with_tax' => 4,
                            'value_with_tax' => 4,
                            'base_tax_amount' => 0,
                            'tax_amount' => 0,
                            'tax_rate' => 0,
                        ],
                    ],
                    'custom_fees_invoiced' => [
                        1 => [
                            'test_fee_0' => [
                                'invoice_id' => 1,
                                'code' => 'test_fee_0',
                                'title' => 'Test Fee',
                                'type' => FeeType::Fixed->value,
                                'percent' => null,
                                'show_percentage' => false,
                                'base_value' => 10,
                                'value' => 10,
                                'base_value_with_tax' => 10,
                                'value_with_tax' => 10,
                                'base_tax_amount' => 0,
                                'tax_amount' => 0,
                                'tax_rate' => 0,
                            ],
                            'test_fee_1' => [
                                'invoice_id' => 1,
                                'code' => 'test_fee_1',
                                'title' => 'Another Test Fee',
                                'type' => FeeType::Percent->value,
                                'percent' => 5,
                                'show_percentage' => true,
                                'base_value' => 4,
                                'value' => 4,
                                'base_value_with_tax' => 4,
                                'value_with_tax' => 4,
                                'base_tax_amount' => 0,
                                'tax_amount' => 0,
                                'tax_rate' => 0,
                            ],
                        ],
                    ],
                    'custom_fees_refunded' => [
                        1 => [
                            'test_fee_0' => [
                                'credit_memo_id' => 1,
                                'code' => 'test_fee_0',
                                'title' => 'Test Fee',
                                'type' => FeeType::Fixed->value,
                                'percent' => null,
                                'show_percentage' => false,
                                'base_value' => 10,
                                'value' => 10,
                                'base_value_with_tax' => 10,
                                'value_with_tax' => 10,
                                'base_tax_amount' => 0,
                                'tax_amount' => 0,
                                'tax_rate' => 0,
                            ],
                            'test_fee_1' => [
                                'credit_memo_id' => 1,
                                'code' => 'test_fee_1',
                                'title' => 'Another Test Fee',
                                'type' => FeeType::Percent->value,
                                'percent' => 5,
                                'show_percentage' => true,
                                'base_value' => 4,
                                'value' => 4,
                                'base_value_with_tax' => 4,
                                'value_with_tax' => 4,
                                'base_tax_amount' => 0,
                                'tax_amount' => 0,
                                'tax_rate' => 0,
                            ],
                        ],
                    ],
                ],
            ],
        );
        $actualCustomOrderFees = $customOrderFeesRepository->getByOrderId($order->getEntityId());

        self::assertEquals($expectedCustomOrderFees->getData(), $actualCustomOrderFees->getData());
    }

    public function testDoesNotAddTaxValuesIfNoCustomFeesExist(): void
    {
        $this->expectNotToPerformAssertions();

        $objectManager = Bootstrap::getObjectManager();
        /** @var CustomOrderFeeTaxValueAdder $customOrderFeeTaxValueAdder */
        $customOrderFeeTaxValueAdder = $objectManager->create(CustomOrderFeeTaxValueAdder::class);

        $customOrderFeeTaxValueAdder->addTaxValues();
    }

    #[DataFixture('JosephLeedy_CustomFees::../test/Integration/_files/order_with_custom_fees_taxed.php')]
    public function testDoesNotAddTaxValuesIfCustomFeesAlreadyHaveTaxValues(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var CustomOrderFeeTaxValueAdder $customOrderFeeTaxValueAdder */
        $customOrderFeeTaxValueAdder = $objectManager->create(CustomOrderFeeTaxValueAdder::class);
        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        /** @var CustomOrderFeesRepositoryInterface $customOrderFeesRepository */
        $customOrderFeesRepository = $objectManager->create(CustomOrderFeesRepositoryInterface::class);

        $customOrderFeeTaxValueAdder->addTaxValues();

        $order->loadByIncrementId('100000001');

        $expectedCustomFeesOrdered = [
            'test_fee_0' => $objectManager->create(
                CustomOrderFeeInterface::class,
                [
                    'data' => [
                        'code' => 'test_fee_0',
                        'title' => 'Test Fee',
                        'type' => FeeType::Fixed,
                        'percent' => null,
                        'show_percentage' => false,
                        'base_value' => 5.00,
                        'value' => 5.00,
                        'base_value_with_tax' => 5.30,
                        'value_with_tax' => 5.30,
                        'base_tax_amount' => 0.30,
                        'tax_amount' => 0.30,
                        'tax_rate' => 6.00,
                    ],
                ],
            ),
            'test_fee_1' => $objectManager->create(
                CustomOrderFeeInterface::class,
                [
                    'data' => [
                        'code' => 'test_fee_1',
                        'title' => 'Another Test Fee',
                        'type' => FeeType::Fixed,
                        'percent' => null,
                        'show_percentage' => false,
                        'base_value' => 1.50,
                        'value' => 1.50,
                        'base_value_with_tax' => 1.59,
                        'value_with_tax' => 1.59,
                        'base_tax_amount' => 0.09,
                        'tax_amount' => 0.09,
                        'tax_rate' => 6.00,
                    ],
                ],
            ),
        ];
        $actualCustomOrderFees = $customOrderFeesRepository->getByOrderId($order->getEntityId());

        self::assertEquals($expectedCustomFeesOrdered, $actualCustomOrderFees->getCustomFeesOrdered());
    }

    #[DataFixture('JosephLeedy_CustomFees::../test/Integration/_files/custom_order_fees_without_taxes.php')]
    public function testThrowsExceptionIfTaxValuesCannotBeSaved(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $customOrderFeesRepositoryStub = $this
            ->getMockBuilder(CustomOrderFeesRepository::class)
            ->onlyMethods(['save'])
            ->setConstructorArgs(
                [
                    'customOrderFeesFactory' => $objectManager->create(CustomOrderFeesInterfaceFactory::class),
                    'resourceModel' => $objectManager->create(CustomOrderFeesResourceModel::class),
                    'collectionFactory' => $objectManager->create(CustomOrderFeesCollectionFactory::class),
                    'searchResultsFactory' => $objectManager->create(SearchResultsInterfaceFactory::class),
                    'collectionProcessor' => $objectManager->create(CollectionProcessorInterface::class),
                ],
            )->getMock();
        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        /** @var CustomOrderFeesRepositoryInterface $customOrderFeesRepository */
        $customOrderFeesRepository = $objectManager->create(CustomOrderFeesRepositoryInterface::class);
        /** @var CustomOrderFeeTaxValueAdder $customOrderFeeTaxValueAdder */
        $customOrderFeeTaxValueAdder = $objectManager->create(
            CustomOrderFeeTaxValueAdder::class,
            [
                'customOrderFeesRepository' => $customOrderFeesRepositoryStub,
            ],
        );

        $order->loadByIncrementId('100000001');

        /** @var CouldNotSaveException $couldNotSaveException */
        $couldNotSaveException = $objectManager->create(
            CouldNotSaveException::class,
            [
                'phrase' => __(
                    'Could not save custom fees for order with ID "%1". Error: "%2"',
                    $order->getEntityId(),
                    '2006: MySQL server has gone away',
                ),
            ],
        );

        $customOrderFeesRepositoryStub
            ->method('save')
            ->willThrowException($couldNotSaveException);

        $customOrderFees = $customOrderFeesRepository->getByOrderId($order->getEntityId());
        $localizedException = $objectManager->create(
            LocalizedException::class,
            [
                'phrase' => __('Could not add taxes to custom order fees with ID "%1".', $customOrderFees->getId()),
                'cause' => $couldNotSaveException,
            ],
        );

        $this->expectExceptionObject($localizedException);

        $customOrderFeeTaxValueAdder->addTaxValues();
    }
}
