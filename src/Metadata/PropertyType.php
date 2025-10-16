<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Metadata;

use Attribute;
use InvalidArgumentException;

use function class_exists;
use function in_array;
use function interface_exists;
use function str_contains;

#[Attribute(Attribute::TARGET_METHOD)]
/**
 * Defines the preferred property type in the object's `$data` array
 */
final class PropertyType // phpcs:ignore Magento2.PHP.FinalImplementation.FoundFinal
{
    private const VALID_TYPES = [
        'array',
        'bool',
        'float',
        'int',
        'string',
    ];

    public readonly string $type;

    /**
     * @phpstan-param value-of<PropertyType::VALID_TYPES>|class-string $type
     * @throws InvalidArgumentException
     */
    public function __construct(string $type)
    {
        $this->validateType($type);

        $this->type = $type;
    }

    /**
     * @phpstan-param value-of<PropertyType::VALID_TYPES>|class-string $type
     * @throws InvalidArgumentException
     */
    private function validateType(string $type): void
    {
        if (str_contains($type, '\\')) {
            if (interface_exists($type) || class_exists($type)) {
                return;
            }

            throw new InvalidArgumentException(
                'Interface, class or enum "' . $type . '" specified in property type does not exist.',
            );
        }

        if (!in_array($type, self::VALID_TYPES, true)) {
            throw new InvalidArgumentException('Invalid property type "' . $type . '".');
        }
    }
}
