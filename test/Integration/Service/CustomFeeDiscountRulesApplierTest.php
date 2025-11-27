<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Service;

use JosephLeedy\CustomFees\Service\CustomFeeDiscountRulesApplier;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\SalesRule\Api\Data\DiscountDataInterface;
use Magento\SalesRule\Api\Data\RuleDiscountInterface;
use Magento\SalesRule\Api\Data\RuleInterface;
use Magento\SalesRule\Api\RuleRepositoryInterface;
use Magento\SalesRule\Model\Converter\ToModel;
use Magento\SalesRule\Model\Rule;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

use function array_first;

final class CustomFeeDiscountRulesApplierTest extends TestCase
{
    #[DataFixture('Magento/Checkout/_files/quote_with_address.php')]
    #[DataFixture('JosephLeedy_CustomFees::../test/Integration/_files/cart_rule_10_percent_off_custom_fees.php')]
    public function testAddsCustomFeeDiscountDescription(): void
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
        /** @var CustomFeeDiscountRulesApplier $customFeeDiscountRulesApplier */
        $customFeeDiscountRulesApplier = $objectManager->create(CustomFeeDiscountRulesApplier::class);
        /** @var DiscountDataInterface $discountData */
        $discountData = $objectManager->create(
            DiscountDataInterface::class,
            [
                'data' => [
                    'base_amount' => 1.00,
                    'amount' => 1.00,
                    'applied_to' => 'CUSTOM_FEE',
                ],
            ],
        );
        /** @var RuleDiscountInterface $discount */
        $discount = $objectManager->create(
            RuleDiscountInterface::class,
            [
                'data' => [
                    'discount' => $discountData,
                    'rule' => '',
                    'rule_id' => $rule->getRuleId(),
                ],
            ],
        );

        $quoteResource->load($quote, 'test_order_1', 'reserved_order_id');

        $customFeeDiscountRulesApplier->addCustomFeeDiscountDescription(
            $quote->getShippingAddress(),
            $rule,
            [
                'base_amount' => 1.00,
                'amount' => 1.00,
            ],
            [],
        );

        $expectedDiscounts = [
            $discount,
        ];
        $actualDiscounts = $quote->getShippingAddress()->getExtensionAttributes()?->getDiscounts();

        self::assertEquals($expectedDiscounts, $actualDiscounts);
    }
}
