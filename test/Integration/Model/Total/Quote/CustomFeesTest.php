<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Model\Total\Quote;

use JosephLeedy\CustomFees\Api\ConfigInterface;
use JosephLeedy\CustomFees\Model\Total\Quote\CustomFees;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

use function __;
use function array_column;
use function in_array;

final class CustomFeesTest extends TestCase
{
    /**
     * @phpcs:ignore Generic.Files.LineLength.TooLong
     * @magentoConfigFixture current_store sales/custom_order_fees/custom_fees [{"code":"test_fee_0","title":"Test Fee","type":"fixed","value":"4.00"},{"code":"test_fee_1","title":"Another Fee","type":"fixed","value":"1.00"}]
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
                'type' => 'fixed',
                'value' => 4.00,
            ],
            $collectedTotals['test_fee_0']->getData(),
        );
        self::assertEquals(
            [
                'code' => 'test_fee_1',
                'title' => __('Another Fee'),
                'type' => 'fixed',
                'value' => 1.00,
            ],
            $collectedTotals['test_fee_1']->getData(),
        );
        self::assertNotNull($quote->getExtensionAttributes()?->getCustomFees());
        self::assertEquals(
            [
                [
                    'code' => 'test_fee_0',
                    'title' => __('Test Fee'),
                    'type' => 'fixed',
                    'base_value' => 4.00,
                    'value' => 4.00,
                ],
                [
                    'code' => 'test_fee_1',
                    'title' => __('Another Fee'),
                    'type' => 'fixed',
                    'base_value' => 1.00,
                    'value' => 1.00,
                ],
            ],
            $quote->getExtensionAttributes()->getCustomFees(),
        );
    }

    /**
     * @phpcs:ignore Generic.Files.LineLength.TooLong
     * @magentoConfigFixture current_store sales/custom_order_fees/custom_fees [{"code":"test_fee_0","title":"Test Fee","type":"fixed","value":"4.00"},{"code":"test_fee_1","title":"Another Fee","type":"fixed","value":"1.00"}]
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
                'type' => 'fixed',
                'value' => 4.00,
            ],
            [
                'code' => 'test_fee_1',
                'title' => __('Another Fee'),
                'type' => 'fixed',
                'value' => 1.00,
            ],
        ];
        $actualCustomFees = $customFeesTotalCollector->fetch($quote, $total);

        self::assertEquals($expectedCustomFees, $actualCustomFees);
    }

    /**
     * @magentoDataFixture Magento/Checkout/_files/quote_with_address.php
     */
    public function testDoesNotCollectExampleCustomFeesTotals(): void
    {
        /** @var ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var ConfigInterface $config */
        $config = $objectManager->get(ConfigInterface::class);
        /** @var Quote $quote */
        $quote = $objectManager->create(Quote::class);
        /** @var QuoteResource $quoteResource */
        $quoteResource = $objectManager->create(QuoteResource::class);

        try {
            $customFees = $config->getCustomFees();
        } catch (LocalizedException) {
            $customFees = [];
        }

        if (count($customFees) === 0 || !in_array('example_fee', array_column($customFees, 'code'), true)) {
            self::fail('Example custom fee is not configured');
        }

        $quoteResource->load($quote, 'test_order_1', 'reserved_order_id');

        $quote->collectTotals();

        $collectedTotals = $quote->getTotals();

        self::assertArrayNotHasKey('example_fee', $collectedTotals);
        self::assertEmpty($quote->getExtensionAttributes()?->getCustomFees());
    }
}
