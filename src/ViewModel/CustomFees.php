<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\ViewModel;

use JosephLeedy\CustomFees\Api\ConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

use function array_column;

class CustomFees implements ArgumentInterface
{
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly SerializerInterface $serializer,
    ) {}

    /**
     * @return array{code: string, title: string, type: 'fixed'|'percent', value: float}[]
     */
    public function getCustomFees(): array
    {
        try {
            return $this->config->getCustomFees();
        } catch (LocalizedException) {
            return [];
        }
    }

    public function getCustomFeesAsJson(): string
    {
        return (string) (
            $this->serializer->serialize($this->getCustomFees()) ?: '[]'
        );
    }

    /**
     * @return string[]
     */
    public function getCustomFeeCodes(): array
    {
        return array_column($this->getCustomFees(), 'code');
    }

    public function getCustomFeeCodesAsJson(): string
    {
        return (string) (
            $this->serializer->serialize($this->getCustomFeeCodes()) ?: '[]'
        );
    }
}
