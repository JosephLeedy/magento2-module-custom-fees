<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Model\Sales\Pdf;

use JosephLeedy\CustomFees\Api\ConfigInterface;
use JosephLeedy\CustomFees\Model\DisplayType;
use JosephLeedy\CustomFees\Model\Sales\Pdf\CustomFees;
use Magento\Framework\ObjectManagerInterface;
use Magento\Sales\Model\Order;
use Magento\TestFramework\Annotation\DataFixture;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;
use PHPUnit\Framework\TestCase;

final class CustomFeesTest extends TestCase
{
    /**
     * @magentoDataFixture JosephLeedy_CustomFees::../test/Integration/_files/order_with_custom_fees.php
     */
    public function testGetTotalsForDisplayGetsTotals(): void
    {
        /** @var ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        /** @var CustomFees $customFeesOrderPdfModel */
        $customFeesOrderPdfModel = $objectManager->create(CustomFees::class);

        $order->loadByIncrementId('100000001');

        $customFeesOrderPdfModel->setOrder($order);

        $expectedCustomFeesTotals = [
            [
                'amount' => '$5.00',
                'label' => 'Test Fee:',
                'font_size' => 7,
            ],
            [
                'amount' => '$1.50',
                'label' => 'Another Test Fee:',
                'font_size' => 7,
            ],
        ];
        $actualCustomFeesTotals = $customFeesOrderPdfModel->getTotalsForDisplay();

        self::assertEquals($expectedCustomFeesTotals, $actualCustomFeesTotals);
    }

    /**
     * @dataProvider displayTypeDataProvider
     * @magentoDataFixture JosephLeedy_CustomFees::../test/Integration/_files/order_with_custom_fees_taxed.php
     */
    public function testGetTotalsForDisplayGetsTotalsByDisplayType(DisplayType $displayType): void
    {
        $configStub = $this->createStub(ConfigInterface::class);
        /** @var ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        /** @var CustomFees $customFeesOrderPdfModel */
        $customFeesOrderPdfModel = $objectManager->create(
            CustomFees::class,
            [
                'config' => $configStub,
            ],
        );

        $configStub
            ->method('getSalesDisplayType')
            ->willReturn($displayType);

        $order->loadByIncrementId('100000001');

        $customFeesOrderPdfModel->setOrder($order);

        $expectedCustomFeesTotals = match ($displayType) {
            DisplayType::IncludingTax => [
                [
                    'amount' => '$5.30',
                    'label' => 'Test Fee:',
                    'font_size' => 7,
                ],
                [
                    'amount' => '$1.59',
                    'label' => 'Another Test Fee:',
                    'font_size' => 7,
                ],
            ],
            DisplayType::Both => [
                [
                    'amount' => '$5.00',
                    'label' => 'Test Fee (Excl. Tax):',
                    'font_size' => 7,
                ],
                [
                    'amount' => '$5.30',
                    'label' => 'Test Fee (Incl. Tax):',
                    'font_size' => 7,
                ],
                [
                    'amount' => '$1.50',
                    'label' => 'Another Test Fee (Excl. Tax):',
                    'font_size' => 7,
                ],
                [
                    'amount' => '$1.59',
                    'label' => 'Another Test Fee (Incl. Tax):',
                    'font_size' => 7,
                ],
            ],
        };
        $actualCustomFeesTotals = $customFeesOrderPdfModel->getTotalsForDisplay();

        self::assertEquals($expectedCustomFeesTotals, $actualCustomFeesTotals);
    }

    /**
     * @param 'true'|'false' $canDisplayZero
     * @dataProvider doesNotGetTotalsForDisplayDataProvider
     */
    public function testGetTotalsForDisplayDoesNotGetTotals(string $canDisplayZero): void
    {
        $filename = 'order';

        if ($canDisplayZero === 'false') {
            $filename .= '_with_example_custom_fee';
        }

        $resolver = Resolver::getInstance();

        $resolver->setCurrentFixtureType(DataFixture::ANNOTATION);
        $resolver->requireDataFixture("JosephLeedy_CustomFees::../test/Integration/_files/$filename.php");

        /** @var ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        /** @var CustomFees $customFeesOrderPdfModel */
        $customFeesOrderPdfModel = $objectManager->create(CustomFees::class);

        $order->loadByIncrementId('100000001');

        $customFeesOrderPdfModel->setOrder($order);
        $customFeesOrderPdfModel->setDisplayZero($canDisplayZero);

        $actualCustomFeesTotals = $customFeesOrderPdfModel->getTotalsForDisplay();

        self::assertEmpty($actualCustomFeesTotals);
    }

    /**
     * @param 'true'|'false' $canDisplayZero
     * @dataProvider canDisplayDataProvider
     */
    public function testCanDisplay(bool $hasCustomFees, string $canDisplayZero, bool $expectedCanDisplay): void
    {
        $filename = 'order';

        if ($hasCustomFees) {
            $filename .= '_with_custom_fees';
        }

        if ($canDisplayZero === 'false') {
            $filename .= '_with_example_custom_fee';
        }

        $resolver = Resolver::getInstance();

        $resolver->setCurrentFixtureType(DataFixture::ANNOTATION);
        $resolver->requireDataFixture("JosephLeedy_CustomFees::../test/Integration/_files/$filename.php");

        /** @var ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        /** @var CustomFees $customFeesOrderPdfModel */
        $customFeesOrderPdfModel = $objectManager->create(CustomFees::class);

        $order->loadByIncrementId('100000001');

        $customFeesOrderPdfModel->setOrder($order);
        $customFeesOrderPdfModel->setDisplayZero($canDisplayZero);

        $actualCanDisplay = $customFeesOrderPdfModel->canDisplay();

        if ($expectedCanDisplay) {
            self::assertTrue($actualCanDisplay);
        } else {
            self::assertFalse($actualCanDisplay);
        }
    }

    /**
     * @return array<string, array{'displayType': DisplayType}>
     */
    public static function displayTypeDataProvider(): array
    {
        return [
            'including tax' => [
                'displayType' => DisplayType::IncludingTax,
            ],
            'including and excluding tax' => [
                'displayType' => DisplayType::Both,
            ],
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function doesNotGetTotalsForDisplayDataProvider(): array
    {
        return [
            'order does not have custom fees' => [
                'canDisplayZero' => 'true',
            ],
            'order has custom fees with zero amount' => [
                'canDisplayZero' => 'false',
            ],
        ];
    }

    /**
     * @return array<string, array<string, string|boolean>>
     */
    public function canDisplayDataProvider(): array
    {
        return [
            'can display custom fees over zero' => [
                'hasCustomFees' => true,
                'canDisplayZero' => 'true',
                'expectedCanDisplay' => true,
            ],
            'can display custom fees equal to zero if enabled' => [
                'hasCustomFees' => false,
                'canDisplayZero' => 'true',
                'expectedCanDisplay' => true,
            ],
            'can not display custom fees equal to zero if disabled' => [
                'hasCustomFees' => false,
                'canDisplayZero' => 'false',
                'expectedCanDisplay' => false,
            ],
        ];
    }
}
