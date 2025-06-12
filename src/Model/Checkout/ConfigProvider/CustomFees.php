<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Model\Checkout\ConfigProvider;

use JosephLeedy\CustomFees\Api\ConfigInterface;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Exception\LocalizedException;

use function array_column;

class CustomFees implements ConfigProviderInterface
{
    public function __construct(private readonly ConfigInterface $config)
    {}

    /**
     * @return array{customFees: array{codes: string[]}}
     */
    public function getConfig(): array
    {
        try {
            $customFees = $this->config->getCustomFees();
        } catch (LocalizedException) {
            $customFees = [];
        }

        $codes = array_column($customFees, 'code');

        return [
            'customFees' => [
                'codes' => $codes,
            ],
        ];
    }
}
