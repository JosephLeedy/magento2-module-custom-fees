<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Block\Sales\Order;

use JosephLeedy\CustomFees\Model\FeeType;
use JosephLeedy\CustomFees\Service\CustomFeesRetriever;
use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Block\Order\Creditmemo\Totals as CreditmemoTotals;
use Magento\Sales\Block\Order\Totals as OrderTotals;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;

use function __;
use function array_key_first;
use function array_walk;
use function count;
use function round;

/**
 * Initializes and renders Custom Fees order total columns
 *
 * @api
 * @method OrderTotals|CreditmemoTotals getParentBlock()
 * @method string|null getBeforeCondition()
 * @method string|null getAfterCondition()
 */
class Totals extends Template
{
    /**
     * @var array<string, DataObject>
     */
    protected $customFeeTotals = [];

    /**
     * @param array{} $data
     */
    public function __construct(
        Context $context,
        private readonly CustomFeesRetriever $customFeesRetriever,
        protected readonly DataObjectFactory $dataObjectFactory,
        array $data = [],
    ) {
        parent::__construct($context, $data);
    }

    public function getSource(): Order|Creditmemo
    {
        return $this->getParentBlock()->getSource();
    }

    public function initTotals(): self
    {
        $source = $this->getSource();
        /** @var Order $order */
        $order = $source instanceof Order ? $source : $source->getOrder();
        $orderedCustomFees = $this->customFeesRetriever->retrieveOrderedCustomFees($order);

        if (count($orderedCustomFees) === 0) {
            return $this;
        }

        $baseDelta = 1;
        $delta = 1;
        $refundedCustomFeeValues = [
            'base_value' => [],
            'value' => [],
        ];

        if ($source instanceof Creditmemo) {
            $baseDelta = (float) $source->getBaseSubtotal() / (float) $order->getBaseSubtotal();
            $delta = (float) $source->getSubtotal() / (float) $order->getSubtotal();
            $refundedCustomFees = $this->customFeesRetriever->retrieveRefundedCustomFees($source);

            foreach ($refundedCustomFees as $fees) {
                foreach ($fees as $fee) {
                    $refundedCustomFeeValues['base_value'][$fee['code']] = round(
                        (float) ($refundedCustomFeeValues['base_value'][$fee['code']] ?? 0)
                        + (float) $fee['base_value'],
                        2,
                    );
                    $refundedCustomFeeValues['value'][$fee['code']] = round(
                        (float) ($refundedCustomFeeValues['value'][$fee['code']] ?? 0) + (float) $fee['value'],
                        2,
                    );
                }
            }
        }

        $firstOrderedFeeKey = array_key_first($orderedCustomFees);
        $previousFeeCode = '';

        array_walk(
            $orderedCustomFees,
            function (
                array $customFee,
                string|int $key,
            ) use (
                $baseDelta,
                $delta,
                $refundedCustomFeeValues,
                $firstOrderedFeeKey,
                &$previousFeeCode,
            ): void {
                $customFee['label'] = FeeType::Percent->equals($customFee['type']) && $customFee['percent'] !== null
                    && $customFee['show_percentage']
                    ? __($customFee['title'] . ' (%1%)', $customFee['percent'])
                    : __($customFee['title']);
                $customFeeCode = $customFee['code'];

                unset($customFee['title']);

                if ($refundedCustomFeeValues['base_value'] !== []) {
                    $customFee['base_value'] = round(
                        (float) $customFee['base_value']
                        - (float) ($refundedCustomFeeValues['base_value'][$customFeeCode] ?? 0),
                        2,
                    );
                    $customFee['value'] = round(
                        (float) $customFee['value'] - (float) ($refundedCustomFeeValues['value'][$customFeeCode] ?? 0),
                        2,
                    );
                } else {
                    $customFee['base_value'] *= $baseDelta;
                    $customFee['value'] *= $delta;
                }

                /** @var DataObject $total */
                $total = $this->dataObjectFactory->create(
                    [
                        'data' => $customFee,
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

                $this->customFeeTotals[$customFeeCode] = $total;
                $previousFeeCode = $customFeeCode;
            },
        );

        return $this;
    }

    /**
     * @return DataObject[]
     */
    public function getCustomFeeTotals(): array
    {
        return $this->customFeeTotals;
    }
}
