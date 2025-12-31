<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Model\Total\Quote;

use JosephLeedy\CustomFees\Api\ConfigInterface;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFeeInterface;
use Magento\Customer\Api\AccountManagementInterface as CustomerAccountManagement;
use Magento\Customer\Api\Data\AddressInterfaceFactory as CustomerAddressFactory;
use Magento\Customer\Api\Data\RegionInterfaceFactory as CustomerAddressRegionFactory;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Address\Total\CollectorInterface;
use Magento\Tax\Api\Data\QuoteDetailsInterfaceFactory;
use Magento\Tax\Api\Data\QuoteDetailsItemExtensionInterfaceFactory;
use Magento\Tax\Api\Data\QuoteDetailsItemInterface;
use Magento\Tax\Api\Data\QuoteDetailsItemInterfaceFactory;
use Magento\Tax\Api\Data\TaxClassKeyInterface;
use Magento\Tax\Api\Data\TaxClassKeyInterfaceFactory;
use Magento\Tax\Api\Data\TaxDetailsItemInterface;
use Magento\Tax\Api\TaxCalculationInterface;
use Magento\Tax\Helper\Data as TaxHelper;
use Magento\Tax\Model\Config as TaxConfig;
use Magento\Tax\Model\Sales\Total\Quote\CommonTaxCollector;

use function array_map;
use function array_reduce;
use function array_values;
use function array_walk;

class CustomFeesTax extends CommonTaxCollector
{
    public function __construct(
        private readonly ConfigInterface $config,
        TaxConfig $taxConfig,
        TaxCalculationInterface $taxCalculationService,
        QuoteDetailsInterfaceFactory $quoteDetailsDataObjectFactory,
        QuoteDetailsItemInterfaceFactory $quoteDetailsItemDataObjectFactory,
        TaxClassKeyInterfaceFactory $taxClassKeyDataObjectFactory,
        CustomerAddressFactory $customerAddressFactory,
        CustomerAddressRegionFactory $customerAddressRegionFactory,
        TaxHelper $taxHelper = null,
        QuoteDetailsItemExtensionInterfaceFactory $quoteDetailsItemExtensionInterfaceFactory = null,
        ?CustomerAccountManagement $customerAccountManagement = null,
    ) {
        parent::__construct(
            $taxConfig,
            $taxCalculationService,
            $quoteDetailsDataObjectFactory,
            $quoteDetailsItemDataObjectFactory,
            $taxClassKeyDataObjectFactory,
            $customerAddressFactory,
            $customerAddressRegionFactory,
            $taxHelper,
            $quoteDetailsItemExtensionInterfaceFactory,
            $customerAccountManagement,
        );
    }

    public function collect(
        Quote $quote,
        ShippingAssignmentInterface $shippingAssignment,
        Total $total,
    ): CollectorInterface {
        parent::collect($quote, $shippingAssignment, $total);

        if ($shippingAssignment->getItems() === null || $shippingAssignment->getItems() === []) {
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

        $this->applyTaxToCustomFees($customFees, $shippingAssignment);
        $this->setTotals($customFees, $total);

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
    private function applyTaxToCustomFees(array $customFees, ShippingAssignmentInterface $shippingAssignment): void
    {
        /** @var Quote $quote */
        $quote = $shippingAssignment->getShipping()->getAddress()->getQuote();
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
        $baseQuoteDetails = $this->prepareQuoteDetails($shippingAssignment, $baseCustomFeeTaxDataObjects);
        $quoteDetails = $this->prepareQuoteDetails($shippingAssignment, $customFeeTaxDataObjects);
        $baseTaxDetails = $this->taxCalculationService->calculateTax($baseQuoteDetails, (int) $storeId);
        $taxDetails = $this->taxCalculationService->calculateTax($quoteDetails, (int) $storeId);

        $this->processCustomFeeTaxData($baseTaxDetails->getItems() ?? [], $taxDetails->getItems() ?? [], $customFees);
    }

    private function buildCustomFeeTaxDataObject(
        CustomOrderFeeInterface $customOrderFee,
        int|string $storeId,
        bool $useBaseCurrency,
    ): QuoteDetailsItemInterface {
        /** @var TaxClassKeyInterface $taxClassKey */
        $taxClassKey = $this->taxClassKeyDataObjectFactory->create();
        /** @var QuoteDetailsItemInterface $quoteDetailsItem */
        $quoteDetailsItem = $this->quoteDetailsItemDataObjectFactory->create();

        $taxClassKey
            ->setType(TaxClassKeyInterface::TYPE_ID)
            ->setValue((string) $this->config->getTaxClass($storeId));

        $quoteDetailsItem
            ->setType('custom_fee')
            ->setCode($customOrderFee->getCode())
            ->setQuantity(1)
            ->setUnitPrice($customOrderFee->getValue())
            ->setDiscountAmount($customOrderFee->getDiscountAmount())
            ->setTaxClassKey($taxClassKey)
            ->setIsTaxIncluded($this->config->isTaxIncluded($storeId));

        if ($useBaseCurrency) {
            $quoteDetailsItem->setUnitPrice($customOrderFee->getBaseValue());
            $quoteDetailsItem->setDiscountAmount($customOrderFee->getBaseDiscountAmount());
        }

        return $quoteDetailsItem;
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
    ): void {
        array_walk(
            $baseCustomFeeTaxDetails,
            static function (TaxDetailsItemInterface $taxDetailsItem) use ($customFees): void {
                $customFeeCode = $taxDetailsItem->getCode();
                $customFee = $customFees[$customFeeCode];
                $rowTax = $taxDetailsItem->getRowTax();

                if ($rowTax === 0.0) {
                    return;
                }

                $customFee->setBaseValue(round($taxDetailsItem->getRowTotal(), 2));
                $customFee->setBaseValueWithTax(round($taxDetailsItem->getRowTotalInclTax(), 2));
                $customFee->setBaseTaxAmount($rowTax);
            },
        );

        array_walk(
            $customFeeTaxDetails,
            static function (TaxDetailsItemInterface $taxDetailsItem) use ($customFees): void {
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
            },
        );
    }

    /**
     * @param array<string, CustomOrderFeeInterface> $customFees
     */
    private function setTotals(array $customFees, Total $total): void
    {
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

        $baseTaxAmount = array_reduce(
            $customFees,
            static fn(float $amount, CustomOrderFeeInterface $customFee): float
                => $amount + $customFee->getBaseTaxAmount(),
            0.00,
        );
        $taxAmount = array_reduce(
            $customFees,
            static fn(float $amount, CustomOrderFeeInterface $customFee): float
                => $amount + $customFee->getTaxAmount(),
            0.00,
        );

        $total->addBaseTotalAmount('tax', $baseTaxAmount);
        $total->addTotalAmount('tax', $taxAmount);
    }
}
