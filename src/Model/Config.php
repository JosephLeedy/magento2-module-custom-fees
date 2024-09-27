<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Model;

use JosephLeedy\CustomFees\Api\ConfigInterface;
use InvalidArgumentException;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class Config implements ConfigInterface
{
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly SerializerInterface $serializer,
    ) {}

    public function getCustomFees(int|string|null $storeId = null): array
    {
        if ($storeId === null) {
            try {
                $storeId = $this->storeManager->getStore()->getId();
            } catch (NoSuchEntityException) {
                $storeId = null;
            }
        }

        /** @var string $customFeesJson */
        $customFeesJson = $this->scopeConfig->getValue(
            self::CONFIG_PATH_CUSTOM_FEES,
            ScopeInterface::SCOPE_STORES,
            $storeId
        ) ?? '[]';

        try {
            /** @var array{code: string, title: string, value: float}[] $customFees */
            $customFees = $this->serializer->unserialize($customFeesJson);
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw new LocalizedException(
                __('Could not get custom fees from configuration. Error: "%1"', $invalidArgumentException->getMessage())
            );
        }

        return $customFees;
    }
}
