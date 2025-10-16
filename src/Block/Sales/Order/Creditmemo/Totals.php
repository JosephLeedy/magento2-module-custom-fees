<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Block\Sales\Order\Creditmemo;

use JosephLeedy\CustomFees\Api\Data\CustomOrderFee\RefundedInterface as RefundedCustomFee;
use JosephLeedy\CustomFees\Service\CustomFeesRetriever;
use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Block\Order\Creditmemo\Totals as CreditmemoTotals;
use Magento\Sales\Model\Order\Creditmemo;

use function array_key_exists;
use function array_key_first;
use function array_walk;

/**
 * Initializes and renders Custom Fees credit memo total columns
 *
 * @api
 * @method CreditmemoTotals getParentBlock()
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

    public function getSource(): Creditmemo
    {
        return $this->getParentBlock()->getSource();
    }

    public function initTotals(): self
    {
        $refundedCustomFees = $this->customFeesRetriever->retrieveRefundedCustomFees($this->getSource()->getOrder());
        /** @var int|string $creditMemoId */
        $creditMemoId = $this->getSource()->getId();

        if (!array_key_exists($creditMemoId, $refundedCustomFees)) {
            return $this;
        }

        /** @var array<string, RefundedCustomFee> $refundedCustomFees */
        $refundedCustomFees = $refundedCustomFees[$creditMemoId];
        $firstFeeKey = array_key_first($refundedCustomFees);
        $previousFeeCode = '';

        array_walk(
            $refundedCustomFees,
            function (RefundedCustomFee $refundedCustomFee, string $key) use ($firstFeeKey, &$previousFeeCode): void {
                $customFeeCode = $refundedCustomFee->getCode();
                /** @var DataObject $total */
                $total = $this->dataObjectFactory->create(
                    [
                        'data' => [
                            'code' => $customFeeCode,
                            'label' => $refundedCustomFee->formatLabel(),
                            'base_value' => $refundedCustomFee->getBaseValue(),
                            'value' => $refundedCustomFee->getValue(),
                        ],
                    ],
                );

                if ($key === $firstFeeKey) {
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
