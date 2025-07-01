<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Service;

use InvalidArgumentException;
use JosephLeedy\CustomFees\Service\ConfigUpdater;
use Magento\Config\App\Config\Source\RuntimeConfigSource;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\ScopeResolverInterface;
use Magento\Framework\App\ScopeResolverPool;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\State\InitException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

use function __;
use function json_encode;

use const JSON_THROW_ON_ERROR;

final class ConfigUpdaterTest extends TestCase
{
    /**
     * @dataProvider addsFieldToConfiguredCustomFeesDataProvider
     * @param string|array<string, mixed> $fieldName
     */
    public function testAddsFieldToConfiguredCustomFees(
        string $scopeType,
        string $scopeCode,
        string $expectedCustomFeesJson,
        int $expectedScopeId,
        string|array $fieldName,
        mixed $defaultValue,
        ?string $after,
    ): void {
        $customFees = [
            '_1749848030825_825' => [
                'code' => 'test_fee_0',
                'title' => 'Test Fee',
                'value' => '5.00',
                'advanced' => '{}',
            ],
            '_1749848078605_605' => [
                'code' => 'test_fee_1',
                'title' => 'Another Test Fee',
                'value' => '1.50',
                'advanced' => '{}',
            ],
        ];
        $runtimeConfigSourceStub = $this->createStub(RuntimeConfigSource::class);
        $configWriterMock = $this->createMock(WriterInterface::class);
        $objectManager = Bootstrap::getObjectManager();
        /** @var ConfigUpdater $configUpdater */
        $configUpdater = $objectManager->create(
            ConfigUpdater::class,
            [
                'runtimeConfigSource' => $runtimeConfigSourceStub,
                'configWriter' => $configWriterMock,
            ],
        );

        $runtimeConfigSourceStub
            ->method('get')
            ->willReturn(json_encode($customFees, JSON_THROW_ON_ERROR));

        $configWriterMock
            ->expects(self::once())
            ->method('save')
            ->with('sales/custom_order_fees/custom_fees', $expectedCustomFeesJson, $scopeType, $expectedScopeId);

        $configUpdater->addFieldToCustomFeesByScope($scopeType, $scopeCode, $fieldName, $defaultValue, $after);
    }

    public function testDoesNotAddFieldToConfiguredCustomFeesIfExceptionIsThrownWhileGettingIdOfInvalidScope(): void
    {
        $scopeResolverPoolStub = $this->createStub(ScopeResolverPool::class);
        $invalidArgumentException = new InvalidArgumentException("Invalid scope type 'invalid_scope'");
        $objectManager = Bootstrap::getObjectManager();
        /** @var LocalizedException $localizedException */
        $localizedException = $objectManager->create(
            LocalizedException::class,
            [
                'phrase' => __('Could not get identifier for scope "%1".', 'invalid_code'),
                'cause' => $invalidArgumentException,
            ],
        );
        /** @var ConfigUpdater $configUpdater */
        $configUpdater = $objectManager->create(
            ConfigUpdater::class,
            [
                'scopeResolverPool' => $scopeResolverPoolStub,
            ],
        );

        $scopeResolverPoolStub
            ->method('get')
            ->willThrowException($invalidArgumentException);

        $this->expectExceptionObject($localizedException);

        $configUpdater->addFieldToCustomFeesByScope('invalid_scope', 'invalid_code', '', null);
    }

    // phpcs:ignore Squiz.Functions.FunctionDeclarationArgumentSpacing.SpacingBetween
    public function testDoesNotAddFieldToConfiguredCustomFeesIfExceptionIsThrownWhileGettingIdOfInvalidStoreScope(
    ): void {
        $scopeResolverStub = $this->createStub(ScopeResolverInterface::class);
        $scopeResolverPoolStub = $this->createStub(ScopeResolverPool::class);
        $objectManager = Bootstrap::getObjectManager();
        /** @var InitException $initException */
        $initException = $objectManager->create(
            InitException::class,
            [
                'phrase' => __('The scope object is invalid. Verify the scope object and try again.'),
            ],
        );
        /** @var LocalizedException $localizedException */
        $localizedException = $objectManager->create(
            LocalizedException::class,
            [
                'phrase' => __('Could not get identifier for scope "%1".', 'invalid_store'),
                'cause' => $initException,
            ],
        );
        /** @var ConfigUpdater $configUpdater */
        $configUpdater = $objectManager->create(
            ConfigUpdater::class,
            [
                'scopeResolverPool' => $scopeResolverPoolStub,
            ],
        );

        $scopeResolverStub
            ->method('getScope')
            ->willThrowException($initException);

        $scopeResolverPoolStub
            ->method('get')
            ->willReturn($scopeResolverStub);

        $this->expectExceptionObject($localizedException);

        $configUpdater->addFieldToCustomFeesByScope('stores', 'invalid_store', '', null);
    }

    public function testDoesNotAddFieldToConfiguredCustomFeesIfCustomFeesNotConfigured(): void
    {
        $configWriterMock = $this->createMock(WriterInterface::class);
        $objectManager = Bootstrap::getObjectManager();
        /** @var ConfigUpdater $configUpdater */
        $configUpdater = $objectManager->create(
            ConfigUpdater::class,
            [
                'configWriter' => $configWriterMock,
            ],
        );

        $configWriterMock
            ->expects(self::never())
            ->method('save');

        $configUpdater->addFieldToCustomFeesByScope('default', null, '', null);
    }

    public function testDoesNotAddFieldToConfiguredCustomFeesIfExceptionIsThrownWhileProcessingCustomFees(): void
    {
        $runtimeConfigSourceStub = $this->createStub(RuntimeConfigSource::class);
        $serializerStub = $this->createStub(SerializerInterface::class);
        $configWriterMock = $this->createMock(WriterInterface::class);
        $objectManager = Bootstrap::getObjectManager();
        /** @var ConfigUpdater $configUpdater */
        $configUpdater = $objectManager->create(
            ConfigUpdater::class,
            [
                'runtimeConfigSource' => $runtimeConfigSourceStub,
                'serializer' => $serializerStub,
                'configWriter' => $configWriterMock,
            ],
        );

        $runtimeConfigSourceStub
            ->method('get')
            ->willReturn('{}');

        $serializerStub
            ->method('unserialize')
            ->willThrowException(new InvalidArgumentException('JSON syntax error'));

        $configWriterMock
            ->expects(self::never())
            ->method('save');

        $configUpdater->addFieldToCustomFeesByScope('default', null, '', null);
    }

    public function testDoesNotAddFieldToConfiguredCustomFeesThatAlreadyHaveIt(): void
    {
        $customFees = [
            '_1749848030825_825' => [
                'code' => 'test_fee_0',
                'title' => 'Test Fee',
                'value' => '5.00',
                'test' => true,
                'advanced' => '{}',
            ],
            '_1749848078605_605' => [
                'code' => 'test_fee_1',
                'title' => 'Another Test Fee',
                'value' => '1.50',
                'test' => true,
                'advanced' => '{}',
            ],
        ];
        $runtimeConfigSourceStub = $this->createStub(RuntimeConfigSource::class);
        $configWriterMock = $this->createMock(WriterInterface::class);
        $objectManager = Bootstrap::getObjectManager();
        /** @var ConfigUpdater $configUpdater */
        $configUpdater = $objectManager->create(
            ConfigUpdater::class,
            [
                'runtimeConfigSource' => $runtimeConfigSourceStub,
                'configWriter' => $configWriterMock,
            ],
        );

        $runtimeConfigSourceStub
            ->method('get')
            ->willReturn(json_encode($customFees, JSON_THROW_ON_ERROR));

        $configWriterMock
            ->expects(self::never())
            ->method('save');

        $configUpdater->addFieldToCustomFeesByScope('default', null, 'test', true, 'value');
    }

    public function testDoesNotAddFieldToConfiguredCustomFeesIfExceptionIsThrownWhileSerializingCustomFees(): void
    {
        $customFees = [
            '_1749848030825_825' => [
                'code' => 'test_fee_0',
                'title' => 'Test Fee',
                'value' => '5.00',
                'advanced' => '{}',
            ],
            '_1749848078605_605' => [
                'code' => 'test_fee_1',
                'title' => 'Another Test Fee',
                'value' => '1.50',
                'advanced' => '{}',
            ],
        ];
        $runtimeConfigSourceStub = $this->createStub(RuntimeConfigSource::class);
        $serializerStub = $this->createStub(SerializerInterface::class);
        $invalidArgumentException = new InvalidArgumentException('JSON syntax error');
        $configWriterMock = $this->createMock(WriterInterface::class);
        $objectManager = Bootstrap::getObjectManager();
        /** @var LocalizedException $localizedException */
        $localizedException = $objectManager->create(
            LocalizedException::class,
            [
                'phrase' => __('Could not serialize custom fees.'),
                'cause' => $invalidArgumentException,
            ],
        );
        /** @var ConfigUpdater $configUpdater */
        $configUpdater = $objectManager->create(
            ConfigUpdater::class,
            [
                'runtimeConfigSource' => $runtimeConfigSourceStub,
                'configWriter' => $configWriterMock,
                'serializer' => $serializerStub,
            ],
        );

        $runtimeConfigSourceStub
            ->method('get')
            ->willReturn(json_encode($customFees, JSON_THROW_ON_ERROR));

        $serializerStub
            ->method('unserialize')
            ->willReturn($customFees);
        $serializerStub
            ->method('serialize')
            ->willThrowException($invalidArgumentException);

        $configWriterMock
            ->expects(self::never())
            ->method('save');

        $this->expectExceptionObject($localizedException);

        $configUpdater->addFieldToCustomFeesByScope('default', null, 'test', true, 'value');
    }

    /**
     * @return array<string, array<string, string|int|bool|array<string, bool>|null>>
     */
    public static function addsFieldToConfiguredCustomFeesDataProvider(): array
    {
        return [
            'single value in default scope at end' => [
                'scopeType' => 'default',
                'scopeCode' => 'default',
                'expectedCustomFeesJson' => '{"_1749848030825_825":{"code":"test_fee_0","title":"Test Fee",'
                    . '"value":"5.00","advanced":"{}","test":true},"_1749848078605_605":{"code":"test_fee_1",'
                    . '"title":"Another Test Fee","value":"1.50","advanced":"{}","test":true}}',
                'expectedScopeId' => 0,
                'fieldName' => 'test',
                'defaultValue' => true,
                'after' => null,
            ],
            'multiple values in default scope at end' => [
                'scopeType' => 'default',
                'scopeCode' => 'default',
                'expectedCustomFeesJson' => '{"_1749848030825_825":{"code":"test_fee_0","title":"Test Fee",'
                    . '"value":"5.00","advanced":"{}","test":true,"another_test":false},"_1749848078605_605":{'
                    . '"code":"test_fee_1","title":"Another Test Fee","value":"1.50","advanced":"{}","test":true,'
                    . '"another_test":false}}',
                'expectedScopeId' => 0,
                'fieldName' => [
                    'test' => true,
                    'another_test' => false,
                ],
                'defaultValue' => null,
                'after' => null,
            ],
            'single value in store scope at end' => [
                'scopeType' => 'stores',
                'scopeCode' => 'default',
                'expectedCustomFeesJson' => '{"_1749848030825_825":{"code":"test_fee_0","title":"Test Fee",'
                    . '"value":"5.00","advanced":"{}","test":true},"_1749848078605_605":{"code":"test_fee_1",'
                    . '"title":"Another Test Fee","value":"1.50","advanced":"{}","test":true}}',
                'expectedScopeId' => 1,
                'fieldName' => 'test',
                'defaultValue' => true,
                'after' => null,
            ],
            'multiple values in store scope at end' => [
                'scopeType' => 'stores',
                'scopeCode' => 'default',
                'expectedCustomFeesJson' => '{"_1749848030825_825":{"code":"test_fee_0","title":"Test Fee",'
                    . '"value":"5.00","advanced":"{}","test":true,"another_test":false},"_1749848078605_605":{'
                    . '"code":"test_fee_1","title":"Another Test Fee","value":"1.50","advanced":"{}","test":true,'
                    . '"another_test":false}}',
                'expectedScopeId' => 1,
                'fieldName' => [
                    'test' => true,
                    'another_test' => false,
                ],
                'defaultValue' => null,
                'after' => null,
            ],
            'single value in default scope after title' => [
                'scopeType' => 'default',
                'scopeCode' => 'default',
                'expectedCustomFeesJson' => '{"_1749848030825_825":{"code":"test_fee_0","title":"Test Fee","test":true,'
                    . '"value":"5.00","advanced":"{}"},"_1749848078605_605":{"code":"test_fee_1",'
                    . '"title":"Another Test Fee","test":true,"value":"1.50","advanced":"{}"}}',
                'expectedScopeId' => 0,
                'fieldName' => 'test',
                'defaultValue' => true,
                'after' => 'title',
            ],
            'multiple values in default scope after title' => [
                'scopeType' => 'default',
                'scopeCode' => 'default',
                'expectedCustomFeesJson' => '{"_1749848030825_825":{"code":"test_fee_0","title":"Test Fee","test":true,'
                    . '"another_test":false,"value":"5.00","advanced":"{}"},"_1749848078605_605":{"code":"test_fee_1",'
                    . '"title":"Another Test Fee","test":true,"another_test":false,"value":"1.50","advanced":"{}"}}',
                'expectedScopeId' => 0,
                'fieldName' => [
                    'test' => true,
                    'another_test' => false,
                ],
                'defaultValue' => null,
                'after' => 'title',
            ],
        ];
    }
}
