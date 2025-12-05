<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Model\Total\Quote;

use JosephLeedy\CustomFees\Api\Data\CustomOrderFeeInterface;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFeeInterfaceFactory;
use JosephLeedy\CustomFees\Model\FeeType;
use JosephLeedy\CustomFees\Service\CustomQuoteFeesRetriever;
use Magento\Framework\Phrase;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Address\Total\AbstractTotal;
use Magento\Quote\Model\Quote\Address\Total\CollectorInterface;

use function array_map;
use function array_values;
use function array_walk;
use function count;
use function round;

class CustomFees extends AbstractTotal
{
    public const CODE = 'custom_fees';

    public function __construct(
        private readonly CustomQuoteFeesRetriever $customQuoteFeesRetriever,
        private readonly CustomOrderFeeInterfaceFactory $customOrderFeeFactory,
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

        $customFees = $this->getCustomFees($quote, $total);

        array_walk(
            $customFees,
            static function (CustomOrderFeeInterface $customFee) use ($total): void {
                $total->setBaseTotalAmount($customFee->getCode(), $customFee->getBaseValue());
                $total->setTotalAmount($customFee->getCode(), $customFee->getValue());
            },
        );

        $quote->getExtensionAttributes()?->setCustomFees($customFees);

        return $this;
    }

    /**
     * @return array{
     *     code: string,
     *     title: Phrase,
     *     value: float,
     * }[]
     */
    public function fetch(Quote $quote, Total $total): array
    {
        $customFees = array_values($this->getCustomFees($quote, $total));
        $totals = array_map(
            static fn(CustomOrderFeeInterface $customOrderFee): array => [
                'code' => $customOrderFee->getCode(),
                'title' => $customOrderFee->formatLabel(),
                'value' => $customOrderFee->getValue(),
            ],
            $customFees,
        );

        return $totals;
    }

    /**
     * @return array<string, CustomOrderFeeInterface>
     */
    private function getCustomFees(Quote $quote, Total $total): array
    {
        $store = $quote->getStore();
        $customFees = [];
        $customQuoteFees = $this->customQuoteFeesRetriever->retrieveApplicableFees($quote);

        if ($customQuoteFees === []) {
            return $customFees;
        }

        foreach ($customQuoteFees as $customFee) {
            $customFeeCode = $customFee['code'];
            /** @var CustomOrderFeeInterface $customOrderFee */
            $customOrderFee = $this->customOrderFeeFactory->create();

            $customOrderFee
                ->setCode($customFeeCode)
                ->setTitle($customFee['title'])
                ->setType($customFee['type'])
                ->setPercent(null)
                ->setShowPercentage((bool) ($customFee['advanced']['show_percentage'] ?? true));

            if (FeeType::Percent->equals($customFee['type'])) {
                $customOrderFee->setPercent((float) $customFee['value']);

                $customFee['value'] = round(((float) $customFee['value'] * (float) $total->getBaseSubtotal()) / 100, 2);
            }

            $convertedValue = $this->priceCurrency->convert($customFee['value'], $store);

            $customOrderFee
                ->setBaseValue((float) $customFee['value'])
                ->setValue($convertedValue);

            $customFees[$customFeeCode] = $customOrderFee;
        }

        return $customFees;
    }
}
