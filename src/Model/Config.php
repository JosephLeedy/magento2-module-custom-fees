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
             *     type: 'fixed'|'percent',
             *     value: float,
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
                    $customFee['advanced'] = json_decode(
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
            },
        );

        /**
         * @var array{
         *      code: string,
         *      title: string,
         *      type: 'fixed'|'percent',
         *      value: float,
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
         *          }
         *      }
         *  }[] $customFees
         */
        return $customFees;
    }
}
