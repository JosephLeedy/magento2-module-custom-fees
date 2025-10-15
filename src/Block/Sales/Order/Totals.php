<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Block\Sales\Order;

use JosephLeedy\CustomFees\Api\Data\CustomOrderFeeInterface;
use JosephLeedy\CustomFees\Service\CustomFeesRetriever;
use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Block\Order\Totals as OrderTotals;
use Magento\Sales\Model\Order;

use function array_key_first;
use function array_walk;
use function count;

/**
 * Initializes and renders Custom Fees order total columns
 *
 * @api
 * @method OrderTotals getParentBlock()
 * @method string|null getBeforeCondition()
 * @method string|null getAfterCondition()
 */
class Totals extends Template
{
    /**
     * @param array{} $data
     */
    public function __construct(
        Context $context,
        private readonly CustomFeesRetriever $customFeesRetriever,
        private readonly DataObjectFactory $dataObjectFactory,
        array $data = [],
    ) {
        parent::__construct($context, $data);
    }

    public function getSource(): Order
    {
        return $this->getParentBlock()->getSource();
    }

    public function initTotals(): self
    {
        $orderedCustomFees = $this->customFeesRetriever->retrieveOrderedCustomFees($this->getSource());

        if (count($orderedCustomFees) === 0) {
            return $this;
        }

        $firstOrderedFeeKey = array_key_first($orderedCustomFees);
        $previousFeeCode = '';

        array_walk(
            $orderedCustomFees,
            function (
                CustomOrderFeeInterface $customOrderFee,
                string $key,
            ) use (
                $firstOrderedFeeKey,
                &$previousFeeCode,
            ): void {
                $customFeeCode = $customOrderFee->getCode();
                /** @var DataObject $total */
                $total = $this->dataObjectFactory->create(
                    [
                        'data' => [
                            'code' => $customFeeCode,
                            'label' => $customOrderFee->formatLabel(),
                            'base_value' => $customOrderFee->getBaseValue(),
                            'value' => $customOrderFee->getValue(),
                        ],
                    ],
                );

                if ($key === $firstOrderedFeeKey) {
                    if ($this->getBeforeCondition() !== null) {
                        $this->getParentBlock()->addTotalBefore($total, $this->getBeforeCondition());
                    } else {
                        $this->getParentBlock()->addTotal($total, $this->getAfterCondition());
                    }
                } else {
                    $this->getParentBlock()->addTotal($total, $previousFeeCode);
                }

                $previousFeeCode = $customFeeCode;
            },
        );

        return $this;
    }
}
