<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Block\Adminhtml\Sales\Order\Creditmemo\Create;

use JosephLeedy\CustomFees\Api\Data\CustomOrderFee\RefundedInterface as RefundedCustomFee;
use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Block\Order\Creditmemo\Totals as CreditmemoTotals;
use Magento\Sales\Model\Order\Creditmemo;

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

        $customFeeTotal = $this->dataObjectFactory->create(
            [
                'data' => [
                    'code' => 'custom_fees',
                    'block_name' => $this->getNameInLayout(),
                ],
            ],
        );

        $this->getParentBlock()->addTotal($customFeeTotal);

        $this->buildCustomFeeTotals();

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

    private function buildCustomFeeTotals(): void
    {
        /** @var Creditmemo $creditmemo */
        $creditmemo = $this->getParentBlock()->getSource();
        /** @var array<string, RefundedCustomFee> $refundedCustomFees */
        $refundedCustomFees = $creditmemo->getExtensionAttributes()?->getRefundedCustomFees() ?? [];

        array_walk(
            $refundedCustomFees,
            function (RefundedCustomFee $refundedCustomFee): void {
                $this->customFeeTotals[$refundedCustomFee->getCode()] = $this->dataObjectFactory->create(
                    [
                        'data' => [
                            'code' => $refundedCustomFee->getCode(),
                            'label' => $refundedCustomFee->formatLabel('Refund'),
                            'base_value' => $refundedCustomFee->getBaseValue(),
                            'value' => $refundedCustomFee->getValue(),
                        ],
                    ],
                );
            },
        );
    }
}
