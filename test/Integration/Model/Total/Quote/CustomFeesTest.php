<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Model\Total\Quote;

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
     * @magentoConfigFixture current_store sales/custom_order_fees/custom_fees [{"code":"test_fee_0","title":"Test Fee","value":"4.00"},{"code":"test_fee_1","title":"Another Fee","value":"1.00"}]
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
                'value' => 4.00
            ],
            $collectedTotals['test_fee_0']->getData()
        );
        self::assertEquals(
            [
                'code' => 'test_fee_1',
                'title' => __('Another Fee'),
                'value' => 1.00
            ],
            $collectedTotals['test_fee_1']->getData()
        );
    }

    /**
     * @phpcs:ignore Generic.Files.LineLength.TooLong
     * @magentoConfigFixture current_store sales/custom_order_fees/custom_fees [{"code":"test_fee_0","title":"Test Fee","value":"4.00"},{"code":"test_fee_1","title":"Another Fee","value":"1.00"}]
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

        $expectedCustomFees = [
            [
                'code' => 'test_fee_0',
                'title' => __('Test Fee'),
                'value' => 4.00
            ],
            [
                'code' => 'test_fee_1',
                'title' => __('Another Fee'),
                'value' => 1.00
            ]
        ];
        $actualCustomFees = $customFeesTotalCollector->fetch($quote, $total);

        self::assertEquals($expectedCustomFees, $actualCustomFees);
    }
}
