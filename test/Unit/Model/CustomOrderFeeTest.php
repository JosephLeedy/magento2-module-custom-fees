<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Unit\Model;

use InvalidArgumentException;
use JosephLeedy\CustomFees\Api\Data\FeeTypeInterface;
use JosephLeedy\CustomFees\Model\CustomOrderFee;
use JosephLeedy\CustomFees\Model\FeeType;
use Magento\Framework\App\State;
use PHPUnit\Framework\TestCase;

use function __;

final class CustomOrderFeeTest extends TestCase
{
    public function testConstructorSetsDefaultValuesForMissingProperties(): void
    {
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

        $customOrderFee = new CustomOrderFee($state, $data);
        $actualData = $customOrderFee->__toArray();

        self::assertEquals($expectedData, $actualData);
    }

    public function testConstructorSetsTypePropertyFromString(): void
    {
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

        $customOrderFee = new CustomOrderFee($state, $data);
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

        new CustomOrderFee($state, $data);
    }

    /**
     * @dataProvider getTypeReturnsCorrectTypeByAreaDataProvider
     */
    public function testGetTypeReturnsCorrectTypeByArea(string $area, FeeTypeInterface|string $expectedType): void
    {
        $state = $this->createStub(State::class);

        $state->method('getAreaCode')->willReturn($area);

        $customOrderFee = new CustomOrderFee($state);

        $customOrderFee->setType(FeeType::Fixed);

        self::assertEquals($expectedType, $customOrderFee->getType());
    }

    /**
     * @dataProvider getLabelReturnsLabelDataProvider
     */
    public function testGetLabelReturnsLabel(string $prefix, FeeType $feeType): void
    {
        $state = $this->createStub(State::class);

        $customOrderFee = new CustomOrderFee($state);

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
