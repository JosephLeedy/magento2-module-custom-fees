<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Service;

use JosephLeedy\CustomFees\Api\ConfigInterface;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFeeInterface;
use JosephLeedy\CustomFees\Model\FeeType;
use JosephLeedy\CustomFees\Model\Rule\Condition\CustomFee;
use JosephLeedy\CustomFees\Service\CustomFeeDiscountRulesApplier;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\SalesRule\Api\Data\RuleInterface;
use Magento\SalesRule\Api\RuleRepositoryInterface;
use Magento\SalesRule\Model\Converter\ToModel;
use Magento\SalesRule\Model\Rule;
use Magento\Store\Model\ScopeInterface as StoreScopeInterface;
use Magento\TestFramework\Fixture\Config as ConfigFixture;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

use function array_first;
use function array_walk;

final class CustomFeeDiscountRulesApplierTest extends TestCase
{
    /**
     * @dataProvider appliesDiscountsToCustomFeesDataProvider
     * @param array<string, string|array<string, float>> $expectedDiscountAmounts
     */
    #[ConfigFixture(
        ConfigInterface::CONFIG_PATH_CUSTOM_FEES,
        '{"_1727299833817_817":{"code":"test_fee_0","title":"Test Fee","type":"fixed","status":"1","value":"4.00","adva'
        . 'nced":"{\\"show_percentage\\":\\"0\\"}"},"_1727299843197_197":{"code":"test_fee_1","title":"Another Fee","ty'
        . 'pe":"percent","status":"1","value":"1.00","advanced":"{\\"show_percentage\\":\\"1\\"}"}}',
        StoreScopeInterface::SCOPE_STORE,
        'default',
    )]
    #[DataFixture('Magento/Checkout/_files/quote_with_address.php')]
    #[DataFixture('JosephLeedy_CustomFees::../test/Integration/_files/cart_rule_10_percent_off_custom_fees.php')]
    public function testAppliesDiscountRulesToCustomFees(string $simpleAction, array $expectedDiscountAmounts): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var Quote $quote */
        $quote = $objectManager->create(Quote::class);
        /** @var QuoteResource $quoteResource */
        $quoteResource = $objectManager->create(QuoteResource::class);
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
        /** @var CustomFeeDiscountRulesApplier $customFeesDiscountApplier */
        $customFeesDiscountApplier = $objectManager->create(CustomFeeDiscountRulesApplier::class);

        $quoteResource->load($quote, 'test_order_1', 'reserved_order_id');

        $quote->setItems($quote->getAllVisibleItems()); // Fix empty items array
        $quote->collectTotals();

        $customFees = $quote->getExtensionAttributes()?->getCustomFees() ?? [];

        array_walk(
            $customFees,
            static function (CustomOrderFeeInterface $customFee): void {
                $customFee->setBaseDiscountAmount(0.00);
                $customFee->setDiscountAmount(0.00);
                $customFee->setDiscountRate(0.00);
            },
        );

        if ($rule->getSimpleAction() !== $simpleAction) {
            $rule->setSimpleAction($simpleAction);

            if ($simpleAction === Rule::BY_FIXED_ACTION || $simpleAction === Rule::BUY_X_GET_Y_ACTION) {
                $rule->setDiscountAmount(1.00);
            }

            if ($simpleAction === Rule::BUY_X_GET_Y_ACTION) {
                $rule->setDiscountStep(1);
            }

            $rule->save();
        }

        $customFeesDiscountApplier->applyRules($quote->getShippingAddress(), [$rule]);

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
                        'base_value_with_tax' => 4.00,
                        'value_with_tax' => 4.00,
                        'base_tax_amount' => 0.00,
                        'tax_amount' => 0.00,
                        'tax_rate' => 0.00,
                    ] + $expectedDiscountAmounts['test_fee_0'],
                ],
            ),
            'test_fee_1' => $objectManager->create(
                CustomOrderFeeInterface::class,
                [
                    'data' => [
                        'code' => 'test_fee_1',
                        'title' => 'Another Fee',
                        'type' => FeeType::Percent,
                        'percent' => 1.00,
                        'show_percentage' => true,
                        'base_value' => 0.20,
                        'value' => 0.20,
                        'base_value_with_tax' => 0.20,
                        'value_with_tax' => 0.20,
                        'base_tax_amount' => 0.00,
                        'tax_amount' => 0.00,
                        'tax_rate' => 0.00,
                    ] + $expectedDiscountAmounts['test_fee_1'],
                ],
            ),
        ];
        $actualCustomFees = $quote->getExtensionAttributes()?->getCustomFees();
        $expectedDiscountDescription = [
            $rule->getRuleId() => $rule->getName(),
        ];
        $actualDiscountDescription = $quote->getShippingAddress()->getDiscountDescriptionArray();
        $expectedAppliedAddressRuleIds = $rule->getRuleId();
        $actualAppliedAddressRuleIds = $quote->getShippingAddress()->getAppliedRuleIds();
        $expectedAppliedQuoteRuleIds = $rule->getRuleId();
        $actualAppliedQuoteRuleIds = $quote->getAppliedRuleIds();

        self::assertEquals($expectedCustomFees, $actualCustomFees);
        self::assertEquals($expectedDiscountDescription, $actualDiscountDescription);
        self::assertEquals($expectedAppliedAddressRuleIds, $actualAppliedAddressRuleIds);
        self::assertEquals($expectedAppliedQuoteRuleIds, $actualAppliedQuoteRuleIds);

        if ($simpleAction === Rule::CART_FIXED_ACTION) {
            $expectedCartRules = [
                $rule->getRuleId() => 8.234323432343235,
            ];
            $actualCartRules = $quote->getShippingAddress()->getCartFixedRules();

            self::assertEquals($expectedCartRules, $actualCartRules);
        }
    }

    #[ConfigFixture(ConfigInterface::CONFIG_PATH_CUSTOM_FEES, '{}', StoreScopeInterface::SCOPE_STORE, 'default')]
    #[DataFixture('Magento/Checkout/_files/quote_with_address.php')]
    #[DataFixture('JosephLeedy_CustomFees::../test/Integration/_files/cart_rule_10_percent_off_custom_fees.php')]
    public function testDoesNotApplyDiscountRulesIfQuoteDoesNotHaveCustomFees(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var Quote $quote */
        $quote = $objectManager->create(Quote::class);
        /** @var QuoteResource $quoteResource */
        $quoteResource = $objectManager->create(QuoteResource::class);
        /** @var CustomFeeDiscountRulesApplier $customFeesDiscountApplier */
        $customFeesDiscountApplier = $objectManager->create(CustomFeeDiscountRulesApplier::class);

        $quoteResource->load($quote, 'test_order_1', 'reserved_order_id');

        $customFeesDiscountApplier->applyRules($quote->getShippingAddress(), []);

        $actualCustomFees = $quote->getExtensionAttributes()?->getCustomFees();
        $actualDiscountDescription = $quote->getShippingAddress()->getDiscountDescriptionArray();
        $actualAppliedAddressRuleIds = $quote->getShippingAddress()->getAppliedRuleIds();
        $actualAppliedQuoteRuleIds = $quote->getAppliedRuleIds();

        self::assertEmpty($actualCustomFees);
        self::assertEmpty($actualDiscountDescription);
        self::assertEmpty($actualAppliedAddressRuleIds);
        self::assertEmpty($actualAppliedQuoteRuleIds);
    }

    /**
     * @dataProvider doesNotApplyUnapplicableDiscountRulesToCustomFeesDataProvider
     */
    #[ConfigFixture(
        ConfigInterface::CONFIG_PATH_CUSTOM_FEES,
        '{"_1727299833817_817":{"code":"test_fee_0","title":"Test Fee","type":"fixed","status":"1","value":"4.00","adva'
        . 'nced":"{\\"show_percentage\\":\\"0\\"}"},"_1727299843197_197":{"code":"test_fee_1","title":"Another Fee","ty'
        . 'pe":"percent","status":"1","value":"1.00","advanced":"{\\"show_percentage\\":\\"1\\"}"}}',
        StoreScopeInterface::SCOPE_STORE,
        'default',
    )]
    #[DataFixture('Magento/Checkout/_files/quote_with_address.php')]
    #[DataFixture('JosephLeedy_CustomFees::../test/Integration/_files/cart_rule_10_percent_off_custom_fees.php')]
    public function testDoesNotApplyUnapplicableDiscountRulesToCustomFees(string $condition): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var Quote $quote */
        $quote = $objectManager->create(Quote::class);
        /** @var QuoteResource $quoteResource */
        $quoteResource = $objectManager->create(QuoteResource::class);
        $customFees = [
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
                        'base_value_with_tax' => 4.00,
                        'value_with_tax' => 4.00,
                        'base_tax_amount' => 0.00,
                        'tax_amount' => 0.00,
                        'tax_rate' => 0.00,
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
                        'percent' => 1.00,
                        'show_percentage' => true,
                        'base_value' => 0.20,
                        'value' => 0.20,
                        'base_value_with_tax' => 0.20,
                        'value_with_tax' => 0.20,
                        'base_tax_amount' => 0.00,
                        'tax_amount' => 0.00,
                        'tax_rate' => 0.00,
                    ],
                ],
            ),
        ];
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
        /** @var CustomFeeDiscountRulesApplier $customFeesDiscountApplier */
        $customFeesDiscountApplier = $objectManager->create(CustomFeeDiscountRulesApplier::class);

        $quoteResource->load($quote, 'test_order_1', 'reserved_order_id');

        $quote->getExtensionAttributes()?->setCustomFees($customFees);

        switch ($condition) {
            case 'does not apply to custom fees':
                $rule->setApplyToCustomFees('0');

                break;
            case 'is not valid for address':
                $rule->setIsValidForAddress($quote->getShippingAddress(), false);

                break;
            case 'is not valid for custom fee':
                $customFeeCode = 'test_fee_2';
                $operator = '==';
                /** @var CustomFee $customFeeCondition */
                $customFeeCondition = $objectManager->create(CustomFee::class);

                $customFeeCondition->setRule($rule);
                $customFeeCondition->setOperator($operator);
                $customFeeCondition->setAttribute('custom_fee');
                $customFeeCondition->setValue($customFeeCode);

                $rule->getActions()->setConditions([$customFeeCondition]);

                break;
        }

        $customFeesDiscountApplier->applyRules($quote->getShippingAddress(), [$rule]);

        $actualCustomFees = $quote->getExtensionAttributes()?->getCustomFees() ?? [];
        $actualDiscountDescription = $quote->getShippingAddress()->getDiscountDescriptionArray();
        $actualAppliedAddressRuleIds = $quote->getShippingAddress()->getAppliedRuleIds();
        $actualAppliedQuoteRuleIds = $quote->getAppliedRuleIds();

        array_walk(
            $actualCustomFees,
            static function (CustomOrderFeeInterface $actualCustomFee): void {
                self::assertSame(0.00, $actualCustomFee->getDiscountAmount());
            },
        );

        self::assertEmpty($actualDiscountDescription);
        self::assertEmpty($actualAppliedAddressRuleIds);
        self::assertEmpty($actualAppliedQuoteRuleIds);
    }

    /**
     * @return array<string, array<string, string|array<string, float>>>
     */
    public static function appliesDiscountsToCustomFeesDataProvider(): array
    {
        return [
            'by percentage of fee amount' => [
                'simpleAction' => Rule::BY_PERCENT_ACTION,
                'expectedDiscountAmounts' => [
                    'test_fee_0' => [
                        'base_discount_amount' => 0.40,
                        'discount_amount' => 0.40,
                        'discount_rate' => 10.00,
                    ],
                    'test_fee_1' => [
                        'base_discount_amount' => 0.02,
                        'discount_amount' => 0.02,
                        'discount_rate' => 10.00,
                    ],
                ],
            ],
            'by fixed discount amount' => [
                'simpleAction' => Rule::BY_FIXED_ACTION,
                'expectedDiscountAmounts' => [
                    'test_fee_0' => [
                        'base_discount_amount' => 1.00,
                        'discount_amount' => 1.00,
                        'discount_rate' => 0.00,
                    ],
                    'test_fee_1' => [
                        'base_discount_amount' => 0.20,
                        'discount_amount' => 0.20,
                        'discount_rate' => 0.00,
                    ],
                ],
            ],
            'by fixed discount amount for whole cart' => [
                'simpleAction' => Rule::CART_FIXED_ACTION,
                'expectedDiscountAmounts' => [
                    'test_fee_0' => [
                        'base_discount_amount' => 1.67,
                        'discount_amount' => 1.67,
                        'discount_rate' => 0.00,
                    ],
                    'test_fee_1' => [
                        'base_discount_amount' => 0.10,
                        'discount_amount' => 0.10,
                        'discount_rate' => 0.00,
                    ],
                ],
            ],
            'by buy X get Y free (discount amount is Y)' => [
                'simpleAction' => Rule::BUY_X_GET_Y_ACTION,
                'expectedDiscountAmounts' => [
                    'test_fee_0' => [
                        'base_discount_amount' => 2.00,
                        'discount_amount' => 2.00,
                        'discount_rate' => 0.00,
                    ],
                    'test_fee_1' => [
                        'base_discount_amount' => 0.10,
                        'discount_amount' => 0.10,
                        'discount_rate' => 0.00,
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function doesNotApplyUnapplicableDiscountRulesToCustomFeesDataProvider(): array
    {
        return [
            'if rule does not apply to custom fees' => [
                'condition' => 'does not apply to custom fees',
            ],
            'if rule is not valid for address' => [
                'condition' => 'is not valid for address',
            ],
            'if rule is not valid for custom fee' => [
                'condition' => 'is not valid for custom fee',
            ],
        ];
    }
}
