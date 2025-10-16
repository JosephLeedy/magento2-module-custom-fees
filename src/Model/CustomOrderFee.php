<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Model;

use InvalidArgumentException;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFeeInterface;
use JosephLeedy\CustomFees\Api\Data\FeeTypeInterface;
use JosephLeedy\CustomFees\Metadata\PropertyType;
use JosephLeedy\CustomFees\Service\DataObjectPropertyTypeConverter;
use JsonSerializable;
use Magento\Framework\Api\AbstractSimpleObject;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Phrase;

use function __;
use function in_array;
use function is_string;
use function trim;

/**
 * Custom order fee data model
 */
class CustomOrderFee extends AbstractSimpleObject implements CustomOrderFeeInterface, JsonSerializable
{
    /**
     * @phpstan-param CustomOrderFeeData|array{} $data
     * @throws InvalidArgumentException
     */
    public function __construct(
        DataObjectPropertyTypeConverter $dataObjectPropertyTypeValidator,
        private readonly State $state,
        array $data = [],
    ) {
        if ($data !== []) {
            $data['type'] ??= FeeType::Fixed;
            $data['percent'] ??= null;
            $data['show_percentage'] ??= true;

            if (is_string($data['type'])) {
                $data['type'] = FeeType::tryFrom($data['type'])
                    ?? throw new InvalidArgumentException((string) __('Invalid custom fee type "%1".', $data['type']));
            }
        }

        $dataObjectPropertyTypeValidator->convert($data, $this);

        parent::__construct($data);
    }

    #[PropertyType('string')]
    public function setCode(string $code): static
    {
        $this->setData(static::CODE, $code);

        return $this;
    }

    public function getCode(): string
    {
        return $this->_get(static::CODE);
    }

    #[PropertyType('string')]
    public function setTitle(string $title): static
    {
        $this->setData(static::TITLE, $title);

        return $this;
    }

    public function getTitle(): string
    {
        return $this->_get(static::TITLE);
    }

    #[PropertyType(FeeType::class)]
    public function setType(FeeTypeInterface|string $type): static
    {
        if (!($type instanceof FeeTypeInterface)) {
            $type = FeeType::tryFrom($type)
                ?? throw new InvalidArgumentException((string) __('Invalid custom fee type "%1".', $type));
        }

        $this->setData(static::TYPE, $type);

        return $this;
    }

    public function getType(): FeeTypeInterface|string
    {
        /** @var FeeType|string $feeType */
        $feeType = $this->_get(static::TYPE);

        if (!($feeType instanceof FeeTypeInterface)) {
            $feeType = FeeType::tryFrom($feeType) ?? FeeType::Fixed;
        }

        if (
            in_array(
                $this->state->getAreaCode(),
                [
                    Area::AREA_WEBAPI_REST,
                    Area::AREA_WEBAPI_SOAP,
                    Area::AREA_GRAPHQL,
                ],
                true,
            )
        ) {
            return $feeType->value;
        }

        return $feeType;
    }

    #[PropertyType('float')]
    public function setPercent(?float $percent): static
    {
        $this->setData(static::PERCENT, $percent);

        return $this;
    }

    public function getPercent(): ?float
    {
        return $this->_get(static::PERCENT);
    }

    #[PropertyType('bool')]
    public function setShowPercentage(bool|int $showPercentage): static
    {
        $this->setData(static::SHOW_PERCENTAGE, (bool) $showPercentage);

        return $this;
    }

    public function getShowPercentage(): bool
    {
        return $this->_get(static::SHOW_PERCENTAGE) ?? true;
    }

    #[PropertyType('float')]
    public function setBaseValue(float $baseValue): static
    {
        $this->setData(static::BASE_VALUE, $baseValue);

        return $this;
    }

    public function getBaseValue(): float
    {
        return $this->_get(static::BASE_VALUE);
    }

    #[PropertyType('float')]
    public function setValue(float $value): static
    {
        $this->setData(static::VALUE, $value);

        return $this;
    }

    public function getValue(): float
    {
        return $this->_get(static::VALUE);
    }

    public function formatLabel(string $prefix = ''): Phrase
    {
        $showPercentage = FeeType::Percent->equals($this->getType())
            && $this->getPercent() !== null
            && $this->getShowPercentage();
        $label = $showPercentage ? "{$this->getTitle()} ({$this->getPercent()}%)" : $this->getTitle();

        if (trim($prefix) !== '') {
            $label = $prefix . ' ' . $label;
        }

        return __($label);
    }

    /**
     * @phpstan-return CustomOrderFeeData
     */
    public function jsonSerialize(): array
    {
        /** @phpstan-var CustomOrderFeeData $data */
        $data = $this->__toArray();

        return $data;
    }
}
