<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Model\Total\Quote;

use JosephLeedy\CustomFees\Api\ConfigInterface;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFeeInterface;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFeeInterfaceFactory;
use JosephLeedy\CustomFees\Model\FeeStatus;
use JosephLeedy\CustomFees\Model\FeeType;
use JosephLeedy\CustomFees\Service\ConditionsApplier;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Address\Total\AbstractTotal;
use Magento\Quote\Model\Quote\Address\Total\CollectorInterface;
use Magento\Tax\Api\Data\QuoteDetailsInterface;
use Magento\Tax\Api\Data\QuoteDetailsInterfaceFactory;
use Magento\Tax\Api\Data\QuoteDetailsItemInterface;
use Magento\Tax\Api\Data\QuoteDetailsItemInterfaceFactory;
use Magento\Tax\Api\Data\TaxClassKeyInterface;
use Magento\Tax\Api\Data\TaxClassKeyInterfaceFactory;
use Magento\Tax\Api\Data\TaxDetailsItemInterface;
use Magento\Tax\Api\TaxCalculationInterface;
use Magento\Tax\Model\Sales\Total\Quote\CommonTaxCollector;
use Psr\Log\LoggerInterface;

use function array_key_exists;
use function array_map;
use function array_values;
use function array_walk;
use function count;
use function round;

class CustomFees extends AbstractTotal
{
    public const CODE = 'custom_fees';

    public function __construct(
        private readonly ConfigInterface $config,
        private readonly LoggerInterface $logger,
        private readonly CustomOrderFeeInterfaceFactory $customOrderFeeFactory,
        private readonly ConditionsApplier $conditionsApplier,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly QuoteDetailsItemInterfaceFactory $quoteDetailsItemFactory,
        private readonly TaxClassKeyInterfaceFactory $taxClassKeyFactory,
        private readonly QuoteDetailsInterfaceFactory $quoteDetailsFactory,
        private readonly CommonTaxCollector $commonTaxCollector,
        private readonly TaxCalculationInterface $taxCalculation,
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

        $this->applyTaxToCustomFees($customFees, $quote->getStoreId(), $shippingAssignment, $total);

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

        try {
            $configuredCustomFees = $this->config->getCustomFees($store->getId());
        } catch (LocalizedException $localizedException) {
            $this->logger->critical($localizedException->getLogMessage(), ['exception' => $localizedException]);

            return $customFees;
        }

        foreach ($configuredCustomFees as $customFee) {
            $customFeeCode = $customFee['code'];

            if ($customFeeCode === 'example_fee' || !FeeStatus::Enabled->equals($customFee['status'])) {
                continue;
            }

            if (array_key_exists('conditions', $customFee['advanced']) && $customFee['advanced']['conditions'] !== []) {
                $isApplicable = $this->conditionsApplier->isApplicable(
                    $quote,
                    $customFeeCode,
                    $customFee['advanced']['conditions'],
                );

                if (!$isApplicable) {
                    continue;
                }
            }

            /** @var CustomOrderFeeInterface $customOrderFee */
            $customOrderFee = $this->customOrderFeeFactory->create();

            $customOrderFee
                ->setCode($customFeeCode)
                ->setTitle($customFee['title'])
                ->setType($customFee['type'])
                ->setPercent(null)
                ->setShowPercentage((bool) ($customFee['advanced']['show_percentage'] ?? true))
                ->setBaseTaxAmount(0.00)
                ->setTaxAmount(0.00);

            if (FeeType::Percent->equals($customFee['type'])) {
                $customOrderFee->setPercent((float) $customFee['value']);

                $customFee['value'] = round(((float) $customFee['value'] * (float) $total->getBaseSubtotal()) / 100, 2);
            }

            $customOrderFee
                ->setBaseValue((float) $customFee['value'])
                ->setValue($this->priceCurrency->convert($customFee['value'], $store));

            $customFees[$customFeeCode] = $customOrderFee;
        }

        return $customFees;
    }

    /**
     * @param array<string, CustomOrderFeeInterface> $customFees
     */
    private function applyTaxToCustomFees(
        array $customFees,
        int|string $storeId,
        ShippingAssignmentInterface $shippingAssignment,
        Total $total,
    ): void {
        $baseCustomFeeTaxDataObjects = array_map(
            fn(CustomOrderFeeInterface $customFee): QuoteDetailsItemInterface => $this->buildCustomFeeTaxDataObject(
                $customFee,
                $storeId,
                true,
            ),
            $customFees,
        );
        $customFeeTaxDataObjects = array_map(
            fn(CustomOrderFeeInterface $customFee): QuoteDetailsItemInterface => $this->buildCustomFeeTaxDataObject(
                $customFee,
                $storeId,
                false,
            ),
            $customFees,
        );
        $baseQuoteTaxDetails = $this->prepareQuoteTaxDetails(
            $baseCustomFeeTaxDataObjects,
            $shippingAssignment->getShipping()->getAddress(),
        );
        $quoteTaxDetails = $this->prepareQuoteTaxDetails(
            $customFeeTaxDataObjects,
            $shippingAssignment->getShipping()->getAddress(),
        );
        $baseTaxDetails = $this->taxCalculation->calculateTax($baseQuoteTaxDetails, (int) $storeId);
        $taxDetails = $this->taxCalculation->calculateTax($quoteTaxDetails, (int) $storeId);

        $this->processCustomFeeTaxData(
            $baseTaxDetails->getItems() ?? [],
            $taxDetails->getItems() ?? [],
            $customFees,
            $total,
        );
    }

    private function buildCustomFeeTaxDataObject(
        CustomOrderFeeInterface $customOrderFee,
        int|string $storeId,
        bool $useBaseCurrency,
    ): QuoteDetailsItemInterface {
        /** @var TaxClassKeyInterface $taxClassKey */
        $taxClassKey = $this->taxClassKeyFactory->create();
        /** @var QuoteDetailsItemInterface $quoteDetailsItem */
        $quoteDetailsItem = $this->quoteDetailsItemFactory->create();

        $taxClassKey
            ->setType(TaxClassKeyInterface::TYPE_ID)
            ->setValue((string) $this->config->getTaxClass($storeId));

        $quoteDetailsItem
            ->setType('custom_fee')
            ->setCode($customOrderFee->getCode())
            ->setQuantity(1)
            ->setUnitPrice($customOrderFee->getValue())
            ->setTaxClassKey($taxClassKey)
            ->setIsTaxIncluded($this->config->isTaxIncluded($storeId));

        if ($useBaseCurrency) {
            $quoteDetailsItem->setUnitPrice($customOrderFee->getBaseValue());
        }

        return $quoteDetailsItem;
    }

    /**
     * @param QuoteDetailsItemInterface[] $customFeeQuoteDataObjects
     */
    private function prepareQuoteTaxDetails(
        array $customFeeQuoteDataObjects,
        AddressInterface $shippingAddress,
    ): QuoteDetailsInterface {
        /** @var AddressInterface&Address $shippingAddress */

        /** @var TaxClassKeyInterface $taxClassKey */
        $taxClassKey = $this->taxClassKeyFactory->create();
        /** @var QuoteDetailsInterface $quoteTaxDetails */
        $quoteTaxDetails = $this->quoteDetailsFactory->create();

        $this->commonTaxCollector->populateAddressData($quoteTaxDetails, $shippingAddress);

        $taxClassKey
            ->setType(TaxClassKeyInterface::TYPE_ID)
            ->setValue((string) $shippingAddress->getQuote()->getCustomerTaxClassId());

        $quoteTaxDetails
            ->setItems($customFeeQuoteDataObjects)
            ->setCustomerTaxClassKey($taxClassKey)
            ->setCustomerId((int) $shippingAddress->getQuote()->getCustomerId());

        return $quoteTaxDetails;
    }

    /**
     * @param TaxDetailsItemInterface[] $baseCustomFeeTaxDetails
     * @param TaxDetailsItemInterface[] $customFeeTaxDetails
     * @param array<string, CustomOrderFeeInterface> $customFees
     */
    private function processCustomFeeTaxData(
        array $baseCustomFeeTaxDetails,
        array $customFeeTaxDetails,
        array $customFees,
        Total $total,
    ): void {
        $baseTaxAmount = 0.00;
        $taxAmount = 0.00;

        array_walk(
            $baseCustomFeeTaxDetails,
            static function (TaxDetailsItemInterface $taxDetailsItem) use ($customFees, &$baseTaxAmount): void {
                $customFeeCode = $taxDetailsItem->getCode();
                $customFee = $customFees[$customFeeCode];

                $customFee->setBaseTaxAmount($taxDetailsItem->getRowTax());

                $baseTaxAmount += $taxDetailsItem->getRowTax();
            },
        );

        array_walk(
            $customFeeTaxDetails,
            static function (TaxDetailsItemInterface $taxDetailsItem) use ($customFees, &$taxAmount): void {
                $customFeeCode = $taxDetailsItem->getCode();
                $customFee = $customFees[$customFeeCode];

                $customFee->setTaxAmount($taxDetailsItem->getRowTax());

                $taxAmount += $taxDetailsItem->getRowTax();
            },
        );

        $total->addBaseTotalAmount('tax', $baseTaxAmount);
        $total->addTotalAmount('tax', $taxAmount);
    }
}
