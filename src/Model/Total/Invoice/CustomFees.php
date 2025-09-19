<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Model\Total\Invoice;

use JosephLeedy\CustomFees\Model\FeeType;
use JosephLeedy\CustomFees\Service\CustomFeesRetriever;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Invoice\Total\AbstractTotal;

use function array_column;
use function array_key_exists;
use function array_sum;

class CustomFees extends AbstractTotal
{
    /**
     * @param mixed[] $data
     */
    public function __construct(
        private readonly CustomFeesRetriever $customFeesRetriever,
        array $data = [],
    ) {
        parent::__construct($data);
    }

    public function collect(Invoice $invoice): self
    {
        parent::collect($invoice);

        $customFees = $this->customFeesRetriever->retrieveOrderedCustomFees($invoice->getOrder());

        if (count($customFees) === 0) {
            return $this;
        }

        $adjustedCustomFeeCount = $this->adjustCustomFees($invoice, $customFees);
        $baseTotalCustomFees = array_sum(array_column($customFees, 'base_value'));
        $totalCustomFees = array_sum(array_column($customFees, 'value'));
        $baseInvoicedCustomFeeAmount = $baseTotalCustomFees;
        $totalInvoicedCustomFeeAmount = $totalCustomFees;

        if ($adjustedCustomFeeCount === 0) {
            $baseInvoicedCustomFeeAmount = (
                (float) $invoice->getBaseSubtotal() / (float) $invoice->getOrder()->getBaseSubtotal()
            ) * $baseTotalCustomFees;
            $totalInvoicedCustomFeeAmount = (
                (float) $invoice->getSubtotal() / (float) $invoice->getOrder()->getSubtotal()
            ) * $totalCustomFees;
        }

        $invoice->setBaseGrandTotal($invoice->getBaseGrandTotal() + $baseInvoicedCustomFeeAmount);
        $invoice->setGrandTotal($invoice->getGrandTotal() + $totalInvoicedCustomFeeAmount);

        return $this;
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
    private function adjustCustomFees(Invoice $invoice, array &$customFees): int
    {
        $adjustedCustomFeeCount = 0;
        $existingInvoicedCustomFees = $this->customFeesRetriever->retrieveInvoicedCustomFees($invoice);
        $invoiceId = (int) $invoice->getEntityId();

        if (!array_key_exists($invoiceId, $existingInvoicedCustomFees)) {
            return $adjustedCustomFeeCount;
        }

        $existingInvoicedCustomFees = $existingInvoicedCustomFees[$invoiceId];

        array_walk(
            $customFees,
            static function (&$customFee) use ($existingInvoicedCustomFees, &$adjustedCustomFeeCount) {
                $customFeeCode = $customFee['code'];

                if (!array_key_exists($customFeeCode, $existingInvoicedCustomFees)) {
                    return;
                }

                $customFee['base_value'] -= (float) $existingInvoicedCustomFees[$customFeeCode]['base_value'];
                $customFee['value'] -= (float) $existingInvoicedCustomFees[$customFeeCode]['value'];

                $adjustedCustomFeeCount++;
            },
        );

        return $adjustedCustomFeeCount;
    }
}
