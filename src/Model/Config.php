<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Model;

use InvalidArgumentException;
use JosephLeedy\CustomFees\Api\ConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

use function array_key_exists;
use function str_replace;

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
            /**
             * @var array{code: string, title: string, value: float, advanced?: string}[] $customFees
             */
            $customFees = $this->serializer->unserialize($customFeesJson);
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw new LocalizedException(
                __('Could not get custom fees from configuration. Error: "%1"', $invalidArgumentException->getMessage())
            );
        }

        array_walk(
            $customFees,
            function (array &$customFee): void {
                if (!array_key_exists('advanced', $customFee)) {
                    $customFee['advanced'] = '[]';
                }

                try {
                    $customFee['advanced'] = $this->serializer->unserialize(
                        str_replace('\\', '\\\\', $customFee['advanced']),
                    );
                } catch (InvalidArgumentException $invalidArgumentException) {
                    throw new LocalizedException(
                        __(
                            'Could not process advanced configuration for custom fee "%1". Error: "%2"',
                            $customFee['code'],
                            $invalidArgumentException->getMessage(),
                        ),
                        $invalidArgumentException,
                    );
                }
            },
        );

        /**
         * @var array{
         *      code: string,
         *      title: string,
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
