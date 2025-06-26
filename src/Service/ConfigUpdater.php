<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Service;

use InvalidArgumentException;
use JosephLeedy\CustomFees\Api\ConfigInterface;
use Magento\Config\App\Config\Source\RuntimeConfigSource;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\ScopeResolverPool;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\State\InitException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\ScopeInterface;

use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_search;
use function array_slice;
use function count;
use function implode;
use function is_string;

use const ARRAY_FILTER_USE_KEY;

class ConfigUpdater
{
    public function __construct(
        private readonly ScopeResolverPool $scopeResolverPool,
        private readonly RuntimeConfigSource $runtimeConfigSource,
        private readonly SerializerInterface $serializer,
        private readonly WriterInterface $configWriter,
    ) {}

    /**
     * @throws LocalizedException
     */
    public function addFieldToDefaultCustomFees(string $fieldName, mixed $defaultValue, ?string $after = null): void
    {
        $this->addFieldToCustomFeesByScope(
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            null,
            $fieldName,
            $defaultValue,
            $after,
        );
    }

    /**
     * @param array<string, mixed> $fields Array containing key-value pairs of field names and default values
     * @throws LocalizedException
     */
    public function addFieldsToDefaultCustomFees(array $fields, ?string $after = null): void
    {
        $this->addFieldToCustomFeesByScope(ScopeConfigInterface::SCOPE_TYPE_DEFAULT, null, $fields, null, $after);
    }

    /**
     * @throws LocalizedException
     */
    public function addFieldToCustomFeesByStore(
        string $storeCode,
        string $fieldName,
        mixed $defaultValue,
        ?string $after = null,
    ): void {
        $this->addFieldToCustomFeesByScope(ScopeInterface::SCOPE_STORES, $storeCode, $fieldName, $defaultValue, $after);
    }

    /**
     * @param array<string, mixed> $fields Array containing key-value pairs of field names and default values
     * @throws LocalizedException
     */
    public function addFieldsToCustomFeesByStore(string $storeCode, array $fields, ?string $after = null): void
    {
        $this->addFieldToCustomFeesByScope(ScopeInterface::SCOPE_STORES, $storeCode, $fields, null, $after);
    }

    /**
     * @param string|array<string, mixed> $fieldName Single field name, or array containing key-value pairs of field
     * names and default values
     * @throws LocalizedException
     */
    public function addFieldToCustomFeesByScope(
        string $scopeType,
        ?string $scopeCode,
        string|array $fieldName,
        mixed $defaultValue,
        ?string $after = null,
    ): void {
        try {
            $scopeId = $this->getScopeIdByScopeCode($scopeType, $scopeCode);
        } catch (InvalidArgumentException | InitException $exception) {
            throw new LocalizedException(__('Could not get identifier for scope "%1".', $scopeCode), $exception);
        }

        $values = is_string($fieldName) ? [$fieldName => $defaultValue] : $fieldName;
        $scopeParts = [$scopeType];

        if ($scopeType !== ScopeConfigInterface::SCOPE_TYPE_DEFAULT) {
            $scopeParts[] = $scopeCode;
        }

        $scopeParts[] = ConfigInterface::CONFIG_PATH_CUSTOM_FEES;
        /** @var string|null $customFeesJson */
        $customFeesJson = $this->runtimeConfigSource->get(implode('/', $scopeParts));

        if ($customFeesJson === null) {
            return;
        }

        try {
            /** @var array<string, array{code: string, title: string, value: float, advanced: string}> $customFees */
            $customFees = $this->serializer->unserialize($customFeesJson) ?? [];
        } catch (InvalidArgumentException) {
            $customFees = [];
        }

        if (count($customFees) === 0) {
            return;
        }

        $hasChanges = false;

        foreach ($customFees as &$customFee) {
            $valuesToAdd = array_filter(
                $values,
                static fn(string $key): bool => !array_key_exists($key, $customFee),
                ARRAY_FILTER_USE_KEY,
            );

            if (count($valuesToAdd) === 0) {
                continue;
            }

            if ($after !== null && array_key_exists($after, $customFee)) {
                /** @var int $position */
                $position = array_search($after, array_keys($customFee), true) + 1;
                $customFee = array_slice($customFee, 0, $position)
                    + $valuesToAdd
                    + array_slice($customFee, $position);
            } else {
                $customFee += $valuesToAdd;
            }

            $hasChanges = true;
        }

        unset($customFee);

        if (!$hasChanges) {
            return;
        }

        try {
            /** @var string $customFeesJson */
            $customFeesJson = $this->serializer->serialize($customFees);
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw new LocalizedException(__('Could not serialize custom fees.'), $invalidArgumentException);
        }

        $this->configWriter->save(
            ConfigInterface::CONFIG_PATH_CUSTOM_FEES,
            $customFeesJson,
            $scopeType,
            $scopeId,
        );
    }

    /**
     * @throws InvalidArgumentException
     * @throws InitException
     */
    private function getScopeIdByScopeCode(string $scopeType, ?string $scopeCode): int
    {
        $scopeResolver = $this->scopeResolverPool->get($scopeType);
        $scope = $scopeResolver->getScope($scopeCode);

        return (int) $scope->getId();
    }
}
