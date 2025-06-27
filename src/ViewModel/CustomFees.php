<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\ViewModel;

use JosephLeedy\CustomFees\Api\ConfigInterface;
use JosephLeedy\CustomFees\Model\FeeType;
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
     * @return array{
     *     code: string,
     *     title: string,
     *     type: value-of<FeeType>,
     *     value: float,
     *     advanced: array{
     *         conditions?: array{
     *             type: class-string,
     *             aggregator: string,
     *             value: '0'|'1',
     *             conditions: array<
     *                 int,
     *                 array{
     *                     type: class-string,
     *                     attribute: string,
     *                     operator: string,
     *                     value: string
     *                 }
     *             >
     *         },
     *         show_percentage: bool,
     *     }
     * }[]
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
