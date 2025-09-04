<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Block\Sales\Order\Creditmemo;

use JosephLeedy\CustomFees\Model\FeeType;
use JosephLeedy\CustomFees\Service\CustomFeesRetriever;
use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Block\Order\Creditmemo\Totals as CreditmemoTotals;
use Magento\Sales\Model\Order\Creditmemo;

use function __;
use function array_key_first;
use function array_walk;
use function count;

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
        $customFees = $this->customFeesRetriever->retrieveRefundedCustomFees($this->getSource());

        if (count($customFees) === 0) {
            return $this;
        }

        $baseDelta = 1;
        $delta = 1;
        $firstFeeKey = array_key_first($customFees);
        $previousFeeCode = '';

        array_walk(
            $customFees,
            function (
                array $customFee,
                string|int $key,
            ) use (
                $baseDelta,
                $delta,
                $firstFeeKey,
                &$previousFeeCode,
            ): void {
                $customFee['label'] = FeeType::Percent->equals($customFee['type'])
                    && $customFee['percent'] !== null
                    && $customFee['show_percentage']
                    ? __($customFee['title'] . ' (%1%)', $customFee['percent'])
                    : __($customFee['title']);
                $customFee['base_value'] *= $baseDelta;
                $customFee['value'] *= $delta;

                unset($customFee['title']);

                /** @var DataObject $total */
                $total = $this->dataObjectFactory->create(
                    [
                        'data' => $customFee,
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

                $previousFeeCode = $customFee['code'];
            },
        );

        return $this;
    }
}
