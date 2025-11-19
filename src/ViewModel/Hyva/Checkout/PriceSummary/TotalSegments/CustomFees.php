<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\ViewModel\Hyva\Checkout\PriceSummary\TotalSegments;

use JosephLeedy\CustomFees\Api\ConfigInterface;
use JosephLeedy\CustomFees\Model\DisplayType;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Quote\Api\Data\TotalSegmentInterface;

class CustomFees implements ArgumentInterface
{
    public function __construct(private readonly ConfigInterface $config) {}

    public function getDisplayType(): DisplayType
    {
        return $this->config->getCartDisplayType();
    }

    public function getValueWithTax(TotalSegmentInterface $totalSegment): float
    {
        return $totalSegment->getExtensionAttributes()?->getCustomFeeTaxDetails()?->getValueWithTax()
            ?? $totalSegment->getValue();
    }
}
