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
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Tax\Api\Data\AppliedTaxInterface;
use Magento\Tax\Api\Data\AppliedTaxInterfaceFactory;
use Magento\Tax\Model\TaxDetails\AppliedTax;
use Magento\Tax\Model\TaxDetails\AppliedTaxRate;

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
        private readonly SerializerInterface $serializer,
        private readonly AppliedTaxInterfaceFactory $appliedTaxFactory,
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

        try {
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
        } catch (LocalizedException) { // phpcs:ignore Magento2.CodeAnalysis.EmptyBlock.DetectedCatch
            // no-op
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

    #[PropertyType('float')]
    public function setBaseValueWithTax(float $baseValueWithTax): static
    {
        $this->setData(static::BASE_VALUE_WITH_TAX, $baseValueWithTax);

        return $this;
    }

    public function getBaseValueWithTax(): float
    {
        return (float) $this->_get(static::BASE_VALUE_WITH_TAX);
    }

    #[PropertyType('float')]
    public function setValueWithTax(float $valueWithTax): static
    {
        $this->setData(static::VALUE_WITH_TAX, $valueWithTax);

        return $this;
    }

    public function getValueWithTax(): float
    {
        return (float) $this->_get(static::VALUE_WITH_TAX);
    }

    #[PropertyType('float')]
    public function setBaseTaxAmount(float $baseTaxAmount): static
    {
        $this->setData(static::BASE_TAX_AMOUNT, $baseTaxAmount);

        return $this;
    }

    public function getBaseTaxAmount(): float
    {
        return (float) $this->_get(static::BASE_TAX_AMOUNT);
    }

    #[PropertyType('float')]
    public function setTaxAmount(float $taxAmount): static
    {
        $this->setData(static::TAX_AMOUNT, $taxAmount);

        return $this;
    }

    public function getTaxAmount(): float
    {
        return (float) $this->_get(static::TAX_AMOUNT);
    }

    #[PropertyType('float')]
    public function setTaxRate(float $taxRate): static
    {
        $this->setData(static::TAX_RATE, $taxRate);

        return $this;
    }

    public function getTaxRate(): float
    {
        return (float) $this->_get(static::TAX_RATE);
    }

    #[PropertyType('array')]
    public function setBaseAppliedTaxes(array|string|null $baseAppliedTaxes): static
    {
        if (is_string($baseAppliedTaxes)) {
            /** @var array<string, AppliedTaxData> $baseAppliedTaxesData */
            $baseAppliedTaxesData = $this->serializer->unserialize($baseAppliedTaxes) ?: [];
            /** @var array<string, AppliedTaxInterface> $baseAppliedTaxes */
            $baseAppliedTaxes = array_map(
                fn(array $appliedTax): AppliedTaxInterface => $this->appliedTaxFactory->create(['data' => $appliedTax]),
                $baseAppliedTaxesData,
            );
        }

        $this->setData(static::BASE_APPLIED_TAXES, $baseAppliedTaxes ?? []);

        return $this;
    }

    public function getBaseAppliedTaxes(): array
    {
        /** @var array<string, AppliedTaxInterface> $baseAppliedTaxes */
        $baseAppliedTaxes = $this->_get(static::BASE_APPLIED_TAXES);

        return $baseAppliedTaxes ?? [];
    }

    #[PropertyType('array')]
    public function setAppliedTaxes(array|string|null $appliedTaxes): static
    {
        if (is_string($appliedTaxes)) {
            /** @var array<string, AppliedTaxData> $appliedTaxesData */
            $appliedTaxesData = $this->serializer->unserialize($appliedTaxes) ?: [];
            /** @var array<string, AppliedTaxInterface> $appliedTaxes */
            $appliedTaxes = array_map(
                fn(array $appliedTax): AppliedTaxInterface => $this->appliedTaxFactory->create(['data' => $appliedTax]),
                $appliedTaxesData,
            );
        }

        $this->setData(static::APPLIED_TAXES, $appliedTaxes ?? []);

        return $this;
    }

    public function getAppliedTaxes(): array
    {
        /** @var array<string, AppliedTaxInterface> $appliedTaxes */
        $appliedTaxes = $this->_get(static::APPLIED_TAXES);

        return $appliedTaxes ?? [];
    }

    #[PropertyType('float')]
    public function setBaseDiscountAmount(?float $baseDiscountAmount): static
    {
        $this->setData(static::BASE_DISCOUNT_AMOUNT, $baseDiscountAmount);

        return $this;
    }

    public function getBaseDiscountAmount(): float
    {
        return (float) $this->_get(static::BASE_DISCOUNT_AMOUNT);
    }

    #[PropertyType('float')]
    public function setDiscountAmount(?float $discountAmount): static
    {
        $this->setData(static::DISCOUNT_AMOUNT, $discountAmount);

        return $this;
    }

    public function getDiscountAmount(): float
    {
        return (float) $this->_get(static::DISCOUNT_AMOUNT);
    }

    #[PropertyType('float')]
    public function setDiscountRate(?float $discountRate): static
    {
        $this->setData(static::DISCOUNT_RATE, $discountRate);

        return $this;
    }

    public function getDiscountRate(): float
    {
        return (float) $this->_get(static::DISCOUNT_RATE);
    }

    #[PropertyType('float')]
    public function setBaseDiscountTaxCompensation(?float $baseDiscountTaxCompensation): static
    {
        $this->setData(static::BASE_DISCOUNT_TAX_COMPENSATION, $baseDiscountTaxCompensation);

        return $this;
    }

    public function getBaseDiscountTaxCompensation(): float
    {
        return (float) $this->_get(static::BASE_DISCOUNT_TAX_COMPENSATION);
    }

    #[PropertyType('float')]
    public function setDiscountTaxCompensation(?float $discountTaxCompensation): static
    {
        $this->setData(static::DISCOUNT_TAX_COMPENSATION, $discountTaxCompensation);

        return $this;
    }

    public function getDiscountTaxCompensation(): float
    {
        return (float) $this->_get(static::DISCOUNT_TAX_COMPENSATION);
    }

    public function formatLabel(string $prefix = '', string $suffix = ''): Phrase
    {
        $showPercentage = FeeType::Percent->equals($this->getType())
            && $this->getPercent() !== null
            && $this->getShowPercentage();
        $label = $showPercentage ? "{$this->getTitle()} ({$this->getPercent()}%)" : $this->getTitle();

        if (trim($prefix) !== '') {
            $label = $prefix . ' ' . $label;
        }

        if (trim($suffix) !== '') {
            $label = $label . ' ' . $suffix;
        }

        return __($label);
    }

    /**
     * @phpstan-return CustomOrderFeeData
     */
    // phpcs:ignore PHPCompatibility.FunctionNameRestrictions.ReservedFunctionNames.MethodDoubleUnderscore
    public function __toArray(): array
    {
        /** @var CustomOrderFeeData $customOrderFeeData */
        $customOrderFeeData = parent::__toArray();

        foreach ($customOrderFeeData['base_applied_taxes'] ?? [] as $rateCode => $baseAppliedTax) {
            if (!($baseAppliedTax instanceof AppliedTax)) {
                continue;
            }

            /** @var AppliedTaxData $baseAppliedTax */
            $baseAppliedTax = $baseAppliedTax->getData() ?? [];

            foreach ($baseAppliedTax['rates'] as &$baseAppliedTaxRate) {
                if (!($baseAppliedTaxRate instanceof AppliedTaxRate)) {
                    continue;
                }

                /** @var AppliedTaxRateData $baseAppliedTaxRate */
                $baseAppliedTaxRate = $baseAppliedTaxRate->getData() ?? [];
            }

            $customOrderFeeData['base_applied_taxes'][$rateCode] = $baseAppliedTax;
        }

        foreach ($customOrderFeeData['applied_taxes'] ?? [] as $rateCode => $appliedTax) {
            if (!($appliedTax instanceof AppliedTax)) {
                continue;
            }

            /** @var AppliedTaxData $appliedTax */
            $appliedTax = $appliedTax->getData() ?? [];

            foreach ($appliedTax['rates'] as &$appliedTaxRate) {
                if (!($appliedTaxRate instanceof AppliedTaxRate)) {
                    continue;
                }

                /** @var AppliedTaxRateData $appliedTaxRate */
                $appliedTaxRate = $appliedTaxRate->getData() ?? [];
            }

            $customOrderFeeData['applied_taxes'][$rateCode] = $appliedTax;
        }

        return $customOrderFeeData;
    }

    /**
     * @phpstan-return CustomOrderFeeData
     */
    public function jsonSerialize(): array
    {
        return $this->__toArray();
    }
}
