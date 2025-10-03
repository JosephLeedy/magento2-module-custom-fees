<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Model;

use InvalidArgumentException;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFeeInterface;
use JosephLeedy\CustomFees\Api\Data\FeeTypeInterface;
use Magento\Framework\Api\AbstractSimpleObject;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;

use function __;
use function in_array;

class CustomOrderFee extends AbstractSimpleObject implements CustomOrderFeeInterface
{
    /**
     * @phpstan-param array{}|array{
     *     code: string,
     *     title: string,
     *     type: value-of<FeeType>,
     *     percent: float|null,
     *     show_percentage: bool,
     *     base_value: float,
     *     value: float,
     * } $data
     */
    public function __construct(private readonly State $state, array $data = [])
    {
        parent::__construct($data);
    }

    public function setCode(string $code): static
    {
        $this->setData(static::CODE, $code);

        return $this;
    }

    public function getCode(): string
    {
        return (string) $this->_get(static::CODE);
    }

    public function setTitle(string $title): static
    {
        $this->setData(static::TITLE, $title);

        return $this;
    }

    public function getTitle(): string
    {
        return (string) $this->_get(static::TITLE);
    }

    public function setType(FeeTypeInterface|string $type): static
    {
        if (!($type instanceof FeeTypeInterface)) {
            $type = FeeType::tryFrom($type)
                ?? throw new InvalidArgumentException((string) __('Invalid custom fee type'));
        }

        $this->setData(static::TYPE, $type);

        return $this;
    }

    public function getType(): FeeTypeInterface|string
    {
        /** @var FeeType $feeType */
        $feeType = $this->_get(static::TYPE);

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

    public function setPercent(?float $percent): static
    {
        $this->setData(static::PERCENT, $percent);

        return $this;
    }

    public function getPercent(): ?float
    {
        /** @var float|null $percent */
        $percent = $this->_get(static::PERCENT);

        return $percent;
    }

    public function setShowPercentage(bool|int $showPercentage): static
    {
        $this->setData(static::SHOW_PERCENTAGE, (bool) $showPercentage);

        return $this;
    }

    public function getShowPercentage(): bool
    {
        return (bool) $this->_get(static::SHOW_PERCENTAGE);
    }

    public function setBaseValue(float $baseValue): static
    {
        $this->setData(static::BASE_VALUE, $baseValue);

        return $this;
    }

    public function getBaseValue(): float
    {
        return (float) $this->_get(static::BASE_VALUE);
    }

    public function setValue(float $value): static
    {
        $this->setData(static::VALUE, $value);

        return $this;
    }

    public function getValue(): float
    {
        return (float) $this->_get(static::VALUE);
    }
}
