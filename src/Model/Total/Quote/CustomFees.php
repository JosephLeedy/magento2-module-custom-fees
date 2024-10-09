<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Model\Total\Quote;

use JosephLeedy\CustomFees\Api\ConfigInterface;
use Magento\Framework\Phrase;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Address\Total\AbstractTotal;
use Magento\Quote\Model\Quote\Address\Total\CollectorInterface;
use Magento\Store\Api\Data\StoreInterface;

use function array_map;
use function array_walk;
use function count;

class CustomFees extends AbstractTotal
{
    public const CODE = 'custom_fees';

    public function __construct(
        private readonly ConfigInterface $config,
        private readonly PriceCurrencyInterface $priceCurrency,
    ) {
        $this->setCode(self::CODE);
    }

    public function collect(
        Quote $quote,
        ShippingAssignmentInterface $shippingAssignment,
        Total $total
    ): CollectorInterface {
        parent::collect($quote, $shippingAssignment, $total);

        if (count($shippingAssignment->getItems()) === 0) {
            return $this;
        }

        [$baseCustomFees, $localCustomFees] = $this->getCustomFees($quote->getStore());
        $customFees = $baseCustomFees;

        array_walk(
            $baseCustomFees,
            /**
             * @param array{code: string, title: string, value: float} $baseCustomFee
             */
            static function (array $baseCustomFee, string|int $key) use ($total, &$customFees): void {
                $total->setBaseTotalAmount($baseCustomFee['code'], $baseCustomFee['value']);

                $customFees[$key]['base_value'] = $baseCustomFee['value'];
            }
        );
        array_walk(
            $localCustomFees,
            /**
             * @param array{code: string, title: string, value: float} $localCustomFee
             */
            static function (array $localCustomFee, string|int $key) use ($total, &$customFees): void {
                $total->setTotalAmount($localCustomFee['code'], $localCustomFee['value']);

                $customFees[$key]['value'] = $localCustomFee['value'];
            }
        );

        $cartExtension = $quote->getExtensionAttributes();

        if ($cartExtension === null) {
            return $this;
        }

        $cartExtension->setCustomFees($customFees);

        return $this;
    }

    /**
     * @return array{code: string, title: Phrase, value: float}[]
     */
    public function fetch(Quote $quote, Total $total): array
    {
        [, $localCustomFees] = $this->getCustomFees($quote->getStore());

        return $localCustomFees;
    }

    /**
     * @return array{code: string, title: Phrase, value: float}[][]
     */
    private function getCustomFees(StoreInterface $store): array
    {
        $baseCustomFees = array_map(
            /**
             * @param array{code: string, title: string, value: float} $customFee
             * @return array{code: string, title: Phrase, value: float}
             */
            static function (array $customFee): array {
                $customFee['title'] = __($customFee['title']);

                return $customFee;
            },
            $this->config->getCustomFees($store->getId())
        );
        $localCustomFees = array_map(
            /**
             * @param array{code: string, title: Phrase, value: float} $customFee
             * @return array{code: string, title: Phrase, value: float}
             */
            function (array $customFee) use ($store): array {
                $customFee['value'] = $this->priceCurrency->convert($customFee['value'], $store);

                return $customFee;
            },
            $baseCustomFees
        );

        return [
            $baseCustomFees,
            $localCustomFees
        ];
    }
}
