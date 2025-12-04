<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Model\Total\Quote;

use JosephLeedy\CustomFees\Api\Data\CustomOrderFeeInterface;
use JosephLeedy\CustomFees\Model\FeeType;
use JosephLeedy\CustomFees\Model\Total\Quote\CustomFees;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

use function __;

final class CustomFeesTest extends TestCase
{
    /**
     * @phpcs:ignore Generic.Files.LineLength.TooLong
     * @magentoConfigFixture current_store sales/custom_order_fees/custom_fees [{"code":"test_fee_0","title":"Test Fee","type":"fixed","value":"4.00","status":"1","advanced":"{\"show_percentage\":\"0\"}"},{"code":"test_fee_1","title":"Another Fee","type":"percent","value":"5","status":"1","advanced": "{\"show_percentage\":\"1\"}"}]
     * @magentoDataFixture Magento/Checkout/_files/quote_with_address.php
     */
    public function testCollectsCustomFeesTotals(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var Quote $quote */
        $quote = $objectManager->create(Quote::class);
        /** @var QuoteResource $quoteResource */
        $quoteResource = $objectManager->create(QuoteResource::class);

        $quoteResource->load($quote, 'test_order_1', 'reserved_order_id');

        $quote->collectTotals();

        $collectedTotals = $quote->getTotals();

        self::assertArrayHasKey('test_fee_0', $collectedTotals);
        self::assertArrayHasKey('test_fee_1', $collectedTotals);
        self::assertEquals(
            [
                'code' => 'test_fee_0',
                'title' => __('Test Fee'),
                'value' => 4.00,
                'tax_details' => [
                    'value_with_tax' => 4.00,
                    'tax_amount' => 0.00,
                    'tax_rate' => 0.00,
                ],
            ],
            $collectedTotals['test_fee_0']->getData(),
        );
        self::assertEquals(
            [
                'code' => 'test_fee_1',
                'title' => __('Another Fee (5%)'),
                'value' => 1.00,
                'tax_details' => [
                    'value_with_tax' => 1.00,
                    'tax_amount' => 0.00,
                    'tax_rate' => 0.00,
                ],
            ],
            $collectedTotals['test_fee_1']->getData(),
        );
        self::assertNotNull($quote->getExtensionAttributes()?->getCustomFees());
        self::assertEquals(
            [
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
                            'percent' => 5,
                            'show_percentage' => true,
                            'base_value' => 1.00,
                            'value' => 1.00,
                            'base_value_with_tax' => 1.00,
                            'value_with_tax' => 1.00,
                            'base_tax_amount' => 0.00,
                            'tax_amount' => 0.00,
                            'tax_rate' => 0.00,
                            'base_discount_amount' => 0.00,
                            'discount_amount' => 0.00,
                            'discount_rate' => 0.00,
                        ],
                    ],
                ),
            ],
            $quote->getExtensionAttributes()->getCustomFees(),
        );
    }

    /**
     * phpcs:ignore Generic.Files.LineLength.TooLong
     * @magentoConfigFixture current_store sales/custom_order_fees/custom_fees [{"code":"test_fee_0","title":"Test Fee","type":"fixed","value":"4.00","status":"1","advanced":"{\"show_percentage\":\"0\"}"},{"code":"test_fee_1","title":"Another Fee","type":"percent","value":"5","status":"1","advanced": "{\"show_percentage\":\"1\"}"}]
     * @magentoConfigFixture current_store tax/classes/custom_fee_tax_class 2
     * @magentoConfigFixture current_store tax/calculation/custom_fees_include_tax 1
     * @magentoConfigFixture current_store shipping/origin/country_id US
     * @magentoConfigFixture current_store shipping/origin/region_id 1
     * @magentoConfigFixture current_store shipping/origin/postcode 75477
     * @magentoDataFixture Magento/Tax/_files/tax_rule_region_1_al.php
     * @magentoDataFixture Magento/Checkout/_files/quote_with_taxable_product_and_customer.php
     */
    public function testCollectsCustomFeesTotalsWithTax(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var Quote $quote */
        $quote = $objectManager->create(Quote::class);
        /** @var QuoteResource $quoteResource */
        $quoteResource = $objectManager->create(QuoteResource::class);

        $quoteResource->load($quote, 'test_order_with_taxable_product', 'reserved_order_id');

        $quote->collectTotals();

        $collectedTotals = $quote->getTotals();

        self::assertEquals(
            [
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
                            'percent' => 5,
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
            ],
            $quote->getExtensionAttributes()->getCustomFees(),
        );
        self::assertSame(1.06, $collectedTotals['tax']->getValue());
    }

    /**
     * @phpcs:ignore Generic.Files.LineLength.TooLong
     * @magentoConfigFixture current_store sales/custom_order_fees/custom_fees [{"code":"test_fee_0","title":"Test Fee","type":"fixed","value":"4.00","status":"1","advanced":"{\"show_percentage\":\"0\"}"},{"code":"test_fee_1","title":"Another Fee","type":"percent","value":"5","status":"1","advanced":"{\"show_percentage\":\"1\"}"}]
     * @magentoDataFixture Magento/Checkout/_files/quote_with_address.php
     */
    public function testFetchesCustomFeesTotals(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var Quote $quote */
        $quote = $objectManager->create(Quote::class);
        /** @var QuoteResource $quoteResource */
        $quoteResource = $objectManager->create(QuoteResource::class);
        /** @var Total $total */
        $total = $objectManager->create(Total::class);
        /** @var CustomFees $customFeesTotalCollector */
        $customFeesTotalCollector = $objectManager->create(CustomFees::class);

        $quoteResource->load($quote, 'test_order_1', 'reserved_order_id');

        $total->setBaseSubtotal(20.00);

        $expectedCustomFees = [
            [
                'code' => 'test_fee_0',
                'title' => __('Test Fee'),
                'value' => 4.00,
                'tax_details' => [
                    'value_with_tax' => 4.00,
                    'tax_amount' => 0.00,
                    'tax_rate' => 0.00,
                ],
            ],
            [
                'code' => 'test_fee_1',
                'title' => __('Another Fee (5%)'),
                'value' => 1.00,
                'tax_details' => [
                    'value_with_tax' => 1.00,
                    'tax_amount' => 0.00,
                    'tax_rate' => 0.00,
                ],
            ],
        ];
        $actualCustomFees = $customFeesTotalCollector->fetch($quote, $total);

        self::assertEquals($expectedCustomFees, $actualCustomFees);
    }
}
