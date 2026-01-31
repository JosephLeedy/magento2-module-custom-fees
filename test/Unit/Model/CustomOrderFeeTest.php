<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Unit\Model;

use InvalidArgumentException;
use JosephLeedy\CustomFees\Api\Data\FeeTypeInterface;
use JosephLeedy\CustomFees\Model\CustomOrderFee;
use JosephLeedy\CustomFees\Model\FeeType;
use JosephLeedy\CustomFees\Service\DataObjectPropertyTypeConverter;
use Magento\Framework\App\State;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\Tax\Api\Data\AppliedTaxInterfaceFactory;
use Magento\Tax\Api\Data\AppliedTaxRateInterfaceFactory;
use Magento\Tax\Model\TaxDetails\AppliedTax;
use Magento\Tax\Model\TaxDetails\AppliedTaxRate;
use PHPUnit\Framework\TestCase;

use function __;

final class CustomOrderFeeTest extends TestCase
{
    public function testConstructorSetsDefaultValuesForMissingProperties(): void
    {
        $dataObjectDataValidator = $this->createStub(DataObjectPropertyTypeConverter::class);
        $state = $this->createStub(State::class);
        $expectedData = [
            'code' => 'test_fee_0',
            'title' => 'Test Fee',
            'type' => FeeType::Fixed,
            'percent' => null,
            'show_percentage' => true,
            'base_value' => 5.00,
            'value' => 5.00,
        ];
        $data = $expectedData;

        unset($data['type'], $data['percent'], $data['show_percentage']);

        $customOrderFee = new CustomOrderFee($dataObjectDataValidator, $state, $data);
        $actualData = $customOrderFee->__toArray();

        self::assertEquals($expectedData, $actualData);
    }

    public function testConstructorSetsTypePropertyFromString(): void
    {
        $dataObjectDataValidator = $this->createStub(DataObjectPropertyTypeConverter::class);
        $state = $this->createStub(State::class);
        $data = [
            'code' => 'test_fee_0',
            'title' => 'Test Fee',
            'type' => 'fixed',
            'percent' => null,
            'show_percentage' => true,
            'base_value' => 5.00,
            'value' => 5.00,
        ];

        $customOrderFee = new CustomOrderFee($dataObjectDataValidator, $state, $data);
        $expectedData = $data;
        $expectedData['type'] = FeeType::Fixed;
        $actualData = $customOrderFee->__toArray();

        self::assertEquals($expectedData, $actualData);
    }

    public function testConstructorThrowsExceptionIfTypePropertyIsInvalid(): void
    {
        $this->expectExceptionObject(
            new InvalidArgumentException((string) __('Invalid custom fee type "%1".', 'invalid')),
        );

        $dataObjectDataValidator = $this->createStub(DataObjectPropertyTypeConverter::class);
        $state = $this->createStub(State::class);
        $data = [
            'code' => 'test_fee_0',
            'title' => 'Test Fee',
            'type' => 'invalid',
            'percent' => null,
            'show_percentage' => true,
            'base_value' => 5.00,
            'value' => 5.00,
        ];

        new CustomOrderFee($dataObjectDataValidator, $state, $data);
    }

    /**
     * @dataProvider getTypeReturnsCorrectTypeByAreaDataProvider
     */
    public function testGetTypeReturnsCorrectTypeByArea(string $area, FeeTypeInterface|string $expectedType): void
    {
        $dataObjectDataValidator = $this->createStub(DataObjectPropertyTypeConverter::class);
        $state = $this->createStub(State::class);

        $state->method('getAreaCode')->willReturn($area);

        $customOrderFee = new CustomOrderFee($dataObjectDataValidator, $state);

        $customOrderFee->setType(FeeType::Fixed);

        self::assertEquals($expectedType, $customOrderFee->getType());
    }

    /**
     * @dataProvider setAppliedTaxesConvertsDataToModelsDataProvider
     * @param string|array<string, AppliedTaxData> $appliedTaxesData
     */
    public function testSetAppliedTaxesConvertsDataToModels(string|array $appliedTaxesData): void
    {
        $appliedTaxRateStub = $this->createStub(AppliedTaxRate::class);
        $appliedTaxStub = $this->createStub(AppliedTax::class);
        $appliedTaxFactoryStub = $this->createStub(AppliedTaxInterfaceFactory::class);
        $appliedTaxRateFactoryStub = $this->createStub(AppliedTaxRateInterfaceFactory::class);
        $dataObjectPropertyTypeConverterStub = $this->createStub(DataObjectPropertyTypeConverter::class);
        $stateStub = $this->createStub(State::class);
        $jsonSerializer = new JsonSerializer();
        $customOrderFee = new CustomOrderFee(
            $dataObjectPropertyTypeConverterStub,
            $stateStub,
            $jsonSerializer,
            $appliedTaxFactoryStub,
            $appliedTaxRateFactoryStub,
        );

        $appliedTaxStub->method('getRates')->willReturn([$appliedTaxRateStub]);

        $appliedTaxFactoryStub->method('create')->willReturn($appliedTaxStub);

        $appliedTaxRateFactoryStub->method('create')->willReturn($appliedTaxRateStub);

        $customOrderFee->setAppliedTaxes($appliedTaxesData);

        $expectedAppliedTaxes = [
            'US-*-*' => $appliedTaxStub,
        ];
        $actualAppliedTaxes = $customOrderFee->getAppliedTaxes();

        self::assertEquals($expectedAppliedTaxes, $actualAppliedTaxes);
    }

    /**
     * @dataProvider getLabelReturnsLabelDataProvider
     */
    public function testGetLabelReturnsLabel(string $prefix, FeeType $feeType): void
    {
        $dataObjectDataValidator = $this->createStub(DataObjectPropertyTypeConverter::class);
        $state = $this->createStub(State::class);

        $customOrderFee = new CustomOrderFee($dataObjectDataValidator, $state);

        $customOrderFee->setType($feeType);
        $customOrderFee->setTitle('Test Fee');

        $expectedLabel = $prefix === '' ? __('Test Fee') : __("$prefix Test Fee");

        if ($feeType->equals(FeeType::Percent)) {
            $customOrderFee->setPercent(10);
            $customOrderFee->setShowPercentage(true);

            $expectedLabel = $prefix === '' ? __('Test Fee (10%)') : __("$prefix Test Fee (10%)");
        }

        $actualLabel = $customOrderFee->formatLabel($prefix);

        self::assertEquals($expectedLabel, $actualLabel);
    }

    public function testToArrayConvertsAppliedTaxesToArray(): void
    {
        $baseAppliedTax = $this->createStub(AppliedTax::class);
        $baseAppliedTaxRate = $this->createStub(AppliedTaxRate::class);
        $baseAppliedTaxData = [
            'amount' => 1.50,
            'percent' => 5.0,
            'tax_rate_key' => 'US-*-*',
            'rates' => [
                'US-*-*' => $baseAppliedTaxRate,
            ],
        ];
        $baseAppliedTaxRateData = [
            'percent' => 5.0,
            'code' => 'US-*-*',
            'title' => 'US-*-*',
        ];
        $appliedTax = $this->createStub(AppliedTax::class);
        $appliedTaxRate = $this->createStub(AppliedTaxRate::class);
        $appliedTaxData = [
            'amount' => 1.50,
            'percent' => 5.0,
            'tax_rate_key' => 'US-*-*',
            'rates' => [
                'US-*-*' => $appliedTaxRate,
            ],
        ];
        $appliedTaxRateData = [
            'percent' => 5.0,
            'code' => 'US-*-*',
            'title' => 'US-*-*',
        ];
        $dataObjectPropertyTypeConverterStub = $this->createStub(DataObjectPropertyTypeConverter::class);
        $stateStub = $this->createStub(State::class);
        $jsonSerializer = new JsonSerializer();
        $appliedTaxFactoryStub = $this->createStub(AppliedTaxInterfaceFactory::class);
        $appliedTaxRateFactoryStub = $this->createStub(AppliedTaxRateInterfaceFactory::class);
        $customOrderFee = new CustomOrderFee(
            $dataObjectPropertyTypeConverterStub,
            $stateStub,
            $jsonSerializer,
            $appliedTaxFactoryStub,
            $appliedTaxRateFactoryStub,
        );

        $baseAppliedTax->method('getData')->willReturn($baseAppliedTaxData);

        $baseAppliedTaxRate->method('getData')->willReturn($baseAppliedTaxRateData);

        $appliedTax->method('getData')->willReturn($appliedTaxData);

        $appliedTaxRate->method('getData')->willReturn($appliedTaxRateData);

        $customOrderFee->setBaseAppliedTaxes(
            [
                'US-*-*' => $baseAppliedTax,
            ],
        );
        $customOrderFee->setAppliedTaxes(
            [
                'US-*-*' => $appliedTax,
            ],
        );

        $expectedCustomFeeData = [
            'base_applied_taxes' => [
                'US-*-*' => [
                    'amount' => 1.50,
                    'percent' => 5.0,
                    'tax_rate_key' => 'US-*-*',
                    'rates' => [
                        'US-*-*' => [
                            'percent' => 5.0,
                            'code' => 'US-*-*',
                            'title' => 'US-*-*',
                        ],
                    ],
                ],
            ],
            'applied_taxes' => [
                'US-*-*' => [
                    'amount' => 1.50,
                    'percent' => 5.0,
                    'tax_rate_key' => 'US-*-*',
                    'rates' => [
                        'US-*-*' => [
                            'percent' => 5.0,
                            'code' => 'US-*-*',
                            'title' => 'US-*-*',
                        ],
                    ],
                ],
            ],
        ];
        $actualCustomFeeData = $customOrderFee->__toArray();

        self::assertEquals($expectedCustomFeeData, $actualCustomFeeData);
    }

    /**
     * @return array<string, array<string, string|value-of<FeeType>|FeeType>>
     */
    public static function getTypeReturnsCorrectTypeByAreaDataProvider(): array
    {
        return [
            'for adminhtml' => [
                'area' => 'adminhtml',
                'expectedType' => FeeType::Fixed,
            ],
            'for frontend' => [
                'area' => 'frontend',
                'expectedType' => FeeType::Fixed,
            ],
            'for REST API' => [
                'area' => 'webapi_rest',
                'expectedType' => FeeType::Fixed->value,
            ],
            'for SOAP API' => [
                'area' => 'webapi_soap',
                'expectedType' => FeeType::Fixed->value,
            ],
            'for GraphQL API' => [
                'area' => 'graphql',
                'expectedType' => FeeType::Fixed->value,
            ],
        ];
    }

    public function setAppliedTaxesConvertsDataToModelsDataProvider(): array
    {
        return [
            'from serialized string' => [
                'appliedTaxesData' => <<<'JSON'
                    {
                        "US-*-*": {
                            "amount": 1.50,
                            "percent": 5.0,
                            "tax_rate_key": "US-*-*",
                            "rates": {
                                "US-*-*": {
                                    "percent": 5.0,
                                    "code": "US-*-*",
                                    "title": "US-*-*"
                                }
                            }
                        }
                    }
                    JSON,
            ],
            'from plain array' => [
                'appliedTaxesData' => [
                    'US-*-*' => [
                        'amount' => 1.50,
                        'percent' => 5.0,
                        'tax_rate_key' => 'US-*-*',
                        'rates' => [
                            'US-*-*' => [
                                'percent' => 5.0,
                                'code' => 'US-*-*',
                                'title' => 'US-*-*',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, array<string, string|FeeType>>
     */
    public static function getLabelReturnsLabelDataProvider(): array
    {
        return [
            'for fixed fee, without prefix' => [
                'prefix' => '',
                'feeType' => FeeType::Fixed,
            ],
            'for fixed fee, with prefix' => [
                'prefix' => 'Refund',
                'feeType' => FeeType::Fixed,
            ],
            'for percent fee, without prefix' => [
                'prefix' => '',
                'feeType' => FeeType::Percent,
            ],
            'for percent fee, with prefix' => [
                'prefix' => 'Refund',
                'feeType' => FeeType::Percent,
            ],
        ];
    }
}
