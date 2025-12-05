<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Model\Total\Quote;

use JosephLeedy\CustomFees\Api\ConfigInterface;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFeeInterface;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote;
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

use function array_map;
use function array_values;
use function array_walk;

class CustomFeesTax extends AbstractTotal
{
    public function __construct(
        private readonly TaxClassKeyInterfaceFactory $taxClassKeyFactory,
        private readonly QuoteDetailsItemInterfaceFactory $quoteDetailsItemFactory,
        private readonly ConfigInterface $config,
        private readonly QuoteDetailsInterfaceFactory $quoteDetailsFactory,
        private readonly CommonTaxCollector $commonTaxCollector,
        private readonly TaxCalculationInterface $taxCalculation,
    ) {}

    public function collect(
        Quote $quote,
        ShippingAssignmentInterface $shippingAssignment,
        Total $total,
    ): CollectorInterface {
        parent::collect($quote, $shippingAssignment, $total);

        if ($shippingAssignment->getItems() === []) {
            return $this;
        }

        /** @var array<string, CustomOrderFeeInterface> $customFees */
        $customFees = $quote->getExtensionAttributes()?->getCustomFees() ?? [];

        if ($customFees === []) {
            return $this;
        }

        array_walk(
            $customFees,
            static function (CustomOrderFeeInterface $customFee): void {
                $customFee->setBaseValueWithTax($customFee->getBaseValue());
                $customFee->setValueWithTax($customFee->getValue());
                $customFee->setBaseTaxAmount(0.00);
                $customFee->setTaxAmount(0.00);
                $customFee->setTaxRate(0.0);
            },
        );

        $this->applyTaxToCustomFees($customFees, $quote, $total);

        array_walk(
            $customFees,
            static function (CustomOrderFeeInterface $customFee) use ($total): void {
                if ($customFee->getTaxRate() === 0.0) {
                    return;
                }

                $total->setBaseTotalAmount($customFee->getCode(), $customFee->getBaseValue());
                $total->setTotalAmount($customFee->getCode(), $customFee->getValue());

                $total->setData('base_' . $customFee->getCode() . '_tax_amount', $customFee->getBaseTaxAmount());
                $total->setData($customFee->getCode() . '_tax_amount', $customFee->getTaxAmount());
            },
        );

        return $this;
    }

    /**
     * @return array{
     *     code: string,
     *     value: float,
     *     tax_details: array{
     *         value_with_tax: float,
     *         tax_amount: float,
     *         tax_rate: float,
     *     },
     * }[]
     */
    public function fetch(Quote $quote, Total $total): array
    {
        $customFees = array_values($quote->getExtensionAttributes()?->getCustomFees() ?? []);
        $totals = array_map(
            static fn(CustomOrderFeeInterface $customOrderFee): array => [
                'code' => $customOrderFee->getCode(),
                'value' => $customOrderFee->getValue(),
                'tax_details' => [
                    'value_with_tax' => $customOrderFee->getValueWithTax(),
                    'tax_amount' => $customOrderFee->getTaxAmount(),
                    'tax_rate' => $customOrderFee->getTaxRate(),
                ],
            ],
            $customFees,
        );

        return $totals;
    }

    /**
     * @param array<string, CustomOrderFeeInterface> $customFees
     */
    private function applyTaxToCustomFees(array $customFees, Quote $quote, Total $total): void
    {
        $storeId = $quote->getStoreId();
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
        $baseQuoteTaxDetails = $this->prepareQuoteTaxDetails($baseCustomFeeTaxDataObjects, $quote);
        $quoteTaxDetails = $this->prepareQuoteTaxDetails($customFeeTaxDataObjects, $quote);
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
    private function prepareQuoteTaxDetails(array $customFeeQuoteDataObjects, Quote $quote): QuoteDetailsInterface
    {
        $shippingAddress = $quote->getShippingAddress();
        /** @var TaxClassKeyInterface $taxClassKey */
        $taxClassKey = $this->taxClassKeyFactory->create();
        /** @var QuoteDetailsInterface $quoteTaxDetails */
        $quoteTaxDetails = $this->quoteDetailsFactory->create();

        $this->commonTaxCollector->populateAddressData($quoteTaxDetails, $shippingAddress);

        $taxClassKey
            ->setType(TaxClassKeyInterface::TYPE_ID)
            ->setValue((string) $quote->getCustomerTaxClassId());

        $quoteTaxDetails
            ->setItems($customFeeQuoteDataObjects)
            ->setCustomerTaxClassKey($taxClassKey)
            ->setCustomerId((int) $quote->getCustomerId());

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
                $rowTax = $taxDetailsItem->getRowTax();

                if ($rowTax === 0.0) {
                    return;
                }

                $customFee->setBaseValue(round($taxDetailsItem->getRowTotal(), 2));
                $customFee->setBaseValueWithTax(round($taxDetailsItem->getRowTotalInclTax(), 2));
                $customFee->setBaseTaxAmount($rowTax);

                $baseTaxAmount += $rowTax;
            },
        );

        array_walk(
            $customFeeTaxDetails,
            static function (TaxDetailsItemInterface $taxDetailsItem) use ($customFees, &$taxAmount): void {
                $customFeeCode = $taxDetailsItem->getCode();
                $customFee = $customFees[$customFeeCode];
                $rowTax = $taxDetailsItem->getRowTax();

                if ($rowTax === 0.0) {
                    return;
                }

                $customFee->setValue(round($taxDetailsItem->getRowTotal(), 2));
                $customFee->setValueWithTax(round($taxDetailsItem->getRowTotalInclTax(), 2));
                $customFee->setTaxAmount($rowTax);
                $customFee->setTaxRate($taxDetailsItem->getTaxPercent());

                $taxAmount += $rowTax;
            },
        );

        $total->addBaseTotalAmount('tax', $baseTaxAmount);
        $total->addTotalAmount('tax', $taxAmount);
    }
}
