<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Model;

use InvalidArgumentException;
use JosephLeedy\CustomFees\Api\ConfigInterface;
use JosephLeedy\CustomFees\Model\Config;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\ScopeInterface as StoreScopeInterface;
use Magento\TestFramework\Fixture\Config as ConfigFixture;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

use function __;

final class ConfigTest extends TestCase
{
    #[ConfigFixture(
        ConfigInterface::CONFIG_PATH_CUSTOM_FEES,
        '{"_1748287113250_250":{"code":"test_fee_0","title":"Test Fee","value":"4.00"},'
        . '"_1748287169237_237":{"code":"test_fee_1","title":"Another Fee","value":"1.00","advanced":"{\"conditions\":'
        . '{\"type\":\"JosephLeedy\\\\CustomFees\\\\Model\\\\Rule\\\\Condition\\\\Combine\",\"aggregator\":\"any\",'
        . '\"value\":\"1\",\"conditions\":[{\"type\":\"JosephLeedy\\\\CustomFees\\\\Model\\\\Rule\\\\Condition\\\\'
        . 'QuoteAddress\",\"attribute\":\"base_subtotal\",\"operator\":\">=\",\"value\":\"100\"},{\"type\":\"Joseph'
        . 'Leedy\\\\CustomFees\\\\Model\\\\Rule\\\\Condition\\\\QuoteAddress\",\"attribute\":\"total_qty\",\"operator'
        . '\":\"<\",\"value\":\"2\"}]}}"}}',
        StoreScopeInterface::SCOPE_STORE,
        'default',
    )]
    public function testGetsAdvancedCustomFeesConfig(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var Config $config */
        $config = $objectManager->create(Config::class);

        $customFees = $config->getCustomFees();
        $expectedAdvancedConfig = [
            'conditions' => [
                'type' => 'JosephLeedy\CustomFees\Model\Rule\Condition\Combine',
                'aggregator' => 'any',
                'value' => '1',
                'conditions' => [
                    [
                        'type' => 'JosephLeedy\CustomFees\Model\Rule\Condition\QuoteAddress',
                        'attribute' => 'base_subtotal',
                        'operator' => '>=',
                        'value' => '100',
                    ],
                    [
                        'type' => 'JosephLeedy\CustomFees\Model\Rule\Condition\QuoteAddress',
                        'attribute' => 'total_qty',
                        'operator' => '<',
                        'value' => '2',
                    ],
                ],
            ],
        ];

        self::assertEmpty($customFees['_1748287113250_250']['advanced']);
        self::assertSame($expectedAdvancedConfig, $customFees['_1748287169237_237']['advanced']);
    }

    #[ConfigFixture(
        ConfigInterface::CONFIG_PATH_CUSTOM_FEES,
        '{"_1748289122895_895":{"code":"test_fee_0","title":"Test Fee","value":"4.00"},'
        . '"_1748289147673_673":{"code":"test_fee_1","title":"Another Fee","value":"1.00","advanced":"{"}}',
        StoreScopeInterface::SCOPE_STORE,
        'default',
    )]
    public function testThrowsExceptionIfAdvancedCustomFeesConfigIsInvalid(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var LocalizedException $localizedException */
        $localizedException = $objectManager->create(
            LocalizedException::class,
            [
                'phrase' => __(
                    'Could not parse advanced configuration for custom fee "%1". Error: "%2"',
                    'test_fee_1',
                    'Unable to unserialize value. Error: Syntax error',
                ),
                new InvalidArgumentException('Unable to unserialize value. Error: Syntax error'),
            ],
        );
        /** @var Config $config */
        $config = $objectManager->create(Config::class);

        $this->expectExceptionObject($localizedException);

        $config->getCustomFees();
    }
}
