<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Model\Total\Quote;

use JosephLeedy\CustomFees\Api\Data\CustomOrderFeeInterface;
use JosephLeedy\CustomFees\Model\Config;
use JosephLeedy\CustomFees\Model\FeeType;
use JosephLeedy\CustomFees\Model\Total\Quote\CustomFeesTax;
use JosephLeedy\CustomFees\Service\CustomFeeDiscountRulesApplier;
use JosephLeedy\CustomFees\Service\CustomQuoteFeesRetriever;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Api\Data\ShippingInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\SalesRule\Api\Data\RuleInterface;
use Magento\SalesRule\Api\RuleRepositoryInterface;
use Magento\SalesRule\Model\Converter\ToModel;
use Magento\SalesRule\Model\ResourceModel\Rule\Collection as RuleCollection;
use Magento\SalesRule\Model\Rule;
use Magento\SalesRule\Model\Validator;
use Magento\Store\Model\ScopeInterface as StoreScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Tax\Api\Data\AppliedTaxInterface;
use Magento\Tax\Api\Data\AppliedTaxRateInterface;
use Magento\TestFramework\Fixture\Config as ConfigFixture;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

use function array_first;
use function array_map;
use function round;

final class CustomFeesTaxTest extends TestCase
{
    /**
     * @dataProvider collectsCustomFeeTaxTotalsExcludingDiscountsDataProvider
     * @param array<string, CustomOrderFeeData> $expectedCustomFees
     * @param array<string, AppliedTaxData> $expectedAppliedTaxes
     * @param array{custom_fees: array<string, AppliedTaxData>} $expectedItemsAppliedTaxes
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
    public function testCollectsCustomFeeTaxTotalsExcludingDiscounts(
        bool $isTaxIncluded,
        array $expectedCustomFees,
        float $expectedTaxAmount,
        array $expectedAppliedTaxes,
        array $expectedItemsAppliedTaxes,
    ): void {
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
        $shippingAssignment->setItems($quote->getAllItems());

        $total->setAppliedTaxes([]);

        $customFeesTaxTotalCollector->collect($quote, $shippingAssignment, $total);

        $actualCustomFees = $this->convertCustomFeesToArray($quote->getExtensionAttributes()?->getCustomFees() ?? []);
        $actualTaxAmount = (float) $total->getTotalAmount('tax');
        $actualAppliedTaxes = $total->getAppliedTaxes();
        $actualItemsAppliedTaxes = $total->getItemsAppliedTaxes();

        self::assertEquals($expectedCustomFees, $actualCustomFees);
        self::assertSame($expectedTaxAmount, $actualTaxAmount);
        /* PHPUnit's `assertEquals` assertion sometimes does weird things with floating point numbers, so we need to use
           a delta to prevent failures */
        self::assertEqualsWithDelta($expectedAppliedTaxes, $actualAppliedTaxes, 0.0000000000000001);
        self::assertEquals($expectedItemsAppliedTaxes, $actualItemsAppliedTaxes);
    }

    /**
     * @dataProvider collectsCustomFeeTaxTotalsIncludingDiscountsDataProvider
     * @param array<string, CustomOrderFeeInterface> $expectedCustomFees
     * @param array<string, AppliedTaxData> $expectedAppliedTaxes
     * @param array{custom_fees: array<string, AppliedTaxData>} $expectedItemsAppliedTaxes
     */
    #[ConfigFixture(
        Config::CONFIG_PATH_CUSTOM_FEES,
        '{"_1727299833817_817":{"code":"test_fee_0","title":"Test Fee","type":"fixed","status":"1","value":"4.00","adva'
        . 'nced":"{\\"show_percentage\\":\\"0\\"}"},"_1727299843197_197":{"code":"test_fee_1","title":"Another Fee","ty'
        . 'pe":"percent","status":"1","value":"5.00","advanced":"{\\"show_percentage\\":\\"1\\"}"}}',
        StoreScopeInterface::SCOPE_STORE,
        'default',
    )]
    #[ConfigFixture('shipping/origin/country_id', 'US', StoreScopeInterface::SCOPE_STORE, 'default')]
    #[ConfigFixture('shipping/origin/region_id', '1', StoreScopeInterface::SCOPE_STORE, 'default')]
    #[ConfigFixture('shipping/origin/postcode', '75477', StoreScopeInterface::SCOPE_STORE, 'default')]
    #[DataFixture('Magento/Tax/_files/tax_rule_region_1_al.php')]
    #[DataFixture('Magento/Checkout/_files/quote_with_taxable_product_and_customer.php')]
    #[DataFixture('JosephLeedy_CustomFees::../test/Integration/_files/cart_rule_10_percent_off_custom_fees.php')]
    public function testCollectsCustomFeeTaxTotalsIncludingDiscounts(
        bool $isTaxIncluded,
        array $expectedCustomFees,
        float $expectedTaxAmount,
        array $expectedAppliedTaxes,
        array $expectedItemsAppliedTaxes,
        float $expectedDiscountTaxCompensationAmount,
    ): void {
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
        $configStub = $this->createStub(Config::class);
        /** @var SearchCriteriaBuilder $searchCriteriaBuilder */
        $searchCriteriaBuilder = $objectManager->create(SearchCriteriaBuilder::class);
        /** @var RuleRepositoryInterface $ruleRepository */
        $ruleRepository = $objectManager->create(RuleRepositoryInterface::class);
        $searchCriteria = $searchCriteriaBuilder
            ->addFilter('name', '10% Off on orders with two items')
            ->create();
        /** @var RuleInterface $ruleData */
        $ruleData = array_first($ruleRepository->getList($searchCriteria)->getItems());
        /** @var ToModel $ruleDataConverter */
        $ruleDataConverter = $objectManager->create(ToModel::class);
        /** @var Rule $rule */
        $rule = $ruleDataConverter->toModel($ruleData);
        $ruleCollectionStub = $this->createStub(RuleCollection::class);
        $validatorStub = $this->createStub(Validator::class);
        /** @var CustomFeeDiscountRulesApplier $customFeeDiscountRulesApplier */
        $customFeeDiscountRulesApplier = $objectManager->create(
            CustomFeeDiscountRulesApplier::class,
            [
                'validator' => $validatorStub,
            ],
        );
        /** @var CustomFeesTax $customFeesTaxTotalCollector */
        $customFeesTaxTotalCollector = $objectManager->create(
            CustomFeesTax::class,
            [
                'config' => $configStub,
                'discountRulesApplier' => $customFeeDiscountRulesApplier,
            ],
        );

        $quoteResource->load($quote, 'test_order_with_taxable_product', 'reserved_order_id');

        $this->setCustomFeesForQuote($quote);

        $address = $quote->getShippingAddress();

        $shipping->setAddress($address);

        $shippingAssignment->setShipping($shipping);
        $shippingAssignment->setItems($quote->getAllItems());

        $configStub->method('isTaxIncluded')->willReturn($isTaxIncluded);
        $configStub->method('getTaxClass')->willReturn(2);

        $rule->setIsValidForAddress($address, true);

        $ruleCollectionStub->method('getItems')->willReturn([$rule->getRuleId() => $rule]);

        $validatorStub->method('getRules')->willReturn($ruleCollectionStub);

        $total->setAppliedTaxes([]);

        $customFeesTaxTotalCollector->collect($quote, $shippingAssignment, $total);

        $actualCustomFees = $this->convertCustomFeesToArray($quote->getExtensionAttributes()?->getCustomFees() ?? []);
        $actualTaxAmount = (float) $total->getTotalAmount('tax');
        $actualAppliedTaxes = $total->getAppliedTaxes();
        $actualItemsAppliedTaxes = $total->getItemsAppliedTaxes();
        $actualDiscountTaxCompensationAmount = (float) $total->getTotalAmount('custom_fees_discount_tax_compensation');

        self::assertEquals($expectedCustomFees, $actualCustomFees);
        self::assertSame($expectedTaxAmount, $actualTaxAmount);
        /* PHPUnit's `assertEquals` assertion sometimes does weird things with floating point numbers, so we need to use
           a delta to prevent failures */
        self::assertEqualsWithDelta($expectedAppliedTaxes, $actualAppliedTaxes, 0.0000000000000001);
        self::assertEquals($expectedItemsAppliedTaxes, $actualItemsAppliedTaxes);
        self::assertSame($expectedDiscountTaxCompensationAmount, $actualDiscountTaxCompensationAmount);
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
        Config::CONFIG_PATH_TAX_CLASS_CUSTOM_FEE_TAX_CLASS,
        '2',
        StoreScopeInterface::SCOPE_STORE,
        'default',
    )]
    #[ConfigFixture(
        Config::CONFIG_PATH_TAX_CALCULATION_CUSTOM_FEES_INCLUDE_TAX,
        '1',
        StoreScopeInterface::SCOPE_STORE,
        'default',
    )]
    #[ConfigFixture('shipping/origin/country_id', 'US', StoreScopeInterface::SCOPE_STORE, 'default')]
    #[ConfigFixture('shipping/origin/region_id', '1', StoreScopeInterface::SCOPE_STORE, 'default')]
    #[ConfigFixture('shipping/origin/postcode', '75477', StoreScopeInterface::SCOPE_STORE, 'default')]
    #[DataFixture('Magento/Tax/_files/tax_rule_region_1_al.php')]
    #[DataFixture('JosephLeedy_CustomFees::../test/Integration/_files/quote_without_address.php')]
    #[DataFixture('JosephLeedy_CustomFees::../test/Integration/_files/cart_rule_10_percent_off_custom_fees.php')]
    public function testCollectsCustomFeeTaxTotalsFromStoreTaxIfAddressIsNotSet(): void
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

        $quoteResource->load($quote, 'test_order_1', 'reserved_order_id');

        $this->setCustomFeesForQuote($quote);

        $shipping->setAddress($quote->getShippingAddress());

        $shippingAssignment->setShipping($shipping);
        $shippingAssignment->setItems($quote->getAllItems());

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
                        'base_value' => 3.72,
                        'value' => 3.72,
                        'base_value_with_tax' => 3.72,
                        'value_with_tax' => 3.72,
                        'base_tax_amount' => 0.00,
                        'tax_amount' => 0.00,
                        'tax_rate' => 0.0,
                        'base_applied_taxes' => [],
                        'applied_taxes' => [],
                        'base_discount_tax_compensation' => 0.00,
                        'discount_tax_compensation' => 0.00,
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
                        'base_value' => 0.93,
                        'value' => 0.93,
                        'base_value_with_tax' => 0.93,
                        'value_with_tax' => 0.93,
                        'base_tax_amount' => 0.00,
                        'tax_amount' => 0.00,
                        'tax_rate' => 0.0,
                        'base_applied_taxes' => [],
                        'applied_taxes' => [],
                        'base_discount_tax_compensation' => 0.00,
                        'discount_tax_compensation' => 0.00,
                    ],
                ],
            ),
        ];
        $expectedItemsAppliedTaxes = [
            'custom_fees' => [],
        ];
        $actualCustomFees = $quote->getExtensionAttributes()->getCustomFees();
        $actualAppliedTaxes = $total->getAppliedTaxes();
        $actualItemsAppliedTaxes = $total->getItemsAppliedTaxes();

        self::assertEquals($expectedCustomFees, $actualCustomFees);
        self::assertEmpty($actualAppliedTaxes);
        self::assertSame($expectedItemsAppliedTaxes, $actualItemsAppliedTaxes);
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

    /**
     * @return array<string, array{
     *     isTaxIncluded: bool,
     *     expectedCustomFees: array<string, CustomOrderFeeData>,
     *     expectedTaxAmount: float,
     *     expectedAppliedTaxes: array<string, AppliedTaxData>,
     *     expectedItemsAppliedTaxes: array{custom_fees: array<string, AppliedTaxData>},
     * }>
     */
    public static function collectsCustomFeeTaxTotalsExcludingDiscountsDataProvider(): array
    {
        return [
            'custom fee value excludes tax' => [
                'isTaxIncluded' => false,
                'expectedCustomFees' => [
                    'test_fee_0' => [
                        'code' => 'test_fee_0',
                        'title' => 'Test Fee',
                        'type' => FeeType::Fixed->value,
                        'percent' => null,
                        'show_percentage' => false,
                        'base_value' => 4.00,
                        'value' => 4.00,
                        'base_value_with_tax' => 4.30,
                        'value_with_tax' => 4.30,
                        'base_tax_amount' => 0.30,
                        'tax_amount' => 0.30,
                        'tax_rate' => 7.5,
                        'base_applied_taxes' => [
                            'US-AL-*-Rate-1' => [
                                'amount' => 0.30,
                                'percent' => 7.5,
                                'tax_rate_key' => 'US-AL-*-Rate-1',
                                'rates' => [
                                    'US-AL-*-Rate-1' => [
                                        'percent' => 7.5,
                                        'code' => 'US-AL-*-Rate-1',
                                        'title' => 'US-AL-*-Rate-1',
                                    ],
                                ],
                            ],
                        ],
                        'applied_taxes' => [
                            'US-AL-*-Rate-1' => [
                                'amount' => 0.30,
                                'percent' => 7.5,
                                'tax_rate_key' => 'US-AL-*-Rate-1',
                                'rates' => [
                                    'US-AL-*-Rate-1' => [
                                        'percent' => 7.5,
                                        'code' => 'US-AL-*-Rate-1',
                                        'title' => 'US-AL-*-Rate-1',
                                    ],
                                ],
                            ],
                        ],
                        'base_discount_tax_compensation' => 0.00,
                        'discount_tax_compensation' => 0.00,
                    ],
                    'test_fee_1' => [
                        'code' => 'test_fee_1',
                        'title' => 'Another Fee',
                        'type' => FeeType::Percent->value,
                        'percent' => 5.0,
                        'show_percentage' => true,
                        'base_value' => 0.50,
                        'value' => 0.50,
                        'base_value_with_tax' => 0.54,
                        'value_with_tax' => 0.54,
                        'base_tax_amount' => 0.04,
                        'tax_amount' => 0.04,
                        'tax_rate' => 7.5,
                        'base_applied_taxes' => [
                            'US-AL-*-Rate-1' => [
                                'amount' => 0.04,
                                'percent' => 7.5,
                                'tax_rate_key' => 'US-AL-*-Rate-1',
                                'rates' => [
                                    'US-AL-*-Rate-1' => [
                                        'percent' => 7.5,
                                        'code' => 'US-AL-*-Rate-1',
                                        'title' => 'US-AL-*-Rate-1',
                                    ],
                                ],
                            ],
                        ],
                        'applied_taxes' => [
                            'US-AL-*-Rate-1' => [
                                'amount' => 0.04,
                                'percent' => 7.5,
                                'tax_rate_key' => 'US-AL-*-Rate-1',
                                'rates' => [
                                    'US-AL-*-Rate-1' => [
                                        'percent' => 7.5,
                                        'code' => 'US-AL-*-Rate-1',
                                        'title' => 'US-AL-*-Rate-1',
                                    ],
                                ],
                            ],
                        ],
                        'base_discount_tax_compensation' => 0.00,
                        'discount_tax_compensation' => 0.00,
                    ],
                ],
                'expectedTaxAmount' => 0.34,
                'expectedAppliedTaxes' => [
                    'US-AL-*-Rate-1' => [
                        'base_amount' => 0.34,
                        'amount' => 0.34,
                        'percent' => 7.5,
                        'id' => 'US-AL-*-Rate-1',
                        'rates' => [
                            [
                                'percent' => 7.5,
                                'code' => 'US-AL-*-Rate-1',
                                'title' => 'US-AL-*-Rate-1',
                            ],
                        ],
                        'item_id' => null,
                        'associated_item_id' => null,
                        'item_type' => 'custom_fee',
                        'process' => 0,
                    ],
                ],
                'expectedItemsAppliedTaxes' => [
                    'custom_fees' => [
                        'test_fee_0' => [
                            'base_amount' => 0.30,
                            'amount' => 0.30,
                            'percent' => 7.5,
                            'id' => 'US-AL-*-Rate-1',
                            'rates' => [
                                [
                                    'percent' => 7.5,
                                    'code' => 'US-AL-*-Rate-1',
                                    'title' => 'US-AL-*-Rate-1',
                                ],
                            ],
                            'item_id' => null,
                            'associated_item_id' => null,
                            'item_type' => 'custom_fee',
                        ],
                        'test_fee_1' => [
                            'base_amount' => 0.04,
                            'amount' => 0.04,
                            'percent' => 7.5,
                            'id' => 'US-AL-*-Rate-1',
                            'rates' => [
                                [
                                    'percent' => 7.5,
                                    'code' => 'US-AL-*-Rate-1',
                                    'title' => 'US-AL-*-Rate-1',
                                ],
                            ],
                            'item_id' => null,
                            'associated_item_id' => null,
                            'item_type' => 'custom_fee',
                        ],
                    ],
                ],
            ],
            'custom fee value includes tax' => [
                'isTaxIncluded' => true,
                'expectedCustomFees' => [
                    'test_fee_0' => [
                        'code' => 'test_fee_0',
                        'title' => 'Test Fee',
                        'type' => FeeType::Fixed->value,
                        'percent' => null,
                        'show_percentage' => false,
                        'base_value' => 3.72,
                        'value' => 3.72,
                        'base_value_with_tax' => 4.00,
                        'value_with_tax' => 4.00,
                        'base_tax_amount' => 0.28,
                        'tax_amount' => 0.28,
                        'tax_rate' => 7.5,
                        'base_applied_taxes' => [
                            'US-AL-*-Rate-1' => [
                                'amount' => 0.28,
                                'percent' => 7.5,
                                'tax_rate_key' => 'US-AL-*-Rate-1',
                                'rates' => [
                                    'US-AL-*-Rate-1' => [
                                        'percent' => 7.5,
                                        'code' => 'US-AL-*-Rate-1',
                                        'title' => 'US-AL-*-Rate-1',
                                    ],
                                ],
                            ],
                        ],
                        'applied_taxes' => [
                            'US-AL-*-Rate-1' => [
                                'amount' => 0.28,
                                'percent' => 7.5,
                                'tax_rate_key' => 'US-AL-*-Rate-1',
                                'rates' => [
                                    'US-AL-*-Rate-1' => [
                                        'percent' => 7.5,
                                        'code' => 'US-AL-*-Rate-1',
                                        'title' => 'US-AL-*-Rate-1',
                                    ],
                                ],
                            ],
                        ],
                        'base_discount_tax_compensation' => 0.00,
                        'discount_tax_compensation' => 0.00,
                    ],
                    'test_fee_1' => [
                        'code' => 'test_fee_1',
                        'title' => 'Another Fee',
                        'type' => FeeType::Percent->value,
                        'percent' => 5.0,
                        'show_percentage' => true,
                        'base_value' => 0.47,
                        'value' => 0.47,
                        'base_value_with_tax' => 0.50,
                        'value_with_tax' => 0.50,
                        'base_tax_amount' => 0.03,
                        'tax_amount' => 0.03,
                        'tax_rate' => 7.5,
                        'base_applied_taxes' => [
                            'US-AL-*-Rate-1' => [
                                'amount' => 0.03,
                                'percent' => 7.5,
                                'tax_rate_key' => 'US-AL-*-Rate-1',
                                'rates' => [
                                    'US-AL-*-Rate-1' => [
                                        'percent' => 7.5,
                                        'code' => 'US-AL-*-Rate-1',
                                        'title' => 'US-AL-*-Rate-1',
                                    ],
                                ],
                            ],
                        ],
                        'applied_taxes' => [
                            'US-AL-*-Rate-1' => [
                                'amount' => 0.03,
                                'percent' => 7.5,
                                'tax_rate_key' => 'US-AL-*-Rate-1',
                                'rates' => [
                                    'US-AL-*-Rate-1' => [
                                        'percent' => 7.5,
                                        'code' => 'US-AL-*-Rate-1',
                                        'title' => 'US-AL-*-Rate-1',
                                    ],
                                ],
                            ],
                        ],
                        'base_discount_tax_compensation' => 0.00,
                        'discount_tax_compensation' => 0.00,
                    ],
                ],
                'expectedTaxAmount' => 0.31,
                'expectedAppliedTaxes' => [
                    'US-AL-*-Rate-1' => [
                        'base_amount' => 0.31,
                        'amount' => 0.31,
                        'percent' => 7.5,
                        'id' => 'US-AL-*-Rate-1',
                        'rates' => [
                            [
                                'percent' => 7.5,
                                'code' => 'US-AL-*-Rate-1',
                                'title' => 'US-AL-*-Rate-1',
                            ],
                        ],
                        'item_id' => null,
                        'associated_item_id' => null,
                        'item_type' => 'custom_fee',
                        'process' => 0,
                    ],
                ],
                'expectedItemsAppliedTaxes' => [
                    'custom_fees' => [
                        'test_fee_0' => [
                            'base_amount' => 0.28,
                            'amount' => 0.28,
                            'percent' => 7.5,
                            'id' => 'US-AL-*-Rate-1',
                            'rates' => [
                                [
                                    'percent' => 7.5,
                                    'code' => 'US-AL-*-Rate-1',
                                    'title' => 'US-AL-*-Rate-1',
                                ],
                            ],
                            'item_id' => null,
                            'associated_item_id' => null,
                            'item_type' => 'custom_fee',
                        ],
                        'test_fee_1' => [
                            'base_amount' => 0.03,
                            'amount' => 0.03,
                            'percent' => 7.5,
                            'id' => 'US-AL-*-Rate-1',
                            'rates' => [
                                [
                                    'percent' => 7.5,
                                    'code' => 'US-AL-*-Rate-1',
                                    'title' => 'US-AL-*-Rate-1',
                                ],
                            ],
                            'item_id' => null,
                            'associated_item_id' => null,
                            'item_type' => 'custom_fee',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, array{
     *     isTaxIncluded: bool,
     *     expectedCustomFees: array<string, CustomOrderFeeData>,
     *     expectedTaxAmount: float,
     *     expectedAppliedTaxes: array<string, AppliedTaxData>,
     *     expectedItemsAppliedTaxes: array{custom_fees: array<string, AppliedTaxData>},
     *     expectedDiscountTaxCompensationAmount: float,
     * }>
     */
    public static function collectsCustomFeeTaxTotalsIncludingDiscountsDataProvider(): array
    {
        return [
            'custom fee value excludes tax' => [
                'isTaxIncluded' => false,
                'expectedCustomFees' => [
                    'test_fee_0' => [
                        'code' => 'test_fee_0',
                        'title' => 'Test Fee',
                        'type' => FeeType::Fixed->value,
                        'percent' => null,
                        'show_percentage' => false,
                        'base_value' => 4.00,
                        'value' => 4.00,
                        'base_value_with_tax' => 4.30,
                        'value_with_tax' => 4.30,
                        'base_tax_amount' => 0.27,
                        'tax_amount' => 0.27,
                        'tax_rate' => 7.5,
                        'base_applied_taxes' => [
                            'US-AL-*-Rate-1' => [
                                'amount' => 0.27,
                                'percent' => 7.5,
                                'tax_rate_key' => 'US-AL-*-Rate-1',
                                'rates' => [
                                    'US-AL-*-Rate-1' => [
                                        'percent' => 7.5,
                                        'code' => 'US-AL-*-Rate-1',
                                        'title' => 'US-AL-*-Rate-1',
                                    ],
                                ],
                            ],
                        ],
                        'applied_taxes' => [
                            'US-AL-*-Rate-1' => [
                                'amount' => 0.27,
                                'percent' => 7.5,
                                'tax_rate_key' => 'US-AL-*-Rate-1',
                                'rates' => [
                                    'US-AL-*-Rate-1' => [
                                        'percent' => 7.5,
                                        'code' => 'US-AL-*-Rate-1',
                                        'title' => 'US-AL-*-Rate-1',
                                    ],
                                ],
                            ],
                        ],
                        'base_discount_amount' => 0.40,
                        'discount_amount' => 0.40,
                        'discount_rate' => 10.0,
                        'base_discount_tax_compensation' => 0.00,
                        'discount_tax_compensation' => 0.00,
                    ],
                    'test_fee_1' => [
                        'code' => 'test_fee_1',
                        'title' => 'Another Fee',
                        'type' => FeeType::Percent->value,
                        'percent' => 5.0,
                        'show_percentage' => true,
                        'base_value' => 0.50,
                        'value' => 0.50,
                        'base_value_with_tax' => 0.54,
                        'value_with_tax' => 0.54,
                        'base_tax_amount' => 0.03,
                        'tax_amount' => 0.03,
                        'tax_rate' => 7.5,
                        'base_applied_taxes' => [
                            'US-AL-*-Rate-1' => [
                                'amount' => 0.03,
                                'percent' => 7.5,
                                'tax_rate_key' => 'US-AL-*-Rate-1',
                                'rates' => [
                                    'US-AL-*-Rate-1' => [
                                        'percent' => 7.5,
                                        'code' => 'US-AL-*-Rate-1',
                                        'title' => 'US-AL-*-Rate-1',
                                    ],
                                ],
                            ],
                        ],
                        'applied_taxes' => [
                            'US-AL-*-Rate-1' => [
                                'amount' => 0.03,
                                'percent' => 7.5,
                                'tax_rate_key' => 'US-AL-*-Rate-1',
                                'rates' => [
                                    'US-AL-*-Rate-1' => [
                                        'percent' => 7.5,
                                        'code' => 'US-AL-*-Rate-1',
                                        'title' => 'US-AL-*-Rate-1',
                                    ],
                                ],
                            ],
                        ],
                        'base_discount_amount' => 0.05,
                        'discount_amount' => 0.05,
                        'discount_rate' => 10.0,
                        'base_discount_tax_compensation' => 0.00,
                        'discount_tax_compensation' => 0.00,
                    ],
                ],
                'expectedTaxAmount' => 0.30,
                'expectedAppliedTaxes' => [
                    'US-AL-*-Rate-1' => [
                        'base_amount' => 0.30,
                        'amount' => 0.30,
                        'percent' => 7.5,
                        'id' => 'US-AL-*-Rate-1',
                        'rates' => [
                            [
                                'percent' => 7.5,
                                'code' => 'US-AL-*-Rate-1',
                                'title' => 'US-AL-*-Rate-1',
                            ],
                        ],
                        'item_id' => null,
                        'associated_item_id' => null,
                        'item_type' => 'custom_fee',
                        'process' => 0,
                    ],
                ],
                'expectedItemsAppliedTaxes' => [
                    'custom_fees' => [
                        'test_fee_0' => [
                            'base_amount' => 0.27,
                            'amount' => 0.27,
                            'percent' => 7.5,
                            'id' => 'US-AL-*-Rate-1',
                            'rates' => [
                                [
                                    'percent' => 7.5,
                                    'code' => 'US-AL-*-Rate-1',
                                    'title' => 'US-AL-*-Rate-1',
                                ],
                            ],
                            'item_id' => null,
                            'associated_item_id' => null,
                            'item_type' => 'custom_fee',
                        ],
                        'test_fee_1' => [
                            'base_amount' => 0.03,
                            'amount' => 0.03,
                            'percent' => 7.5,
                            'id' => 'US-AL-*-Rate-1',
                            'rates' => [
                                [
                                    'percent' => 7.5,
                                    'code' => 'US-AL-*-Rate-1',
                                    'title' => 'US-AL-*-Rate-1',
                                ],
                            ],
                            'item_id' => null,
                            'associated_item_id' => null,
                            'item_type' => 'custom_fee',
                        ],
                    ],
                ],
                'expectedDiscountTaxCompensationAmount' => 0.00,
            ],
            'custom fee value includes tax' => [
                'isTaxIncluded' => true,
                'expectedCustomFees' => [
                    'test_fee_0' => [
                        'code' => 'test_fee_0',
                        'title' => 'Test Fee',
                        'type' => FeeType::Fixed->value,
                        'percent' => null,
                        'show_percentage' => false,
                        'base_value' => 3.72,
                        'value' => 3.72,
                        'base_value_with_tax' => 4.00,
                        'value_with_tax' => 4.00,
                        'base_tax_amount' => 0.25,
                        'tax_amount' => 0.25,
                        'tax_rate' => 7.5,
                        'base_applied_taxes' => [
                            'US-AL-*-Rate-1' => [
                                'amount' => 0.25,
                                'percent' => 7.5,
                                'tax_rate_key' => 'US-AL-*-Rate-1',
                                'rates' => [
                                    'US-AL-*-Rate-1' => [
                                        'percent' => 7.5,
                                        'code' => 'US-AL-*-Rate-1',
                                        'title' => 'US-AL-*-Rate-1',
                                    ],
                                ],
                            ],
                        ],
                        'applied_taxes' => [
                            'US-AL-*-Rate-1' => [
                                'amount' => 0.25,
                                'percent' => 7.5,
                                'tax_rate_key' => 'US-AL-*-Rate-1',
                                'rates' => [
                                    'US-AL-*-Rate-1' => [
                                        'percent' => 7.5,
                                        'code' => 'US-AL-*-Rate-1',
                                        'title' => 'US-AL-*-Rate-1',
                                    ],
                                ],
                            ],
                        ],
                        'base_discount_amount' => 0.37,
                        'discount_amount' => 0.37,
                        'discount_rate' => 10.0,
                        'base_discount_tax_compensation' => 0.03,
                        'discount_tax_compensation' => 0.03,
                    ],
                    'test_fee_1' => [
                        'code' => 'test_fee_1',
                        'title' => 'Another Fee',
                        'type' => FeeType::Percent->value,
                        'percent' => 5.0,
                        'show_percentage' => true,
                        'base_value' => 0.47,
                        'value' => 0.47,
                        'base_value_with_tax' => 0.50,
                        'value_with_tax' => 0.50,
                        'base_tax_amount' => 0.03,
                        'tax_amount' => 0.03,
                        'tax_rate' => 7.5,
                        'base_applied_taxes' => [
                            'US-AL-*-Rate-1' => [
                                'amount' => 0.03,
                                'percent' => 7.5,
                                'tax_rate_key' => 'US-AL-*-Rate-1',
                                'rates' => [
                                    'US-AL-*-Rate-1' => [
                                        'percent' => 7.5,
                                        'code' => 'US-AL-*-Rate-1',
                                        'title' => 'US-AL-*-Rate-1',
                                    ],
                                ],
                            ],
                        ],
                        'applied_taxes' => [
                            'US-AL-*-Rate-1' => [
                                'amount' => 0.03,
                                'percent' => 7.5,
                                'tax_rate_key' => 'US-AL-*-Rate-1',
                                'rates' => [
                                    'US-AL-*-Rate-1' => [
                                        'percent' => 7.5,
                                        'code' => 'US-AL-*-Rate-1',
                                        'title' => 'US-AL-*-Rate-1',
                                    ],
                                ],
                            ],
                        ],
                        'base_discount_amount' => 0.05,
                        'discount_amount' => 0.05,
                        'discount_rate' => 10.0,
                        'base_discount_tax_compensation' => 0.00,
                        'discount_tax_compensation' => 0.00,
                    ],
                ],
                'expectedTaxAmount' => 0.28,
                'expectedAppliedTaxes' => [
                    'US-AL-*-Rate-1' => [
                        'base_amount' => 0.28,
                        'amount' => 0.28,
                        'percent' => 7.5,
                        'id' => 'US-AL-*-Rate-1',
                        'rates' => [
                            [
                                'percent' => 7.5,
                                'code' => 'US-AL-*-Rate-1',
                                'title' => 'US-AL-*-Rate-1',
                            ],
                        ],
                        'item_id' => null,
                        'associated_item_id' => null,
                        'item_type' => 'custom_fee',
                        'process' => 0,
                    ],
                ],
                'expectedItemsAppliedTaxes' => [
                    'custom_fees' => [
                        'test_fee_0' => [
                            'base_amount' => 0.25,
                            'amount' => 0.25,
                            'percent' => 7.5,
                            'id' => 'US-AL-*-Rate-1',
                            'rates' => [
                                [
                                    'percent' => 7.5,
                                    'code' => 'US-AL-*-Rate-1',
                                    'title' => 'US-AL-*-Rate-1',
                                ],
                            ],
                            'item_id' => null,
                            'associated_item_id' => null,
                            'item_type' => 'custom_fee',
                        ],
                        'test_fee_1' => [
                            'base_amount' => 0.03,
                            'amount' => 0.03,
                            'percent' => 7.5,
                            'id' => 'US-AL-*-Rate-1',
                            'rates' => [
                                [
                                    'percent' => 7.5,
                                    'code' => 'US-AL-*-Rate-1',
                                    'title' => 'US-AL-*-Rate-1',
                                ],
                            ],
                            'item_id' => null,
                            'associated_item_id' => null,
                            'item_type' => 'custom_fee',
                        ],
                    ],
                ],
                'expectedDiscountTaxCompensationAmount' => 0.03,
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
                            'base_applied_taxes' => [
                                'US-AL-*-Rate-1' => $objectManager->create(
                                    AppliedTaxInterface::class,
                                    [
                                        'data' => [
                                            'base_amount' => $taxAmount,
                                            'amount' => $taxAmount,
                                            'percent' => 7.5,
                                            'id' => 'US-AL-*-Rate-1',
                                            'rates' => [
                                                'US-AL-*-Rate-1' => $objectManager->create(
                                                    AppliedTaxRateInterface::class,
                                                    [
                                                        'data' => [
                                                            'percent' => 7.5,
                                                            'code' => 'US-AL-*-Rate-1',
                                                            'title' => 'US-AL-*-Rate-1',
                                                        ],
                                                    ],
                                                ),
                                            ],
                                        ],
                                    ],
                                ),
                            ],
                            'applied_taxes' => [
                                'US-AL-*-Rate-1' => $objectManager->create(
                                    AppliedTaxInterface::class,
                                    [
                                        'data' => [
                                            'base_amount' => $taxAmount,
                                            'amount' => $taxAmount,
                                            'percent' => 7.5,
                                            'id' => 'US-AL-*-Rate-1',
                                            'rates' => [
                                                'US-AL-*-Rate-1' => $objectManager->create(
                                                    AppliedTaxRateInterface::class,
                                                    [
                                                        'data' => [
                                                            'percent' => 7.5,
                                                            'code' => 'US-AL-*-Rate-1',
                                                            'title' => 'US-AL-*-Rate-1',
                                                        ],
                                                    ],
                                                ),
                                            ],
                                        ],
                                    ],
                                ),
                            ],
                            'base_discount_tax_compensation' => 0.00,
                            'discount_tax_compensation' => 0.00,
                        ],
                    ],
                );
            },
            $customQuoteFeesRetriever->retrieveApplicableFees($quote),
        );

        $quote->getExtensionAttributes()?->setCustomFees($customFees);
    }

    /**
     * @param array<string, CustomOrderFeeInterface> $customFees
     * @return array<string, CustomOrderFeeData>
     */
    private function convertCustomFeesToArray(array $customFees): array
    {
        return array_map(
            static function (CustomOrderFeeInterface $customFee): array {
                /* We need to convert the custom fee to an array to prevent PHP from crashing while comparing it with
                   the expected data */
                $customFeeData = $customFee->__toArray();
                $customFeeData['type'] = $customFeeData['type']->value;
                $customFeeData['base_applied_taxes'] = array_map(
                    static function (AppliedTaxInterface $appliedTax): array {
                        /** @var AppliedTaxData $appliedTaxData */
                        $appliedTaxData = $appliedTax->getData() ?? [];

                        if ($appliedTaxData === []) {
                            return [];
                        }

                        $appliedTaxData['rates'] = array_map(
                            static fn(AppliedTaxRateInterface $appliedTaxRate): array
                                => $appliedTaxRate->getData() ?? [],
                            $appliedTaxData['rates'],
                        );

                        return $appliedTaxData;
                    },
                    $customFeeData['base_applied_taxes'],
                );
                $customFeeData['applied_taxes'] = array_map(
                    static function (AppliedTaxInterface $appliedTax): array {
                        /** @var AppliedTaxData $appliedTaxData */
                        $appliedTaxData = $appliedTax->getData() ?? [];

                        if ($appliedTaxData === []) {
                            return [];
                        }

                        $appliedTaxData['rates'] = array_map(
                            static fn(AppliedTaxRateInterface $appliedTaxRate): array
                                => $appliedTaxRate->getData() ?? [],
                            $appliedTaxData['rates'],
                        );

                        return $appliedTaxData;
                    },
                    $customFeeData['applied_taxes'],
                );

                return $customFeeData;
            },
            $customFees,
        );
    }
}
