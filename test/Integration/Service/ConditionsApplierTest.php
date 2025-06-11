<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Service;

use JosephLeedy\CustomFees\Model\Rule\Condition\Combine;
use JosephLeedy\CustomFees\Model\Rule\Condition\Product;
use JosephLeedy\CustomFees\Model\Rule\Condition\QuoteAddress;
use JosephLeedy\CustomFees\Service\ConditionsApplier;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

final class ConditionsApplierTest extends TestCase
{
    /**
     * @dataProvider appliesConditionsSuccessfullyDataProvider
     * @param array<int, array{type: class-string, attribute: string, operator: string, value: string}> $conditions
     */
    #[DataFixture('Magento/Checkout/_files/quote_with_address_and_shipping_method_saved.php')]
    public function testAppliesConditionsSuccessfully(string $aggregator, array $conditions): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var QuoteResource $quoteResource */
        $quoteResource = $objectManager->create(QuoteResource::class);
        /** @var Quote $quote */
        $quote = $objectManager->create(Quote::class);
        $aggregatedConditions = [
            'type' => Combine::class,
            'aggregator' => $aggregator,
            'value' => '1',
            'conditions' => $conditions,
        ];
        /** @var ConditionsApplier $conditionsApplier */
        $conditionsApplier = $objectManager->create(ConditionsApplier::class);

        $quoteResource->load($quote, 'test_order_1', 'reserved_order_id');

        $isApplicable = $conditionsApplier->isApplicable($quote, 'test_fee_0', $aggregatedConditions);

        self::assertTrue($isApplicable);
    }

    /**
     * @return array<
     *     string,
     *     array{
     *         aggregator: 'all'|'any',
     *         conditions: array<
     *             int,
     *             array{
     *                 type: class-string,
     *                 attribute: string,
     *                 operator: string,
     *                 value: string
     *             }
     *         >
     *     }
     * >
     */
    public static function appliesConditionsSuccessfullyDataProvider(): array
    {
        return [
            'only address condition' => [
                'aggregator' => 'all',
                'conditions' => [
                    [
                        'type' => QuoteAddress::class,
                        'operator' => '==',
                        'value' => 'flatrate_flatrate',
                        'attribute' => 'shipping_method',
                    ],
                ],
            ],
            'only product condition' => [
                'aggregator' => 'all',
                'conditions' => [
                    [
                        'type' => Product::class,
                        'operator' => '==',
                        'value' => 'simple',
                        'attribute' => 'sku',
                    ],
                ],
            ],
            'address or product condition' => [
                'aggregator' => 'any',
                'conditions' => [
                    [
                        'type' => QuoteAddress::class,
                        'operator' => '==',
                        'value' => 'flatrate_flatrate',
                        'attribute' => 'shipping_method',
                    ],
                    [
                        'type' => Product::class,
                        'operator' => '==',
                        'value' => 'simple',
                        'attribute' => 'sku',
                    ],
                ],
            ],
            'address and product condition' => [
                'aggregator' => 'all',
                'conditions' => [
                    [
                        'type' => QuoteAddress::class,
                        'operator' => '==',
                        'value' => 'flatrate_flatrate',
                        'attribute' => 'shipping_method',
                    ],
                    [
                        'type' => Product::class,
                        'operator' => '==',
                        'value' => 'simple',
                        'attribute' => 'sku',
                    ],
                ],
            ],
        ];
    }
}
