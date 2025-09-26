<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Block\Sales\Order\Invoice;

use JosephLeedy\CustomFees\Model\FeeType;
use JosephLeedy\CustomFees\Service\CustomFeesRetriever;
use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Block\Order\Invoice\Totals as InvoiceTotals;
use Magento\Sales\Model\Order\Invoice;

use function __;
use function array_key_exists;
use function array_key_first;
use function array_walk;
use function round;

/**
 * Initializes and renders Custom Fees invoice total columns
 *
 * @api
 * @method InvoiceTotals getParentBlock()
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

    public function getSource(): Invoice
    {
        /** @var Invoice $invoice */
        $invoice = $this->getParentBlock()->getSource();

        return $invoice;
    }

    public function initTotals(): self
    {
        $invoice = $this->getSource();
        $order = $invoice->getOrder();
        $customFees = $this->customFeesRetriever->retrieveOrderedCustomFees($order);

        if ($customFees === []) {
            return $this;
        }

        /** @var int|string|null $invoiceId */
        $invoiceId = $invoice->getId();
        $baseDelta = (float) $invoice->getBaseSubtotal() / (float) $order->getBaseSubtotal();
        $delta = (float) $invoice->getSubtotal() / (float) $order->getSubtotal();

        if ($invoiceId !== null) {
            $invoicedCustomFees = $this->customFeesRetriever->retrieveInvoicedCustomFees($order);

            if (array_key_exists($invoiceId, $invoicedCustomFees)) {
                $customFees = $invoicedCustomFees[$invoiceId];
                $baseDelta = 1;
                $delta = 1;
            }
        }

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
                $customFee['base_value'] = round($customFee['base_value'] * $baseDelta, 2);
                $customFee['value'] = round($customFee['value'] * $delta, 2);

                unset(
                    $customFee['invoice_id'],
                    $customFee['title'],
                    $customFee['type'],
                    $customFee['percent'],
                    $customFee['show_percentage'],
                );

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
