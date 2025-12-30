<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Service;

use ColinODell\PsrTestLogger\TestLogger;
use JosephLeedy\CustomFees\Api\ConfigInterface;
use JosephLeedy\CustomFees\Service\CustomQuoteFeesRetriever;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\Store\Model\ScopeInterface as StoreScopeInterface;
use Magento\TestFramework\Fixture\Config as ConfigFixture;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

use function __;
use function array_column;
use function count;
use function in_array;

final class CustomQuoteFeesRetrieverTest extends TestCase
{
    #[ConfigFixture(
        ConfigInterface::CONFIG_PATH_CUSTOM_FEES,
        '{"_1727299833817_817":{"code":"test_fee_0","title":"Test Fee","type":"fixed","status":"1","value":"4.00",'
        . '"advanced":"{\\"show_percentage\\":\\"0\\"}"},"_1727299843197_197":{"code":"test_fee_1","title":'
        . '"Another Fee","type":"percent","status":"1","value":"1.00","advanced":"{\\"show_percentage\\":\\"1\\"}"}}',
        StoreScopeInterface::SCOPE_STORE,
        'default',
    )]
    #[DataFixture('Magento/Checkout/_files/quote_with_address.php')]
    public function testRetrievesCustomQuoteFees(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var Quote $quote */
        $quote = $objectManager->create(Quote::class);
        /** @var QuoteResource $quoteResource */
        $quoteResource = $objectManager->create(QuoteResource::class);
        /** @var CustomQuoteFeesRetriever $customQuoteFeesRetriever */
        $customQuoteFeesRetriever = $objectManager->create(CustomQuoteFeesRetriever::class);

        $quoteResource->load($quote, 'test_order_1', 'reserved_order_id');

        $expectedCustomFees = [
            'test_fee_0' => [
                'code' => 'test_fee_0',
                'title' => 'Test Fee',
                'type' => 'fixed',
                'value' => '4.00',
                'advanced' => [
                    'show_percentage' => false,
                ],
            ],
            'test_fee_1' => [
                'code' => 'test_fee_1',
                'title' => 'Another Fee',
                'type' => 'percent',
                'value' => '1.00',
                'advanced' => [
                    'show_percentage' => true,
                ],
            ],
        ];
        $actualCustomFees = $customQuoteFeesRetriever->retrieveApplicableFees($quote);

        self::assertEquals($expectedCustomFees, $actualCustomFees);
    }

    #[ConfigFixture(
        ConfigInterface::CONFIG_PATH_CUSTOM_FEES,
        '{"_1727299833817_817":{"code":"test_fee_0","title":"Test Fee","type":"fixed","status":"0","value":"4.00",'
        . '"advanced":"{\\"show_percentage\\":\\"0\\"}"},"_1727299843197_197":{"code":"test_fee_1","title":'
        . '"Another Fee","type":"percent","status":"0","value":"1.00","advanced":"{\\"show_percentage\\":\\"1\\"}"}}',
        StoreScopeInterface::SCOPE_STORE,
        'default',
    )]
    #[DataFixture('Magento/Checkout/_files/quote_with_address.php')]
    public function testDoesNotRetrieveDisabledCustomQuoteFees(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var Quote $quote */
        $quote = $objectManager->create(Quote::class);
        /** @var QuoteResource $quoteResource */
        $quoteResource = $objectManager->create(QuoteResource::class);
        /** @var CustomQuoteFeesRetriever $customQuoteFeesRetriever */
        $customQuoteFeesRetriever = $objectManager->create(CustomQuoteFeesRetriever::class);

        $quoteResource->load($quote, 'test_order_1', 'reserved_order_id');

        $actualCustomFees = $customQuoteFeesRetriever->retrieveApplicableFees($quote);

        self::assertEmpty($actualCustomFees);
    }

    #[ConfigFixture(
        ConfigInterface::CONFIG_PATH_CUSTOM_FEES,
        '{"_1727299833817_817":{"code":"test_fee_0","title":"Test Fee","type":"fixed","status":"1","value":"4.00",'
        . '"advanced":"{\\"show_percentage\\":\\"0\\",\\"conditions\\":{\\"type\\":\\"JosephLeedy\\\\\\\\CustomFees\\\\'
        . '\\\\Model\\\\\\\\Rule\\\\\\\\Condition\\\\\\\\Combine\\",\\"aggregator\\":\\"any\\",\\"value\\":\\"1\\",'
        . '\\"conditions\\":[{\\"type\\":\\"JosephLeedy\\\\\\\\CustomFees\\\\\\\\Model\\\\\\\\Rule\\\\\\\\Condition\\\\'
        . '\\\\Product\\",\\"attribute\\":\\"sku\\",\\"operator\\":\\"==\\",\\"value\\":\\"simple\\"}]}}"},"_1727299843'
        . '197_197":{"code":"test_fee_1","title":"Another Fee","type":"percent","status":"0","value":"1.00","advanced":'
        . '"{\\"show_percentage\\":\\"1\\"}"}}',
        StoreScopeInterface::SCOPE_STORE,
        'default',
    )]
    #[DataFixture('Magento/Checkout/_files/quote_with_address.php')]
    public function testDoesNotRetrieveUnapplicableCustomQuoteFees(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var Quote $quote */
        $quote = $objectManager->create(Quote::class);
        /** @var QuoteResource $quoteResource */
        $quoteResource = $objectManager->create(QuoteResource::class);
        /** @var CustomQuoteFeesRetriever $customQuoteFeesRetriever */
        $customQuoteFeesRetriever = $objectManager->create(CustomQuoteFeesRetriever::class);

        $quoteResource->load($quote, 'test_order_1', 'reserved_order_id');

        $expectedCustomFees = [
            'test_fee_0' => [
                'code' => 'test_fee_0',
                'title' => 'Test Fee',
                'type' => 'fixed',
                'value' => '4.00',
                'advanced' => [
                    'show_percentage' => false,
                ],
            ],
        ];
        $actualCustomFees = $customQuoteFeesRetriever->retrieveApplicableFees($quote);

        self::assertEquals($expectedCustomFees, $actualCustomFees);
    }

    #[DataFixture('Magento/Checkout/_files/quote_with_address.php')]
    public function testDoesNotRetrieveExampleCustomQuoteFee(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var ConfigInterface $config */
        $config = $objectManager->get(ConfigInterface::class);
        /** @var Quote $quote */
        $quote = $objectManager->create(Quote::class);
        /** @var QuoteResource $quoteResource */
        $quoteResource = $objectManager->create(QuoteResource::class);
        /** @var CustomQuoteFeesRetriever $customQuoteFeesRetriever */
        $customQuoteFeesRetriever = $objectManager->create(CustomQuoteFeesRetriever::class);

        try {
            $customFees = $config->getCustomFees();
        } catch (LocalizedException) {
            $customFees = [];
        }

        if (count($customFees) === 0 || !in_array('example_fee', array_column($customFees, 'code'), true)) {
            self::fail('Example custom fee is not configured');
        }

        $quoteResource->load($quote, 'test_order_1', 'reserved_order_id');

        $customQuoteFees = $customQuoteFeesRetriever->retrieveApplicableFees($quote);

        self::assertArrayNotHasKey('example_fee', $customQuoteFees);
    }

    #[ConfigFixture(
        ConfigInterface::CONFIG_PATH_CUSTOM_FEES,
        '{"_1727299833817_817":{"code":"test_fee_0","title":"Test Fee","type":"fixed","status":"1","value":"4.00",'
        . '"advanced":"{\\"show_percentage\\":\\"0\\"}"},"_1727299843197_197":{"code":"test_fee_1","title":'
        . '"Another Fee","type":"percent","status":"1","value":"1.00","advanced":"{\\"show_percentage\\":\\"1\\"}"}}',
        StoreScopeInterface::SCOPE_STORE,
        'default',
    )]
    #[DataFixture('Magento/Checkout/_files/quote_with_address.php')]
    public function testLogsExceptionThrownWhileGettingConfiguredCustomFees(): void
    {
        $configStub = $this->createStub(ConfigInterface::class);
        $testLogger = new TestLogger();
        $objectManager = Bootstrap::getObjectManager();
        /** @var LocalizedException $couldNotGetCustomFeesException */
        $couldNotGetCustomFeesException = $objectManager->create(
            LocalizedException::class,
            [
                'phrase' => __(
                    'Could not get custom fees from configuration. Error: "%1"',
                    'Unable to unserialize value. Error: Syntax error',
                ),
            ],
        );
        /** @var Quote $quote */
        $quote = $objectManager->create(Quote::class);
        /** @var QuoteResource $quoteResource */
        $quoteResource = $objectManager->create(QuoteResource::class);
        /** @var CustomQuoteFeesRetriever $customQuoteFeesRetriever */
        $customQuoteFeesRetriever = $objectManager->create(
            CustomQuoteFeesRetriever::class,
            [
                'config' => $configStub,
                'logger' => $testLogger,
            ],
        );

        $configStub
            ->method('getCustomFees')
            ->willThrowException($couldNotGetCustomFeesException);

        $quoteResource->load($quote, 'test_order_1', 'reserved_order_id');

        $customQuoteFeesRetriever->retrieveApplicableFees($quote);

        self::assertTrue(
            $testLogger->hasRecord(
                [
                    'message' => 'Could not get custom fees from configuration. Error: "Unable to unserialize value. '
                        . 'Error: Syntax error"',
                    'context' => [
                        'exception' => $couldNotGetCustomFeesException,
                    ],
                ],
                LogLevel::CRITICAL,
            ),
        );
    }
}
