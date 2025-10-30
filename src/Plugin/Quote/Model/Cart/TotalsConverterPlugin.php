<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Plugin\Quote\Model\Cart;

use JosephLeedy\CustomFees\Api\ConfigInterface;
use JosephLeedy\CustomFees\Api\Data\CustomFeeTaxDetailsInterface;
use JosephLeedy\CustomFees\Api\Data\CustomFeeTaxDetailsInterfaceFactory;
use JosephLeedy\CustomFees\Model\FeeStatus;
use Magento\Quote\Api\Data\TotalSegmentInterface;
use Magento\Quote\Model\Cart\TotalsConverter;
use Magento\Quote\Model\Quote\Address\Total;

use function array_column;
use function array_filter;
use function array_walk;
use function in_array;

class TotalsConverterPlugin
{
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly CustomFeeTaxDetailsInterfaceFactory $customFeeTaxDetailsFactory,
    ) {}

    /**
     * @phpstan-param TotalSegmentInterface[] $result
     * @phpstan-param Total[] $addressTotals
     * @phpstan-return TotalSegmentInterface[]
     */
    public function afterProcess(TotalsConverter $subject, array $result, array $addressTotals): array
    {
        $customFees = $this->config->getCustomFees();

        if ($customFees === []) {
            return $result;
        }

        $customFeeCodes = array_column(
            array_filter(
                $customFees,
                static fn(array $customFee): bool => FeeStatus::Enabled->equals($customFee['status']),
            ),
            'code',
        );

        array_walk(
            $result,
            function (TotalSegmentInterface $totalSegment) use ($addressTotals, $customFeeCodes): void {
                $segmentCode = $totalSegment->getCode();
                $total = $addressTotals[$segmentCode];

                if (!in_array($segmentCode, $customFeeCodes) || !$total->hasData('tax_details')) {
                    return;
                }

                /** @var CustomFeeTaxDetailsInterface $customFeeTaxDetails */
                $customFeeTaxDetails = $this->customFeeTaxDetailsFactory->create(
                    [
                        'data' => $total->getData('tax_details'),
                    ],
                );

                $totalSegment->getExtensionAttributes()?->setCustomFeeTaxDetails($customFeeTaxDetails);
            },
        );

        return $result;
    }
}
