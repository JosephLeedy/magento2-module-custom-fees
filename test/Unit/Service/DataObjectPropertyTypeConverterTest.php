<?php

// phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Unit\Service;

use InvalidArgumentException;
use JosephLeedy\CustomFees\Metadata\PropertyType;
use JosephLeedy\CustomFees\Model\CustomOrderFee;
use JosephLeedy\CustomFees\Model\FeeType;
use JosephLeedy\CustomFees\Service\DataObjectPropertyTypeConverter;
use Magento\Framework\Api\AbstractSimpleObject;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\DataObject;
use Magento\Framework\ObjectManagerInterface;
use PHPUnit\Framework\TestCase;

use function __;

enum TestBackedEnum: int
{
    case Test = 1;
}

enum TestEnum
{
    case Test;
}

final class TestClass extends AbstractSimpleObject
{
    #[PropertyType(TestBackedEnum::class)]
    public function setBackedEnum(?TestBackedEnum $testBackedEnum): self
    {
        return $this;
    }

    #[PropertyType(TestEnum::class)]
    public function setEnum(?TestEnum $enum): self
    {
        return $this;
    }

    #[PropertyType(DataObject::class)]
    public function setClass(?DataObject $class): self
    {
        return $this;
    }
}

final class DataObjectPropertyTypeConverterTest extends TestCase
{
    /**
     * @dataProvider convertsPropertyTypesSuccessfullyDataProvider
     */
    public function testConvertsPropertyTypesSuccessfully(bool $empty): void
    {
        $this->expectNotToPerformAssertions();

        $data = [];
        $converter = new DataObjectPropertyTypeConverter();

        if (!$empty) {
            $data = [
                'code' => 'test_fee_0',
                'title' => 'Test Fee',
                'type' => FeeType::Fixed,
                'percent' => null,
                'show_percentage' => false,
                'base_value' => 5.00,
                'value' => 5.00,
            ];
        }

        $converter->convert($data, CustomOrderFee::class);
    }

    public function testThrowsExceptionIfPropertyIsMissing(): void
    {
        $this->expectExceptionObject(
            new InvalidArgumentException((string) __('Please provide a value for property "%1".', 'value')),
        );

        $data = [
            'code' => 'test_fee_0',
            'title' => 'Test Fee',
            'type' => FeeType::Fixed,
            'percent' => null,
            'show_percentage' => true,
            'base_value' => 5.00,
        ];
        $converter = new DataObjectPropertyTypeConverter();

        $converter->convert($data, CustomOrderFee::class);
    }

    public function testSetsCorrectPropertyTypes(): void
    {
        $data = [
            'code' => 'test_fee_0',
            'title' => 'Test Fee',
            'type' => FeeType::Percent,
            'percent' => '1',
            'show_percentage' => '1',
            'base_value' => '5.00',
            'value' => '5.00',
        ];
        $converter = new DataObjectPropertyTypeConverter();

        $expectedData = $data;
        $expectedData['percent'] = 1.0;
        $expectedData['show_percentage'] = true;
        $expectedData['base_value'] = 5.00;
        $expectedData['value'] = 5.00;

        $converter->convert($data, CustomOrderFee::class);

        self::assertEquals($expectedData, $data);
    }

    public function testInstantiatesPropertiesFromEnumsSuccessfully(): void
    {
        $data = [
            'backed_enum' => 1,
            'enum' => 'Test',
        ];
        $converter = new DataObjectPropertyTypeConverter();

        $converter->convert($data, TestClass::class);

        $expectedData = [
            'backed_enum' => TestBackedEnum::Test,
            'enum' => TestEnum::Test,
        ];

        self::assertEquals($expectedData, $data);
    }

    public function testThrowsExceptionIfBackedEnumPropertyIsInvalid(): void
    {
        $this->expectExceptionObject(
            new InvalidArgumentException(
                (string) __('Invalid value "%1" for property "%2".', 'invalid', 'backed_enum'),
            ),
        );

        $data = [
            'backed_enum' => 'invalid',
        ];
        $converter = new DataObjectPropertyTypeConverter();

        $converter->convert($data, TestClass::class);
    }

    public function testThrowsExceptionIfUnitEnumPropertyIsInvalid(): void
    {
        $this->expectExceptionObject(
            new InvalidArgumentException(
                (string) __('Invalid value "%1" for property "%2".', 'invalid', 'enum'),
            ),
        );

        $data = [
            'enum' => 'invalid',
        ];
        $converter = new DataObjectPropertyTypeConverter();

        $converter->convert($data, TestClass::class);
    }

    public function testInstantiatesPropertyFromClass(): void
    {
        $data = [
            'class' => '',
        ];
        $converter = new DataObjectPropertyTypeConverter();

        $this->instantiateObjectManager(
            [
                [
                    DataObject::class,
                    [],
                    new DataObject(),
                ],
            ],
        );

        $converter->convert($data, TestClass::class);

        $expectedData = [
            'class' => new DataObject(),
        ];

        self::assertEquals($expectedData, $data);
    }

    public static function convertsPropertyTypesSuccessfullyDataProvider(): array
    {
        return [
            'with valid data' => [
                'empty' => false,
            ],
            'with no data' => [
                'empty' => true,
            ],
        ];
    }

    /**
     * @param array<int, array{0: class-string, 1: mixed[], 2: object}> $objects
     */
    private function instantiateObjectManager(array $objects = []): void
    {
        $objectManagerStub = $this->getMockBuilder(ObjectManagerInterface::class)
            ->addMethods(['getInstance'])
            ->onlyMethods(['create'])
            ->getMockForAbstractClass();

        $objectManagerStub
            ->method('getInstance')
            ->willReturnSelf();
        $objectManagerStub
            ->method('create')
            ->willReturnMap($objects);

        ObjectManager::setInstance($objectManagerStub);
    }
}
