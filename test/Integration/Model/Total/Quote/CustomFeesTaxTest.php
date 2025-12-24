<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Model\Total\Quote;

use JosephLeedy\CustomFees\Api\Data\CustomOrderFeeInterface;
use JosephLeedy\CustomFees\Model\Config;
use JosephLeedy\CustomFees\Model\FeeType;
use JosephLeedy\CustomFees\Model\Total\Quote\CustomFeesTax;
use JosephLeedy\CustomFees\Service\CustomQuoteFeesRetriever;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Api\Data\ShippingInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\Store\Model\ScopeInterface as StoreScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Fixture\Config as ConfigFixture;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

use function array_map;
use function array_walk;
use function round;

final class CustomFeesTaxTest extends TestCase
{
    /**
     * @dataProvider collectsCustomFeeTaxTotalsDataProvider
     */
    #[ConfigFixture(
        Config::CONFIG_PATH_CUSTOM_FEES,
        '{"_1727299833817_817":{"code":"test_fee_0","title":"Test Fee","type":"fixed","status":"1","value":"4.00","adva'
        . 'nced":"{\\"show_percentage\\":\\"0\\"}"},"_1727299843197_197":{"code":"test_fee_1","title":"Another Fee","ty'
        . 'pe":"percent","status":"1","value":"5.00","advanced":"{\\"show_percentage\\":\\"1\\"}"}}',
        StoreScopeInterface::SCOPE_STORE,
        'default',
    )]
    #[ConfigFixture(
        Config::CONFIG_PATH_TAX_CLASS_CUSTOM_FEE_TAX_CLASS,
        '2',
        StoreScopeInterface::SCOPE_STORE,
        'default',
    )]
    #[ConfigFixture('shipping/origin/country_id', 'US', StoreScopeInterface::SCOPE_STORE, 'default')]
    #[ConfigFixture('shipping/origin/region_id', '1', StoreScopeInterface::SCOPE_STORE, 'default')]
    #[ConfigFixture('shipping/origin/postcode', '75477', StoreScopeInterface::SCOPE_STORE, 'default')]
    #[DataFixture('Magento/Tax/_files/tax_rule_region_1_al.php')]
    #[DataFixture('Magento/Checkout/_files/quote_with_taxable_product_and_customer.php')]
    public function testCollectsCustomFeeTaxTotals(bool $isTaxIncluded): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $configStub = $this
            ->getMockBuilder(Config::class)
            ->setConstructorArgs(
                [
                    'storeManager' => $objectManager->get(StoreManagerInterface::class),
                    'scopeConfig' => $objectManager->get(ScopeConfigInterface::class),
                    'serializer' => $objectManager->get(SerializerInterface::class),
                ],
            )->onlyMethods(['isTaxIncluded'])
            ->getMock();
        /** @var Quote $quote */
        $quote = $objectManager->create(Quote::class);
        /** @var QuoteResource $quoteResource */
        $quoteResource = $objectManager->create(QuoteResource::class);
        /** @var ShippingInterface $shipping */
        $shipping = $objectManager->create(ShippingInterface::class);
        /** @var ShippingAssignmentInterface $shippingAssignment */
        $shippingAssignment = $objectManager->create(ShippingAssignmentInterface::class);
        /** @var Total $total */
        $total = $objectManager->create(Total::class);
        /** @var CustomFeesTax $customFeesTaxTotalCollector */
        $customFeesTaxTotalCollector = $objectManager->create(
            CustomFeesTax::class,
            [
                'config' => $configStub,
            ],
        );

        $configStub->method('isTaxIncluded')->willReturn($isTaxIncluded);

        $quoteResource->load($quote, 'test_order_with_taxable_product', 'reserved_order_id');

        $this->setCustomFeesForQuote($quote);

        $shipping->setAddress($quote->getShippingAddress());

        $shippingAssignment->setShipping($shipping);

        $customFeesTaxTotalCollector->collect($quote, $shippingAssignment, $total);

        $expectedCustomFees = [
            'test_fee_0' => $objectManager->create(
                CustomOrderFeeInterface::class,
                [
                    'data' => [
                        'code' => 'test_fee_0',
                        'title' => 'Test Fee',
                        'type' => FeeType::Fixed,
                        'percent' => null,
                        'show_percentage' => false,
                        'base_value' => $isTaxIncluded ? 3.72 : 4.00,
                        'value' => $isTaxIncluded ? 3.72 : 4.00,
                        'base_value_with_tax' => $isTaxIncluded ? 4.00 : 4.30,
                        'value_with_tax' => $isTaxIncluded ? 4.00 : 4.30,
                        'base_tax_amount' => $isTaxIncluded ? 0.28 : 0.30,
                        'tax_amount' => $isTaxIncluded ? 0.28 : 0.30,
                        'tax_rate' => 7.5,
                    ],
                ],
            ),
            'test_fee_1' => $objectManager->create(
                CustomOrderFeeInterface::class,
                [
                    'data' => [
                        'code' => 'test_fee_1',
                        'title' => 'Another Fee',
                        'type' => FeeType::Percent,
                        'percent' => 5.0,
                        'show_percentage' => true,
                        'base_value' => $isTaxIncluded ? 0.47 : 0.50,
                        'value' => $isTaxIncluded ? 0.47 : 0.50,
                        'base_value_with_tax' => $isTaxIncluded ? 0.50 : 0.54,
                        'value_with_tax' => $isTaxIncluded ? 0.50 : 0.54,
                        'base_tax_amount' => $isTaxIncluded ? 0.03 : 0.04,
                        'tax_amount' => $isTaxIncluded ? 0.03 : 0.04,
                        'tax_rate' => 7.5,
                    ],
                ],
            ),
        ];
        $actualCustomFees = $quote->getExtensionAttributes()->getCustomFees();
        $expectedTaxAmount = $isTaxIncluded ? 0.31 : 0.34;
        $actualTaxAmount = (float) $total->getTotalAmount('tax');

        self::assertEquals($expectedCustomFees, $actualCustomFees);
        self::assertSame($expectedTaxAmount, $actualTaxAmount);
    }

    #[ConfigFixture(
        Config::CONFIG_PATH_CUSTOM_FEES,
        '{"_1727299833817_817":{"code":"test_fee_0","title":"Test Fee","type":"fixed","status":"1","value":"4.00","adva'
        . 'nced":"{\\"show_percentage\\":\\"0\\"}"},"_1727299843197_197":{"code":"test_fee_1","title":"Another Fee","ty'
        . 'pe":"percent","status":"1","value":"5.00","advanced":"{\\"show_percentage\\":\\"1\\"}"}}',
        StoreScopeInterface::SCOPE_STORE,
        'default',
    )]
    #[ConfigFixture(
        Config::CONFIG_PATH_TAX_CALCULATION_CUSTOM_FEES_INCLUDE_TAX,
        '0',
        StoreScopeInterface::SCOPE_STORE,
        'default',
    )]
    #[ConfigFixture(
        Config::CONFIG_PATH_TAX_CLASS_CUSTOM_FEE_TAX_CLASS,
        '2',
        StoreScopeInterface::SCOPE_STORE,
        'default',
    )]
    #[ConfigFixture('shipping/origin/country_id', 'US', StoreScopeInterface::SCOPE_STORE, 'default')]
    #[ConfigFixture('shipping/origin/region_id', '1', StoreScopeInterface::SCOPE_STORE, 'default')]
    #[ConfigFixture('shipping/origin/postcode', '75477', StoreScopeInterface::SCOPE_STORE, 'default')]
    #[DataFixture('Magento/Tax/_files/tax_rule_region_1_al.php')]
    #[DataFixture('Magento/Checkout/_files/quote_with_taxable_product_and_customer.php')]
    public function testCollectsCustomFeeTaxTotalsAfterDiscounts(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var Quote $quote */
        $quote = $objectManager->create(Quote::class);
        /** @var QuoteResource $quoteResource */
        $quoteResource = $objectManager->create(QuoteResource::class);
        /** @var ShippingInterface $shipping */
        $shipping = $objectManager->create(ShippingInterface::class);
        /** @var ShippingAssignmentInterface $shippingAssignment */
        $shippingAssignment = $objectManager->create(ShippingAssignmentInterface::class);
        /** @var Total $total */
        $total = $objectManager->create(Total::class);
        /** @var CustomFeesTax $customFeesTaxTotalCollector */
        $customFeesTaxTotalCollector = $objectManager->create(CustomFeesTax::class);

        $quoteResource->load($quote, 'test_order_with_taxable_product', 'reserved_order_id');

        $this->setCustomFeesForQuote($quote);

        $customFees = $quote->getExtensionAttributes()->getCustomFees();

        array_walk(
            $customFees,
            static function (CustomOrderFeeInterface $customFee) use ($quote): void {
                $discountRate = 25.0;
                $discountAmount = round($customFee->getBaseValue() * ($discountRate / 100), 2);

                $customFee->setBaseDiscountAmount($discountAmount);
                $customFee->setDiscountAmount($discountAmount);
                $customFee->setDiscountRate($discountRate);
            },
        );

        $shipping->setAddress($quote->getShippingAddress());

        $shippingAssignment->setShipping($shipping);

        $customFeesTaxTotalCollector->collect($quote, $shippingAssignment, $total);

        $expectedCustomFees = [
            'test_fee_0' => $objectManager->create(
                CustomOrderFeeInterface::class,
                [
                    'data' => [
                        'code' => 'test_fee_0',
                        'title' => 'Test Fee',
                        'type' => FeeType::Fixed,
                        'percent' => null,
                        'show_percentage' => false,
                        'base_value' => 4.00,
                        'value' => 4.00,
                        'base_value_with_tax' => 4.30,
                        'value_with_tax' => 4.30,
                        'base_tax_amount' => 0.23,
                        'tax_amount' => 0.23,
                        'tax_rate' => 7.5,
                        'base_discount_amount' => 1.00,
                        'discount_amount' => 1.00,
                        'discount_rate' => 25.0,
                    ],
                ],
            ),
            'test_fee_1' => $objectManager->create(
                CustomOrderFeeInterface::class,
                [
                    'data' => [
                        'code' => 'test_fee_1',
                        'title' => 'Another Fee',
                        'type' => FeeType::Percent,
                        'percent' => 5.0,
                        'show_percentage' => true,
                        'base_value' => 0.50,
                        'value' => 0.50,
                        'base_value_with_tax' => 0.54,
                        'value_with_tax' => 0.54,
                        'base_tax_amount' => 0.02,
                        'tax_amount' => 0.02,
                        'tax_rate' => 7.5,
                        'base_discount_amount' => 0.13,
                        'discount_amount' => 0.13,
                        'discount_rate' => 25.0,
                    ],
                ],
            ),
        ];
        $actualCustomFees = $quote->getExtensionAttributes()->getCustomFees();
        $expectedTaxAmount = 0.25;
        $actualTaxAmount = (float) $total->getTotalAmount('tax');

        self::assertEquals($expectedCustomFees, $actualCustomFees);
        self::assertSame($expectedTaxAmount, $actualTaxAmount);
    }

    #[ConfigFixture(
        Config::CONFIG_PATH_CUSTOM_FEES,
        '{"_1727299833817_817":{"code":"test_fee_0","title":"Test Fee","type":"fixed","status":"1","value":"4.00","adva'
        . 'nced":"{\\"show_percentage\\":\\"0\\"}"},"_1727299843197_197":{"code":"test_fee_1","title":"Another Fee","ty'
        . 'pe":"percent","status":"1","value":"5.00","advanced":"{\\"show_percentage\\":\\"1\\"}"}}',
        StoreScopeInterface::SCOPE_STORE,
        'default',
    )]
    #[DataFixture('Magento/Checkout/_files/quote_with_address.php')]
    public function testFetchesCustomFeeTaxTotals(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var Quote $quote */
        $quote = $objectManager->create(Quote::class);
        /** @var QuoteResource $quoteResource */
        $quoteResource = $objectManager->create(QuoteResource::class);
        /** @var Total $total */
        $total = $objectManager->create(Total::class);
        /** @var CustomFeesTax $customFeesTaxTotalCollector */
        $customFeesTaxTotalCollector = $objectManager->create(CustomFeesTax::class);

        $quoteResource->load($quote, 'test_order_1', 'reserved_order_id');

        $this->setCustomFeesForQuote($quote);

        $expectedTotals = [
            [
                'code' => 'test_fee_0',
                'value' => 4.00,
                'tax_details' => [
                    'value_with_tax' => 4.30,
                    'tax_amount' => 0.30,
                    'tax_rate' => 7.5,
                ],
            ],
            [
                'code' => 'test_fee_1',
                'value' => 1.00,
                'tax_details' => [
                    'value_with_tax' => 1.08,
                    'tax_amount' => 0.08,
                    'tax_rate' => 7.5,
                ],
            ],
        ];
        $actualTotals = $customFeesTaxTotalCollector->fetch($quote, $total);

        self::assertEquals($expectedTotals, $actualTotals);
    }

    public static function collectsCustomFeeTaxTotalsDataProvider(): array
    {
        return [
            'custom fee value excludes tax' => [
                'isTaxIncluded' => false,
            ],
            'custom fee value includes tax' => [
                'isTaxIncluded' => true,
            ],
        ];
    }

    private function setCustomFeesForQuote(Quote $quote): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var CustomQuoteFeesRetriever $customQuoteFeesRetriever */
        $customQuoteFeesRetriever = $objectManager->create(CustomQuoteFeesRetriever::class);
        /** @var array<string, CustomOrderFeeInterface> $customFees */
        $customFees = array_map(
            static function (array $customFeeData) use ($quote, $objectManager): CustomOrderFeeInterface {
                $isPercent = FeeType::Percent->equals($customFeeData['type']);
                $value = $isPercent
                    ? round(((float) ($quote->getSubtotal() ?? 20.00)) * ((float) $customFeeData['value'] / 100), 2)
                    : (float) $customFeeData['value'];
                $valueWithTax = round($value * 1.075, 2);
                $taxAmount = round($valueWithTax - $value, 2);

                return $objectManager->create(
                    CustomOrderFeeInterface::class,
                    [
                        'data' => [
                            'code' => $customFeeData['code'],
                            'title' => $customFeeData['title'],
                            'type' => $customFeeData['type'],
                            'percent' => $isPercent ? $customFeeData['value'] : null,
                            'show_percentage' => $customFeeData['advanced']['show_percentage'],
                            'base_value' => $value,
                            'value' => $value,
                            'base_value_with_tax' => $valueWithTax,
                            'value_with_tax' => $valueWithTax,
                            'base_tax_amount' => $taxAmount,
                            'tax_amount' => $taxAmount,
                            'tax_rate' => 7.5,
                        ],
                    ],
                );
            },
            $customQuoteFeesRetriever->retrieveApplicableFees($quote),
        );

        $quote->getExtensionAttributes()?->setCustomFees($customFees);
    }
}
