<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Plugin\Quote\Api\Data;

use JosephLeedy\CustomFees\Api\ConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Result\Layout;
use Magento\Quote\Api\Data\TotalSegmentExtensionFactory;
use Magento\Quote\Api\Data\TotalSegmentExtensionInterface;
use Magento\Quote\Api\Data\TotalSegmentInterface;
use Magento\Quote\Api\Data\TotalSegmentInterfaceFactory;
use Magento\Quote\Api\Data\TotalsInterface;

use function __;
use function array_column;
use function array_filter;
use function array_key_exists;
use function count;
use function in_array;

use const ARRAY_FILTER_USE_KEY;

class TotalsInterfacePlugin
{
    public function __construct(
        private readonly Layout $layout,
        private readonly TotalSegmentInterfaceFactory $totalSegmentFactory,
        private readonly TotalSegmentExtensionFactory $totalSegmentExtensionFactory,
        private readonly ConfigInterface $config,
    ) {}

    /**
     * Adds a total segment for custom fees in Hyvä Checkout
     *
     * @param TotalSegmentInterface[]|null $result
     * @return TotalSegmentInterface[]|null
     * @see TotalsInterface::getTotalSegments
     */
    public function afterGetTotalSegments(TotalsInterface $subject, ?array $result): ?array
    {
        $layoutHandles = $this->layout->getLayout()->getUpdate()->getHandles();

        if (
            $result === null
            || !in_array('hyva_checkout', $layoutHandles, true)
            || array_key_exists('custom_fees', $result)
        ) {
            return $result;
        }

        /** @var TotalSegmentInterface $customFeesTotalSegment */
        $customFeesTotalSegment = $this->totalSegmentFactory->create();
        /** @var TotalSegmentExtensionInterface $customFeesTotalSegmentExtension */
        $customFeesTotalSegmentExtension = $this->totalSegmentExtensionFactory->create();

        try {
            $customFees = $this->config->getCustomFees();
        } catch (LocalizedException) {
            $customFees = [];
        }

        if (count($customFees) === 0) {
            return $result;
        }

        $customFeeCodes = array_column($customFees, 'code');
        $customFeesTotalSegments = array_filter(
            $result,
            static fn (string $key): bool => in_array($key, $customFeeCodes, true),
            ARRAY_FILTER_USE_KEY
        );
        $result = array_diff_key($result, $customFeesTotalSegments);

        $customFeesTotalSegmentExtension->setCustomFeeSegments($customFeesTotalSegments);

        $customFeesTotalSegment->setCode('custom_fees');
        $customFeesTotalSegment->setTitle((string)__('Custom Fees'));
        $customFeesTotalSegment->setValue(0);
        $customFeesTotalSegment->setArea();
        $customFeesTotalSegment->setExtensionAttributes($customFeesTotalSegmentExtension);

        $result['custom_fees'] = $customFeesTotalSegment;

        return $result;
    }
}
