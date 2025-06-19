<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Model\Total\Quote;

use JosephLeedy\CustomFees\Api\ConfigInterface;
use JosephLeedy\CustomFees\Model\FeeType;
use JosephLeedy\CustomFees\Service\ConditionsApplier;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Address\Total\AbstractTotal;
use Magento\Quote\Model\Quote\Address\Total\CollectorInterface;
use Psr\Log\LoggerInterface;

use function array_key_exists;
use function array_walk;
use function count;
use function round;

class CustomFees extends AbstractTotal
{
    public const CODE = 'custom_fees';

    public function __construct(
        private readonly ConfigInterface $config,
        private readonly LoggerInterface $logger,
        private readonly ConditionsApplier $conditionsApplier,
        private readonly PriceCurrencyInterface $priceCurrency,
    ) {
        $this->setCode(self::CODE);
    }

    public function collect(
        Quote $quote,
        ShippingAssignmentInterface $shippingAssignment,
        Total $total,
    ): CollectorInterface {
        parent::collect($quote, $shippingAssignment, $total);

        if (count($shippingAssignment->getItems()) === 0) {
            return $this;
        }

        [$baseCustomFees, $localCustomFees] = $this->getCustomFees($quote, $total);
        $customFees = $baseCustomFees;

        array_walk(
            $baseCustomFees,
            /**
             * @param array{
             *     code: string,
             *     title: string,
             *     type: 'fixed'|'percent',
             *     percent: float|null,
             *     value: float
             * } $baseCustomFee
             */
            static function (array $baseCustomFee, string|int $key) use ($total, &$customFees): void {
                $total->setBaseTotalAmount($baseCustomFee['code'], $baseCustomFee['value']);

                $customFees[$key]['base_value'] = $baseCustomFee['value'];
            },
        );
        array_walk(
            $localCustomFees,
            /**
             * @param array{
             *     code: string,
             *     title: string,
             *     type: 'fixed'|'percent',
             *     percent: float|null,
             *     value: float
             * } $localCustomFee
             */
            static function (array $localCustomFee, string|int $key) use ($total, &$customFees): void {
                $total->setTotalAmount($localCustomFee['code'], $localCustomFee['value']);

                $customFees[$key]['value'] = $localCustomFee['value'];
            },
        );

        $cartExtension = $quote->getExtensionAttributes();

        if ($cartExtension === null) {
            return $this;
        }

        $cartExtension->setCustomFees($customFees);

        return $this;
    }

    /**
     * @return array{code: string, title: Phrase, type: 'fixed'|'percent', percent: float|null, value: float}[]
     */
    public function fetch(Quote $quote, Total $total): array
    {
        [, $localCustomFees] = $this->getCustomFees($quote, $total);

        return $localCustomFees;
    }

    /**
     * @return array{code: string, title: Phrase, type: 'fixed'|'percent', percent: float|null, value: float}[][]
     */
    private function getCustomFees(Quote $quote, Total $total): array
    {
        $store = $quote->getStore();
        $baseCustomFees = [];
        $localCustomFees = [];

        try {
            $customFees = $this->config->getCustomFees($store->getId());
        } catch (LocalizedException $localizedException) {
            $this->logger->critical($localizedException->getLogMessage(), ['exception' => $localizedException]);

            return [$baseCustomFees, $localCustomFees];
        }

        foreach ($customFees as $id => $customFee) {
            if ($customFee['code'] === 'example_fee') {
                continue;
            }

            $customFee['percent'] = null;

            if (FeeType::Percent->equals($customFee['type'])) {
                $customFee['percent'] = $customFee['value'];
                $customFee['value'] = round(((float) $customFee['value'] * (float) $total->getBaseSubtotal()) / 100);
            }

            if (
                array_key_exists('conditions', $customFee['advanced'])
                && count($customFee['advanced']['conditions']) > 0
            ) {
                $isApplicable = $this->conditionsApplier->isApplicable(
                    $quote,
                    $customFee['code'],
                    $customFee['advanced']['conditions'],
                );

                if (!$isApplicable) {
                    continue;
                }
            }

            unset($customFee['advanced']);

            $customFee['title'] = __($customFee['title']);
            $baseCustomFees[$id] = $customFee;
            $localCustomFees[$id] = [
                'value' => $this->priceCurrency->convert($customFee['value'], $store),
            ] + $customFee;
        }

        return [$baseCustomFees, $localCustomFees];
    }
}
