<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Service;

use JosephLeedy\CustomFees\Service\RulesApplier;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

final class RulesApplierTest extends TestCase
{
    #[DataFixture('Magento/Checkout/_files/quote_with_address_and_shipping_method_saved.php')]
    public function testAppliesRulesSuccessfully(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var QuoteResource $quoteResource */
        $quoteResource = $objectManager->create(QuoteResource::class);
        /** @var Quote $quote */
        $quote = $objectManager->create(Quote::class);
        $conditions = [
            'type' => 'JosephLeedy\CustomFees\Model\Rule\Condition\Combine',
            'aggregator' => 'any',
            'value' => '1',
            'conditions' => [
                [
                    'type' => 'JosephLeedy\CustomFees\Model\Rule\Condition\QuoteAddress',
                    'attribute' => 'shipping_method',
                    'operator' => '==',
                    'value' => 'flatrate_flatrate',
                ],
            ],
        ];
        /** @var RulesApplier $rulesApplier */
        $rulesApplier = $objectManager->create(RulesApplier::class);

        $quoteResource->load($quote, 'test_order_1', 'reserved_order_id');

        $isApplicable = $rulesApplier->isApplicable($quote->getShippingAddress(), 'test_fee_0', $conditions);

        self::assertTrue($isApplicable);
    }
}
