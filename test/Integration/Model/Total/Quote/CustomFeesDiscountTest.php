<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Model\Total\Quote;

use ColinODell\PsrTestLogger\TestLogger;
use JosephLeedy\CustomFees\Api\ConfigInterface;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFeeInterface;
use JosephLeedy\CustomFees\Model\FeeType;
use JosephLeedy\CustomFees\Model\Total\Quote\CustomFeesDiscount;
use JosephLeedy\CustomFees\Service\CustomQuoteFeesRetriever;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Api\Data\ShippingInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\SalesRule\Model\Validator;
use Magento\Store\Model\ScopeInterface as StoreScopeInterface;
use Magento\TestFramework\Fixture\Config as ConfigFixture;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Zend_Db_Select_Exception;

use function __;
use function array_filter;
use function array_keys;
use function array_map;
use function in_array;

use const ARRAY_FILTER_USE_KEY;

final class CustomFeesDiscountTest extends TestCase
{
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
    public function testCollectsCustomFeeDiscountTotals(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var Quote $quote */
        $quote = $objectManager->create(Quote::class);
        /** @var QuoteResource $quoteResource */
        $quoteResource = $objectManager->create(QuoteResource::class);

        $quoteResource->load($quote, 'test_order_1', 'reserved_order_id');

        $quote->setItems($quote->getAllVisibleItems()); // Fix empty items array
        $quote->collectTotals();

        $expectedAddressData = [
            'base_subtotal_with_discount' => 21.78,
            'subtotal_with_discount' => 21.78,
            'base_discount_amount' => -2.42,
            'discount_amount' => -2.42,
            'discount_description' => '10% Off on orders with two items',
            'base_test_fee_0_discount_amount' => -0.40,
            'test_fee_0_discount_amount' => -0.40,
            'base_test_fee_1_discount_amount' => -0.02,
            'test_fee_1_discount_amount' => -0.02,
        ];
        $actualAddressData = array_filter(
            $quote->getShippingAddress()->toArray(),
            static fn(string $key): bool => in_array($key, array_keys($expectedAddressData)),
            ARRAY_FILTER_USE_KEY,
        );
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
                        'base_discount_amount' => 0.40,
                        'discount_amount' => 0.40,
                        'discount_rate' => 10.00,
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
                        'percent' => 1.0,
                        'show_percentage' => true,
                        'base_value' => 0.20,
                        'value' => 0.20,
                        'base_value_with_tax' => 0.20,
                        'value_with_tax' => 0.20,
                        'base_tax_amount' => 0.00,
                        'tax_amount' => 0.00,
                        'tax_rate' => 0.00,
                        'base_discount_amount' => 0.02,
                        'discount_amount' => 0.02,
                        'discount_rate' => 10.00,
                    ],
                ],
            ),
        ];
        $actualCustomFees = $quote->getExtensionAttributes()->getCustomFees();

        self::assertEquals($expectedAddressData, $actualAddressData);
        self::assertEquals($expectedCustomFees, $actualCustomFees);
    }

    /**
     * @dataProvider doesNotCollectCustomFeeDiscountTotalsDataProvider
     */
    #[ConfigFixture(
        ConfigInterface::CONFIG_PATH_CUSTOM_FEES,
        '{"_1727299833817_817":{"code":"test_fee_0","title":"Test Fee","type":"fixed","status":"1","value":"4.00","adva'
        . 'nced":"{\\"show_percentage\\":\\"0\\"}"},"_1727299843197_197":{"code":"test_fee_1","title":"Another Fee","ty'
        . 'pe":"fixed","status":"1","value":"1.00","advanced":"{\\"show_percentage\\":\\"0\\"}"}}',
        StoreScopeInterface::SCOPE_STORE,
        'default',
    )]
    #[DataFixture('Magento/Checkout/_files/quote_with_address.php')]
    public function testDoesNotCollectCustomFeeDiscountTotals(bool $noShippingAssignmentItems, bool $noCustomFees): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var Quote $quote */
        $quote = $objectManager->create(Quote::class);
        /** @var QuoteResource $quoteResource */
        $quoteResource = $objectManager->create(QuoteResource::class);
        $expectedBaseSubtotalWithDiscount = 20.00;
        $expectedSubtotalWithDiscount = 20.00;

        $quoteResource->load($quote, 'test_order_1', 'reserved_order_id');

        if ($noShippingAssignmentItems) {
            $quote->getShippingAddress()->setData('cached_items_all', []);

            $expectedBaseSubtotalWithDiscount = 0.00;
            $expectedSubtotalWithDiscount = 0.00;
        }

        if ($noCustomFees) {
            $configWriter = $objectManager->create(WriterInterface::class);

            $configWriter->delete(ConfigInterface::CONFIG_PATH_CUSTOM_FEES);

            $objectManager->get(ReinitableConfigInterface::class)->reinit();
        }

        $quote->collectTotals();

        self::assertSame(
            $expectedBaseSubtotalWithDiscount,
            (float) $quote->getShippingAddress()->getData('base_subtotal_with_discount'),
        );
        self::assertSame(
            $expectedSubtotalWithDiscount,
            (float) $quote->getShippingAddress()->getData('subtotal_with_discount'),
        );
        self::assertSame(0.00, (float) $quote->getShippingAddress()->getData('base_discount_amount'));
        self::assertSame(0.00, (float) $quote->getShippingAddress()->getData('discount_amount'));
        self::assertEmpty($quote->getShippingAddress()->getDiscountDescription());
        self::assertNull($quote->getShippingAddress()->getData('base_test_fee_0_discount_amount'));
        self::assertNull($quote->getShippingAddress()->getData('test_fee_0_discount_amount'));
        self::assertNull($quote->getShippingAddress()->getData('base_test_fee_1_discount_amount'));
        self::assertNull($quote->getShippingAddress()->getData('test_fee_1_discount_amount'));
    }

    #[ConfigFixture(
        ConfigInterface::CONFIG_PATH_CUSTOM_FEES,
        '{"_1727299833817_817":{"code":"test_fee_0","title":"Test Fee","type":"fixed","status":"1","value":"4.00","adva'
        . 'nced":"{\\"show_percentage\\":\\"0\\"}"},"_1727299843197_197":{"code":"test_fee_1","title":"Another Fee","ty'
        . 'pe":"fixed","status":"1","value":"1.00","advanced":"{\\"show_percentage\\":\\"0\\"}"}}',
        StoreScopeInterface::SCOPE_STORE,
        'default',
    )]
    #[DataFixture('Magento/Checkout/_files/quote_with_address.php')]
    public function testLogsErrorIfDiscountRulesCannotBeRetrievedDuringCollection(): void
    {
        $ruleValidatorStub = $this->createStub(Validator::class);
        $testLogger = new TestLogger();
        $objectManager = Bootstrap::getObjectManager();
        /** @var Quote $quote */
        $quote = $objectManager->create(Quote::class);
        /** @var QuoteResource $quoteResource */
        $quoteResource = $objectManager->create(QuoteResource::class);
        $dbSelectException = new Zend_Db_Select_Exception();
        /** @var CustomQuoteFeesRetriever $customQuoteFeesRetriever */
        $customQuoteFeesRetriever = $objectManager->create(CustomQuoteFeesRetriever::class);
        /** @var ShippingInterface $shipping */
        $shipping = $objectManager->create(ShippingInterface::class);
        /** @var ShippingAssignmentInterface $shippingAssignment */
        $shippingAssignment = $objectManager->create(ShippingAssignmentInterface::class);
        /** @var Total $total */
        $total = $objectManager->create(Total::class);
        /** @var CustomFeesDiscount $customFeesDiscountTotalCollector */
        $customFeeDiscountTotalCollector = $objectManager->create(
            CustomFeesDiscount::class,
            [
                'validator' => $ruleValidatorStub,
                'logger' => $testLogger,
            ],
        );

        $ruleValidatorStub->method('reset')->willReturnSelf();
        $ruleValidatorStub->method('getRules')->willThrowException($dbSelectException);

        $quoteResource->load($quote, 'test_order_1', 'reserved_order_id');

        /** @var CustomOrderFeeInterface[] $customFees */
        $customFees = array_map(
            static fn(array $customFeeData): CustomOrderFeeInterface => $objectManager->create(
                CustomOrderFeeInterface::class,
                [
                    'data' => [
                        'code' => $customFeeData['code'],
                        'title' => $customFeeData['title'],
                        'type' => $customFeeData['type'],
                        'percent' => null,
                        'show_percentage' => $customFeeData['advanced']['show_percentage'],
                        'base_value' => $customFeeData['value'],
                        'value' => $customFeeData['value'],
                        'base_value_with_tax' => $customFeeData['value'],
                        'value_with_tax' => $customFeeData['value'],
                        'base_tax_amount' => 0.00,
                        'tax_amount' => 0.00,
                        'tax_rate' => 0.00,
                    ],
                ],
            ),
            $customQuoteFeesRetriever->retrieveApplicableFees($quote),
        );

        $quote->getExtensionAttributes()?->setCustomFees($customFees);

        $shipping->setAddress($quote->getShippingAddress());

        $shippingAssignment->setItems($quote->getAllVisibleItems());
        $shippingAssignment->setShipping($shipping);

        $customFeeDiscountTotalCollector->collect($quote, $shippingAssignment, $total);

        self::assertTrue(
            $testLogger->hasRecord(
                [
                    'message' => 'Could not retrieve sales rules to apply to custom fees.',
                    'context' => [
                        'exception' => $dbSelectException,
                    ],
                ],
                LogLevel::CRITICAL,
            ),
        );
    }

    public function testFetchesCustomFeeDiscountTotal(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var Quote $quote */
        $quote = $objectManager->create(Quote::class);
        /** @var Total $total */
        $total = $objectManager->create(Total::class);
        /** @var CustomFeesDiscount $customFeesDiscountTotalCollector */
        $customFeesDiscountTotalCollector = $objectManager->create(CustomFeesDiscount::class);

        $total->setDiscountAmount(-2.42);
        $total->setDiscountDescription('save');

        $expectedTotal = [
            'code' => 'discount',
            'title' => __('Discount (%1)', 'save'),
            'value' => -2.42,
        ];
        $actualTotal = $customFeesDiscountTotalCollector->fetch($quote, $total);

        self::assertEquals($expectedTotal, $actualTotal);
    }

    public function testDoesNotFetchCustomFeeDiscountTotalIfDiscountAmountIsZero(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var Quote $quote */
        $quote = $objectManager->create(Quote::class);
        /** @var Total $total */
        $total = $objectManager->create(Total::class);
        /** @var CustomFeesDiscount $customFeesDiscountTotalCollector */
        $customFeesDiscountTotalCollector = $objectManager->create(CustomFeesDiscount::class);

        $total->setDiscountAmount(0.00);

        $actualTotal = $customFeesDiscountTotalCollector->fetch($quote, $total);

        self::assertEmpty($actualTotal);
    }

    /**
     * @return array<string, array<string, bool>>
     */
    public static function doesNotCollectCustomFeeDiscountTotalsDataProvider(): array
    {
        return [
            'no shipping assignment items' => [
                'noShippingAssignmentItems' => true,
                'noCustomFees' => false,
            ],
            'no custom fees' => [
                'noShippingAssignmentItems' => false,
                'noCustomFees' => true,
            ],
            'no sales rules' => [
                'noShippingAssignmentItems' => false,
                'noCustomFees' => false,
            ],
        ];
    }
}
