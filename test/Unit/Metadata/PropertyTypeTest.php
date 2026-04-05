<?php

// phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Unit\Metadata;

use InvalidArgumentException;
use JosephLeedy\CustomFees\Metadata\PropertyType;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

interface TestInterface
{
    public function test(): void;
}

final class TestClass implements TestInterface
{
    public function test(): void {}

    #[PropertyType('string')]
    public function withValidAttribute(): void {}

    #[PropertyType('invalid')]
    public function withInvalidAttribute(): void {}
}

enum TestEnum: string
{
    case Test = 'test';
}

final class PropertyTypeTest extends TestCase
{
    /**
     * @dataProvider validatesTypeSuccessfullyDataProvider
     */
    public function testValidatesTypeSuccessfully(string $type): void
    {
        $this->expectNotToPerformAssertions();

        new PropertyType($type);
    }

    public function testThrowsExceptionIfTypeIsInvalid(): void
    {
        $this->expectExceptionObject(new InvalidArgumentException('Invalid property type "invalid".'));

        new PropertyType('invalid');
    }

    public function testThrowsExceptionIfClassDoesNotExist(): void
    {
        $this->expectExceptionObject(
            new InvalidArgumentException(
                'Interface, class or enum "\Invalid" specified in property type does not exist.',
            ),
        );

        new PropertyType('\Invalid');
    }

    public function testDoesNotThrowsExceptionIfAppliedAttributeIsValid(): void
    {
        $reflectionClass = new ReflectionClass(TestClass::class);
        $reflectionMethod = $reflectionClass->getMethod('withValidAttribute');

        try {
            $attribute = $reflectionMethod->getAttributes(PropertyType::class)[0]->newInstance();
        } catch (InvalidArgumentException) {
            $this->fail();
        }

        self::assertSame('string', $attribute->type);
    }

    public function testThrowsExceptionIfAppliedAttributeIsInvalid(): void
    {
        $this->expectExceptionObject(new InvalidArgumentException('Invalid property type "invalid".'));

        $reflectionClass = new ReflectionClass(TestClass::class);
        $reflectionMethod = $reflectionClass->getMethod('withInvalidAttribute');

        $reflectionMethod->getAttributes(PropertyType::class)[0]->newInstance();
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function validatesTypeSuccessfullyDataProvider(): array
    {
        return [
            'with string type' => [
                'type' => 'string',
            ],
            'with int type' => [
                'type' => 'int',
            ],
            'with float type' => [
                'type' => 'float',
            ],
            'with bool type' => [
                'type' => 'bool',
            ],
            'with array type' => [
                'type' => 'array',
            ],
            'with interface type' => [
                'type' => TestInterface::class,
            ],
            'with class type' => [
                'type' => TestClass::class,
            ],
            'with enum type' => [
                'type' => TestEnum::class,
            ],
        ];
    }
}
