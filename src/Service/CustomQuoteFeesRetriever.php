<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Service;

use JosephLeedy\CustomFees\Api\ConfigInterface;
use JosephLeedy\CustomFees\Model\FeeStatus;
use JosephLeedy\CustomFees\Model\FeeType;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Psr\Log\LoggerInterface;

use function array_key_exists;

/**
 * @api
 */
class CustomQuoteFeesRetriever
{
    /**
     * @var array<string, array{
     *     code: string,
     *     title: string,
     *     type: value-of<FeeType>,
     *     value: float,
     *     advanced: array{
     *         show_percentage: bool,
     *     },
     * }>[]
     */
    private array $customFeesByQuote = [];

    public function __construct(
        private readonly ConfigInterface $config,
        private readonly LoggerInterface $logger,
        private readonly ConditionsApplier $conditionsApplier,
    ) {}

    /**
     * @return array<string, array{
     *     code: string,
     *     title: string,
     *     type: value-of<FeeType>,
     *     value: float,
     *     advanced: array{
     *         show_percentage: bool,
     *     },
     * }>
     */
    public function retrieveApplicableFees(Quote $quote): array
    {
        $quoteId = (int) $quote->getEntityId();

        if (array_key_exists($quoteId, $this->customFeesByQuote)) {
            return $this->customFeesByQuote[$quoteId];
        }

        $store = $quote->getStore();
        $customFees = [];

        try {
            $configuredCustomFees = $this->config->getCustomFees($store->getId());
        } catch (LocalizedException $localizedException) {
            $this->logger->critical($localizedException->getLogMessage(), ['exception' => $localizedException]);

            return $customFees;
        }

        foreach ($configuredCustomFees as $customFee) {
            /** @var string $customFeeCode */
            $customFeeCode = $customFee['code'];
            $customFeeStatus = FeeStatus::tryFrom((int) $customFee['status']) ?? FeeStatus::Disabled;

            if ($customFeeCode === 'example_fee' || $customFeeStatus === FeeStatus::Disabled) {
                continue;
            }

            $customFeeConditions = $customFee['advanced']['conditions'] ?? [];

            if ($customFeeConditions !== []) {
                $isApplicable = $this->conditionsApplier->isApplicable($quote, $customFeeCode, $customFeeConditions);

                if (!$isApplicable) {
                    continue;
                }
            }

            unset($customFee['status'], $customFee['advanced']['conditions']);

            $customFees[$customFeeCode] = $customFee;
        }

        $this->customFeesByQuote[$quoteId] = $customFees;

        return $this->customFeesByQuote[$quoteId];
    }

    public function reset(): void
    {
        $this->customFeesByQuote = [];
    }
}
