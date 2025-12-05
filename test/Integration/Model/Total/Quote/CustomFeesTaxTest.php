<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Model\Total\Quote;

use JosephLeedy\CustomFees\Api\Data\CustomOrderFeeInterface;
use JosephLeedy\CustomFees\Model\Config;
use JosephLeedy\CustomFees\Model\FeeType;
use JosephLeedy\CustomFees\Model\Total\Quote\CustomFeesTax;
use JosephLeedy\CustomFees\Service\CustomQuoteFeesRetriever;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\Store\Model\ScopeInterface as StoreScopeInterface;
use Magento\TestFramework\Fixture\Config as ConfigFixture;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

use function array_map;
use function round;

final class CustomFeesTaxTest extends TestCase
{
    #[ConfigFixture(
        Config::CONFIG_PATH_CUSTOM_FEES,
        '{"_1727299833817_817":{"code":"test_fee_0","title":"Test Fee","type":"fixed","status":"1","value":"4.00","adva'
        . 'nced":"{\\"show_percentage\\":\\"0\\"}"},"_1727299843197_197":{"code":"test_fee_1","title":"Another Fee","ty'
        . 'pe":"percent","status":"1","value":"5.00","advanced":"{\\"show_percentage\\":\\"1\\"}"}}',
        StoreScopeInterface::SCOPE_STORE,
        'default',
    )]
    #[ConfigFixture('tax/classes/custom_fee_tax_class', '2', StoreScopeInterface::SCOPE_STORE, 'default')]
    #[ConfigFixture('tax/calculation/custom_fees_include_tax', '1', StoreScopeInterface::SCOPE_STORE, 'default')]
    #[ConfigFixture('shipping/origin/country_id', 'US', StoreScopeInterface::SCOPE_STORE, 'default')]
    #[ConfigFixture('shipping/origin/region_id', '1', StoreScopeInterface::SCOPE_STORE, 'default')]
    #[ConfigFixture('shipping/origin/postcode', '75477', StoreScopeInterface::SCOPE_STORE, 'default')]
    #[DataFixture('Magento/Tax/_files/tax_rule_region_1_al.php')]
    #[DataFixture('Magento/Checkout/_files/quote_with_taxable_product_and_customer.php')]
    public function testCollectsCustomFeeTaxTotals(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var Quote $quote */
        $quote = $objectManager->create(Quote::class);
        /** @var QuoteResource $quoteResource */
        $quoteResource = $objectManager->create(QuoteResource::class);

        $quoteResource->load($quote, 'test_order_with_taxable_product', 'reserved_order_id');

        $quote->collectTotals();

        $collectedTotals = $quote->getTotals();

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
                        'base_value_with_tax' => 4.00,
                        'value_with_tax' => 4.00,
                        'base_tax_amount' => 0.28,
                        'tax_amount' => 0.28,
                        'tax_rate' => 7.5,
                        'base_discount_amount' => 0.00,
                        'discount_amount' => 0.00,
                        'discount_rate' => 0.00,
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
                        'base_value' => 0.47,
                        'value' => 0.47,
                        'base_value_with_tax' => 0.50,
                        'value_with_tax' => 0.50,
                        'base_tax_amount' => 0.03,
                        'tax_amount' => 0.03,
                        'tax_rate' => 7.5,
                        'base_discount_amount' => 0.00,
                        'discount_amount' => 0.00,
                        'discount_rate' => 0.00,
                    ],
                ],
            ),
        ];
        $actualCustomFees = $quote->getExtensionAttributes()->getCustomFees();
        $expectedTaxAmount = 1.06;
        $actualTaxAmount = (float) $collectedTotals['tax']->getValue();

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
        /** @var CustomQuoteFeesRetriever $customQuoteFeesRetriever */
        $customQuoteFeesRetriever = $objectManager->create(CustomQuoteFeesRetriever::class);
        /** @var Total $total */
        $total = $objectManager->create(Total::class);
        /** @var CustomFeesTax $customFeesTaxTotalCollector */
        $customFeesTaxTotalCollector = $objectManager->create(CustomFeesTax::class);

        $quoteResource->load($quote, 'test_order_1', 'reserved_order_id');

        /** @var array<string, CustomOrderFeeInterface> $customFees */
        $customFees = array_map(
            static function (array $customFeeData) use ($objectManager): CustomOrderFeeInterface {
                $isPercent = FeeType::Percent->equals($customFeeData['type']);
                $value = $isPercent
                    ? round(20.00 * ((float) $customFeeData['value'] / 100), 2)
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
}
