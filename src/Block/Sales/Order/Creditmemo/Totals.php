<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Block\Sales\Order\Creditmemo;

use JosephLeedy\CustomFees\Api\ConfigInterface;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFee\RefundedInterface as RefundedCustomFee;
use JosephLeedy\CustomFees\Model\DisplayType;
use JosephLeedy\CustomFees\Service\CustomFeesRetriever;
use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Block\Order\Creditmemo\Totals as CreditmemoTotals;
use Magento\Sales\Model\Order\Creditmemo;

use function __;
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
        private readonly ConfigInterface $config,
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
        /** @var string $firstRefundedFeeKey */
        $firstRefundedFeeKey = array_key_first($refundedCustomFees) ?? '';

        array_walk(
            $refundedCustomFees,
            function (RefundedCustomFee $refundedCustomFee) use ($firstRefundedFeeKey): void {
                $displayType = $this->config->getSalesDisplayType($this->getParentBlock()->getOrder()->getStoreId());
                $totalExcludingTax = null;
                $totalIncludingTax = null;

                if ($displayType === DisplayType::ExcludingTax || $displayType === DisplayType::Both) {
                    $totalExcludingTax = $this->buildTotal($refundedCustomFee, false);
                }

                if ($displayType === DisplayType::IncludingTax || $displayType === DisplayType::Both) {
                    $totalIncludingTax = $this->buildTotal($refundedCustomFee, true);
                }

                if ($displayType === DisplayType::Both) {
                    $totalExcludingTax->setLabel(__('%1 Excl. Tax', $totalExcludingTax->getLabel()));

                    $totalIncludingTax->setLabel(__('%1 Incl. Tax', $totalIncludingTax->getLabel()));
                    $totalIncludingTax->setCode($totalIncludingTax->getCode() . '_with_tax');
                }

                if ($totalExcludingTax !== null) {
                    $this->addTotal($totalExcludingTax, $firstRefundedFeeKey);
                }

                if ($totalIncludingTax !== null) {
                    $this->addTotal($totalIncludingTax, $firstRefundedFeeKey);
                }
            },
        );

        return $this;
    }

    private function buildTotal(RefundedCustomFee $refundedCustomFee, bool $includeTax): DataObject
    {
        $totalData = [
            'code' => $refundedCustomFee->getCode(),
            'label' => $refundedCustomFee->formatLabel(),
            'base_value' => $refundedCustomFee->getBaseValue(),
            'value' => $refundedCustomFee->getValue(),
        ];

        if ($includeTax) {
            $totalData['base_value'] = $refundedCustomFee->getBaseValueWithTax();
            $totalData['value'] = $refundedCustomFee->getValueWithTax();
        }

        return $this->dataObjectFactory->create(['data' => $totalData]);
    }

    private function addTotal(DataObject $total, string $firstTotalKey): void
    {
        if ($total->getCode() === $firstTotalKey) {
            if ($this->getBeforeCondition() !== null) {
                $this->getParentBlock()->addTotalBefore($total, $this->getBeforeCondition());
            } else {
                $this->getParentBlock()->addTotal($total, $this->getAfterCondition());
            }
        } else {
            $this->getParentBlock()->addTotal($total);
        }
    }
}
