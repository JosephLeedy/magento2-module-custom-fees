<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Service;

use JosephLeedy\CustomFees\Api\ConfigInterface;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFeeInterface;
use JosephLeedy\CustomFees\Model\FeeType;
use JosephLeedy\CustomFees\Service\CustomFeesDiscountApplier;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\SalesRule\Api\Data\RuleInterface;
use Magento\SalesRule\Api\RuleRepositoryInterface;
use Magento\SalesRule\Model\Rule;
use Magento\Store\Model\ScopeInterface as StoreScopeInterface;
use Magento\TestFramework\Fixture\Config as ConfigFixture;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

use function array_first;

final class CustomFeesDiscountApplierTest extends TestCase
{
    /**
     * @dataProvider appliesDiscountsToCustomFeesDataProvider
     */
    #[ConfigFixture(
        ConfigInterface::CONFIG_PATH_CUSTOM_FEES,
        '{"_1727299833817_817":{"code":"test_fee_0","title":"Test Fee","type":"fixed","status":"1","value":"4.00",'
        . '"advanced":"{\\"show_percentage\\":\\"0\\"}"},"_1727299843197_197":{"code":"test_fee_1","title":'
        . '"Another Fee","type":"percent","status":"1","value":"1.00","advanced":"{\\"show_percentage\\":\\"1\\"}"}}',
        StoreScopeInterface::SCOPE_STORE,
        'default',
    )]
    #[DataFixture('Magento/Checkout/_files/quote_with_address.php')]
    #[DataFixture('JosephLeedy_CustomFees::../test/Integration/_files/cart_rule_10_percent_off_custom_fees.php')]
    public function testAppliesDiscountsToCustomFees(string $simpleAction, array $expectedDiscountAmounts): void
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
        /** @var RuleInterface $rule */
        $rule = array_first($ruleRepository->getList($searchCriteria)->getItems());
        /** @var CustomFeesDiscountApplier $customFeesDiscountApplier */
        $customFeesDiscountApplier = $objectManager->create(CustomFeesDiscountApplier::class);

        $quoteResource->load($quote, 'test_order_1', 'reserved_order_id');

        if ($rule->getSimpleAction() !== $simpleAction) {
            $rule->setSimpleAction($simpleAction);

            $ruleRepository->save($rule);
        }

        $customFeesDiscountApplier->applyCustomFeeDiscounts($quote->getShippingAddress());

        $expectedAppliedAddressRuleIds = [
            $rule->getRuleId(),
        ];
        $actualAppliedAddressRuleIds = $quote->getShippingAddress()->getAppliedRuleIds();
        $expectedAppliedQuoteRuleIds = [
            $rule->getRuleId(),
        ];
        $actualAppliedQuoteRuleIds = $quote->getAppliedRuleIds();
        $expectedCustomFees = [
            'test_fee_0' => $objectManager->create(
                CustomOrderFeeInterface::class,
                [
                    'data' => [
                        'code' => 'test_fee_0',
                        'title' => 'Test Fee',
                        'type' => FeeType::Fixed,
                        'percent' => 0.00,
                        'show_percentage' => false,
                        'base_value' => 4.00,
                        'value' => 4.00,
                        'base_value_with_tax' => 4.00,
                        'value_with_tax' => 4.00,
                        'base_tax_amount' => 0.00,
                        'tax_amount' => 0.00,
                        'tax_rate' => 0.00,
                        'base_discount_amount' => 0.40,
                        'discount_amount' => 0.40,
                        'discount_rate' => 10.00,
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
                        'base_value' => 0.40,
                        'value' => 0.40,
                        'base_value_with_tax' => 0.40,
                        'value_with_tax' => 0.40,
                        'base_tax_amount' => 0.00,
                        'tax_amount' => 0.00,
                        'tax_rate' => 0.00,
                    ] + $expectedDiscountAmounts['test_fee_1'],
                ],
            ),
        ];
        $actualCustomFees = $quote->getExtensionAttributes()?->getCustomFees();

        self::assertEquals($expectedAppliedAddressRuleIds, $actualAppliedAddressRuleIds);
        self::assertEquals($expectedAppliedQuoteRuleIds, $actualAppliedQuoteRuleIds);
        self::assertEquals($expectedCustomFees, $actualCustomFees);
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
                        'base_discount_amount' => 0.04,
                        'discount_amount' => 0.04,
                        'discount_rate' => 10.00,
                    ],
                ],
            ],
            'by fixed discount amount' => [
                'simpleAction' => Rule::BY_FIXED_ACTION,
                // TODO: Determine the correct discount amounts for this case
                'expectedDiscountAmounts' => [
                    'test_fee_0' => [
                        'base_discount_amount' => 0.40,
                        'discount_amount' => 0.40,
                        'discount_rate' => 10.00,
                    ],
                    'test_fee_1' => [
                        'base_discount_amount' => 0.04,
                        'discount_amount' => 0.04,
                        'discount_rate' => 10.00,
                    ],
                ],
            ],
            'by fixed discount amount for whole cart' => [
                'simpleAction' => Rule::CART_FIXED_ACTION,
                // TODO: Determine the correct discount amounts for this case
                'expectedDiscountAmounts' => [
                    'test_fee_0' => [
                        'base_discount_amount' => 0.40,
                        'discount_amount' => 0.40,
                        'discount_rate' => 10.00,
                    ],
                    'test_fee_1' => [
                        'base_discount_amount' => 0.04,
                        'discount_amount' => 0.04,
                        'discount_rate' => 10.00,
                    ],
                ],
            ],
            'buy X get Y free (discount amount is Y)' => [
                'simpleAction' => Rule::BUY_X_GET_Y_ACTION,
                // TODO: Determine the correct discount amounts for this case
                'expectedDiscountAmounts' => [
                    'test_fee_0' => [
                        'base_discount_amount' => 0.40,
                        'discount_amount' => 0.40,
                        'discount_rate' => 10.00,
                    ],
                    'test_fee_1' => [
                        'base_discount_amount' => 0.04,
                        'discount_amount' => 0.04,
                        'discount_rate' => 10.00,
                    ],
                ],
            ],
        ];
    }
}
