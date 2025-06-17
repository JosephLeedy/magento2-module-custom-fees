<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Setup\Patch\Data;

use JosephLeedy\CustomFees\Service\ConfigUpdater;
use Magento\Framework\App\Config;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Setup\Patch\DataPatchInterface;

use function array_column;
use function array_filter;

class AddDefaultFeeTypeToConfiguredCustomFees implements DataPatchInterface
{
    public function __construct(private readonly Config $config, private readonly ConfigUpdater $configUpdater) {}

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }

    /**
     * @throws LocalizedException
     */
    public function apply(): self
    {
        $this->configUpdater->addFieldToDefaultCustomFees('type', 'fixed', 'title');

        /**
         * @var array<string, array{
         *     store_id: int,
         *     code: string,
         *     website_id: int,
         *     group_id: int,
         *     name: string,
         *     sort_order: int,
         *     is_active: int,
         * }> $stores
         */
        $stores = $this->config->get('scopes', 'stores', []);
        $storeCodes = array_column(
            array_filter($stores, static fn(array $store): bool => (bool) $store['is_active']),
            'code',
        );

        array_walk(
            $storeCodes,
            function (string $storeCode): void {
                $this->configUpdater->addFieldToCustomFeesByStore($storeCode, 'type', 'fixed', 'title');
            },
        );

        return $this;
    }
}
