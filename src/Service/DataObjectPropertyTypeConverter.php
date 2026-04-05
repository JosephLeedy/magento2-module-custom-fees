<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Service;

use BackedEnum;
use Error;
use Exception;
use InvalidArgumentException;
use JosephLeedy\CustomFees\Metadata\PropertyType;
use Magento\Framework\Api\AbstractSimpleObject;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;
use TypeError;
use UnitEnum;

use function __;
use function array_key_exists;
use function array_map;
use function call_user_func;
use function constant;
use function gettype;
use function in_array;
use function is_subclass_of;
use function lcfirst;
use function preg_replace;
use function settype;
use function str_contains;
use function str_starts_with;
use function strtolower;
use function substr;
use function trim;

/**
 * @internal
 */
class DataObjectPropertyTypeConverter
{
    /**
     * Ensures that data properties have the correct type and required values are set in the given data object
     *
     * @param mixed[] $data
     * @param AbstractSimpleObject|DataObject|class-string $dataObject
     * @return true
     * @throws ReflectionException
     * @throws InvalidArgumentException
     * @throws LocalizedException
     * @throws Exception
     */
    public function convert(array &$data, object|string $dataObject): bool
    {
        if ($data === []) {
            return true;
        }

        $reflectionClass = new ReflectionClass($dataObject);
        $methods = $reflectionClass->getMethods();

        array_walk(
            $methods,
            function (ReflectionMethod $method) use (&$data): void {
                $methodName = $method->getName();

                if (!str_starts_with($methodName, 'set') || $methodName === 'setData') {
                    return;
                }

                $parameters = $method->getParameters();

                if ($parameters === []) {
                    return;
                }

                $parameterType = $parameters[0]->getType();
                $typeNames = match (true) {
                    $parameterType instanceof ReflectionNamedType => [$parameterType->getName() ?? 'null'],
                    $parameterType instanceof ReflectionUnionType => array_map(
                        // @phpstan-ignore argument.type
                        static fn(ReflectionNamedType $type): string => $type->getName() ?? 'null',
                        $parameterType->getTypes(),
                    ),
                    default => [],
                };

                if ($typeNames === []) {
                    return;
                }

                $property = strtolower(
                    trim(preg_replace('/([A-Z]|[0-9]+)/', "_$1", lcfirst(substr($methodName, 3))) ?? '', '_'),
                );

                if (
                    (($parameterType !== null && $parameterType->allowsNull()) || in_array('null', $typeNames, true))
                    && !array_key_exists($property, $data)
                ) {
                    return;
                }

                if (!array_key_exists($property, $data)) {
                    throw new InvalidArgumentException(
                        (string) __('Please provide a value for property "%1".', $property),
                    );
                }

                // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged
                $currentValueType = strtolower(gettype($data[$property]));
                $normalizedTypes = [
                    'integer' => 'int',
                    'double' => 'float',
                    'boolean' => 'bool',
                ];

                if (array_key_exists($currentValueType, $normalizedTypes)) {
                    $currentValueType = $normalizedTypes[$currentValueType];
                }

                if (($parameterType !== null && $parameterType->allowsNull()) && $currentValueType === 'null') {
                    return;
                }

                if (in_array($currentValueType, $typeNames, true)) {
                    return;
                }

                /** @var ReflectionAttribute<PropertyType>[] $propertyTypeAttributes */
                $propertyTypeAttributes = $method->getAttributes(PropertyType::class);

                if ($propertyTypeAttributes === []) {
                    return;
                }

                $propertyTypeAttribute = $propertyTypeAttributes[0]->newInstance();

                if (str_contains($propertyTypeAttribute->type, '\\')) {
                    $this->convertBackedEnumProperty($data, $propertyTypeAttribute->type, $property);
                    $this->convertUnitEnumProperty($data, $propertyTypeAttribute->type, $property);

                    if (!($data[$property] instanceof $propertyTypeAttribute->type)) {
                        $arguments = !empty($data[$property]) ? ['data' => (array) $data[$property]] : [];
                        $data[$property] = ObjectManager::getInstance()
                            ->create($propertyTypeAttribute->type, $arguments);
                    }

                    return;
                }

                settype($data[$property], $propertyTypeAttribute->type)
                    ?: throw new LocalizedException(
                        __(
                            'Could not set type "%1" for property "%2".',
                            $propertyTypeAttribute->type,
                            $property,
                        ),
                    );
            },
        );

        return true;
    }

    /**
     * @param mixed[] $data
     * @throws InvalidArgumentException
     */
    private function convertBackedEnumProperty(array &$data, string $propertyType, string $property): void
    {
        /** @var int|string|object $value */
        $value = $data[$property];

        if (!is_subclass_of($propertyType, BackedEnum::class) || $value instanceof $propertyType) {
            return;
        }

        /** @var int|string $value */

        try {
            // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged
            $enum = call_user_func([$propertyType, 'tryFrom'], $value);
        } catch (TypeError) {
            $enum = null;
        }

        if ($enum === null) {
            throw new InvalidArgumentException(
                (string) __('Invalid value "%1" for property "%2".', $value, $property),
            );
        }

        $data['property'] = $enum;
    }

    /**
     * @param mixed[] $data
     * @throws InvalidArgumentException
     */
    private function convertUnitEnumProperty(array &$data, string $propertyType, string $property): void
    {
        /** @var int|string|object $value */
        $value = $data[$property];

        if (!is_subclass_of($propertyType, UnitEnum::class) || $value instanceof $propertyType) {
            return;
        }

        /** @var int|string $value */

        try {
            $data[$property] = constant($propertyType . '::' . $value);
        } catch (Error) {
            throw new InvalidArgumentException(
                (string) __('Invalid value "%1" for property "%2".', $value, $property),
            );
        }
    }
}
