<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Block\Adminhtml\Sales\Order\Creditmemo\Create;

use JosephLeedy\CustomFees\Model\FeeType;
use JosephLeedy\CustomFees\Service\CustomFeesRetriever;
use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Block\Order\Creditmemo\Totals as CreditmemoTotals;
use Magento\Sales\Model\Order\Creditmemo;

use function __;
use function array_key_exists;
use function array_walk;

/**
 * Initializes and renders Custom Fees new credit memo total columns
 *
 * @api
 * @method CreditmemoTotals getParentBlock()
 * @method string|null getBeforeCondition()
 * @method string|null getAfterCondition()
 */
class Totals extends Template
{
    /**
     * @var array<string, DataObject>
     */
    private array $customFeeTotals = [];

    /**
     * @param mixed[] $data
     */
    public function __construct(
        Context $context,
        private readonly CustomFeesRetriever $customFeesRetriever,
        private readonly DataObjectFactory $dataObjectFactory,
        array $data = [],
    ) {
        parent::__construct($context, $data);
    }

    public function initTotals(): self
    {
        $creditmemo = $this->getParentBlock()->getSource();

        if ($creditmemo === null) {
            return $this;
        }

        $orderedCustomFees = $this->customFeesRetriever->retrieveOrderedCustomFees($creditmemo->getOrder());

        if (count($orderedCustomFees) === 0) {
            return $this;
        }

        $customFeeTotal = $this->dataObjectFactory->create(
            [
                'data' => [
                    'code' => 'custom_fees',
                    'block_name' => $this->getNameInLayout(),
                ],
            ],
        );

        $this->getParentBlock()->addTotal($customFeeTotal);

        $this->buildCustomFeeTotals($orderedCustomFees);

        return $this;
    }

    /**
     * @return array<string, DataObject>
     */
    public function getCustomFeeTotals(): array
    {
        return $this->customFeeTotals;
    }

    public function formatValue(float $value): string
    {
        $order = $this->getParentBlock()->getSource()->getOrder();

        return $order
            ->getOrderCurrency()
            ->formatPrecision(
                $value,
                2,
                [
                    'display' => 1,
                ],
                false,
            );
    }

    /**
     * @param array<string, array{
     *     code: string,
     *     title: string,
     *     type: value-of<FeeType>,
     *     percent: float|null,
     *     show_percentage: bool,
     *     base_value: float,
     *     value: float,
     * }> $customFees
     */
    private function buildCustomFeeTotals(array $customFees): void
    {
        /** @var Creditmemo $creditmemo */
        $creditmemo = $this->getParentBlock()->getSource();
        $order = $creditmemo->getOrder();
        $existingRefundedCustomFees = $this->customFeesRetriever->retrieveRefundedCustomFees($order);
        $existingRefundedCustomFeeValues = [
            'base_value' => [],
            'value' => [],
        ];
        $creditmemoExtensionAttributes = $creditmemo->getExtensionAttributes();
        $refundedCustomFees = $creditmemoExtensionAttributes?->getRefundedCustomFees() ?? [];
        $baseDelta = (float) $creditmemo->getBaseSubtotal() / (float) $order->getBaseSubtotal();
        $delta = (float) $creditmemo->getSubtotal() / (float) $order->getSubtotal();

        foreach ($existingRefundedCustomFees as $fees) {
            foreach ($fees as $fee) {
                $existingRefundedCustomFeeValues['base_value'][$fee['code']] = round(
                    (float) ($existingRefundedCustomFeeValues['base_value'][$fee['code']] ?? 0)
                    + (float) $fee['base_value'],
                    2,
                );
                $existingRefundedCustomFeeValues['value'][$fee['code']] = round(
                    (float) ($existingRefundedCustomFeeValues['value'][$fee['code']] ?? 0) + (float) $fee['value'],
                    2,
                );
            }
        }

        array_walk(
            $customFees,
            function (array $customFee) use (
                $existingRefundedCustomFeeValues,
                $refundedCustomFees,
                $baseDelta,
                $delta,
            ): void {
                $customFeeCode = $customFee['code'];
                $baseValue = $customFee['base_value'];
                $value = $customFee['value'];
                $label = FeeType::Percent->equals($customFee['type'])
                    && $customFee['percent'] !== null
                    && $customFee['show_percentage']
                    ? __('Refund %1 (%2%)', $customFee['title'], $customFee['percent'])
                    : __('Refund %1', $customFee['title']);

                if ($existingRefundedCustomFeeValues['base_value'] !== []) {
                    $baseValue = round(
                        (float) $baseValue
                        - (float) ($existingRefundedCustomFeeValues['base_value'][$customFeeCode] ?? 0),
                        2,
                    );
                    $value = round(
                        (float) $value - (float) ($existingRefundedCustomFeeValues['value'][$customFeeCode] ?? 0),
                        2,
                    );
                } else {
                    $baseValue *= $baseDelta;
                    $value *= $delta;
                }

                // Replace refunded custom fee values with those set on the credit memo by its creator, if available
                if (
                    array_key_exists($customFeeCode, $refundedCustomFees)
                    && $refundedCustomFees[$customFeeCode] !== $customFee['base_value']
                ) {
                    $baseValue = $refundedCustomFees[$customFeeCode];
                    $value = $refundedCustomFees[$customFeeCode];
                }

                $this->customFeeTotals[$customFeeCode] = $this->dataObjectFactory->create(
                    [
                        'data' => [
                            'code' => $customFeeCode,
                            'label' => $label,
                            'base_value' => $baseValue,
                            'value' => $value,
                        ],
                    ],
                );
            },
        );
    }
}
