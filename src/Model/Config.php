<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Model;

use InvalidArgumentException;
use JosephLeedy\CustomFees\Api\ConfigInterface;
use JsonException;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

use function array_key_exists;
use function json_decode;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

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
            $storeId,
        ) ?? '[]';

        try {
            /**
             * @var array{
             *     code: string,
             *     title: string,
             *     type: value-of<FeeType>,
             *     value: float,
             *     status: value-of<FeeStatus>,
             *     advanced?: string
             * }[] $customFees
             */
            $customFees = $this->serializer->unserialize($customFeesJson);
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw new LocalizedException(
                __(
                    'Could not get custom fees from configuration. Error: "%1"',
                    $invalidArgumentException->getMessage(),
                ),
            );
        }

        array_walk(
            $customFees,
            static function (array &$customFee): void {
                if (!array_key_exists('advanced', $customFee)) {
                    $customFee['advanced'] = '[]';
                }

                try {
                    $customFee['advanced'] = (array) json_decode(
                        $customFee['advanced'],
                        true,
                        512,
                        JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
                    );
                } catch (JsonException $jsonException) {
                    throw new LocalizedException(
                        __(
                            'Could not process advanced configuration for custom fee "%1". Error: "%2"',
                            $customFee['code'],
                            $jsonException->getMessage(),
                        ),
                        $jsonException,
                    );
                }

                if (array_key_exists('show_percentage', $customFee['advanced'])) {
                    $customFee['advanced']['show_percentage'] = (bool) $customFee['advanced']['show_percentage'];
                } else {
                    $customFee['advanced']['show_percentage'] = FeeType::Percent->equals($customFee['type']);
                }
            },
        );

        /**
         * @var array{
         *      code: string,
         *      title: string,
         *      type: value-of<FeeType>,
         *      value: float,
         *      status: value-of<FeeStatus>,
         *      advanced: array{
         *          conditions?: array{
         *              type: class-string,
         *              aggregator: string,
         *              value: '0'|'1',
         *              conditions: array<
         *                  int,
         *                  array{
         *                      type: class-string,
         *                      attribute: string,
         *                      operator: string,
         *                      value: string
         *                  }
         *              >
         *          },
         *          show_percentage: bool,
         *      }
         *  }[] $customFees
         */
        return $customFees;
    }

    public function isCustomOrderFeesReportAggregationEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::CONFIG_PATH_REPORTS_CUSTOM_ORDER_FEES_ENABLE_AGGREGATION);
    }

    public function getCustomOrderFeesReportAggregationTime(): string
    {
        /** @var string|null $aggregationTime */
        $aggregationTime = $this->scopeConfig->getValue(self::CONFIG_PATH_REPORTS_CUSTOM_ORDER_FEES_AGGREGATION_TIME);

        return (string) ($aggregationTime ?? '00,00,00');
    }

    public function getCustomOrderFeesReportAggregationFrequency(): string
    {
        return (string) (
            $this->scopeConfig->getValue(self::CONFIG_PATH_REPORTS_CUSTOM_ORDER_FEES_AGGREGATION_FREQUENCY) ?? 'D'
        );
    }

    public function getTaxClass(int|string|null $storeId = null): int
    {
        return (int) (
            $this->scopeConfig->getValue(
                self::CONFIG_PATH_TAX_CLASS_CUSTOM_FEE_TAX_CLASS,
                ScopeInterface::SCOPE_STORES,
                $storeId,
            ) ?? 0
        );
    }

    public function isTaxIncluded(int|string|null $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::CONFIG_PATH_TAX_CALCULATION_CUSTOM_FEES_INCLUDE_TAX,
            ScopeInterface::SCOPE_STORES,
            $storeId,
        ) ?? false;
    }

    public function getDisplayType(int|string|null $storeId = null): DisplayType
    {
        $taxDisplayType = (int) (
            $this->scopeConfig->getValue(
                self::CONFIG_PATH_TAX_DISPLAY_CUSTOM_FEES,
                ScopeInterface::SCOPE_STORE,
                $storeId,
            ) ?? 1
        );

        return DisplayType::tryFrom($taxDisplayType) ?? DisplayType::ExcludingTax;
    }

    public function getCartDisplayType(int|string|null $storeId = null): DisplayType
    {
        $cartDisplayType = (int) (
            $this->scopeConfig->getValue(
                self::CONFIG_PATH_TAX_CART_DISPLAY_CUSTOM_FEES,
                ScopeInterface::SCOPE_STORE,
                $storeId,
            ) ?? 1
        );

        return DisplayType::tryFrom($cartDisplayType) ?? DisplayType::ExcludingTax;
    }

    public function getSalesDisplayType(int|string|null $storeId = null): DisplayType
    {
        $salesDisplayType = (int) (
            $this->scopeConfig->getValue(
                self::CONFIG_PATH_TAX_SALES_DISPLAY_CUSTOM_FEES,
                ScopeInterface::SCOPE_STORE,
                $storeId,
            ) ?? 1
        );

        return DisplayType::tryFrom($salesDisplayType) ?? DisplayType::ExcludingTax;
    }
}
