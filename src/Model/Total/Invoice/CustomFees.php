<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Model\Total\Invoice;

use JosephLeedy\CustomFees\Service\CustomFeesRetriever;
use Magento\Sales\Api\Data\InvoiceExtensionInterface;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Invoice\Total\AbstractTotal;

use function array_walk;

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

        $baseSubtotalDelta = (float) $invoice->getBaseSubtotal() / (float) $invoice->getOrder()->getBaseSubtotal();
        $subtotalDelta = (float) $invoice->getSubtotal() / (float) $invoice->getOrder()->getSubtotal();
        $baseTotalCustomFees = 0;
        $totalCustomFees = 0;

        array_walk(
            $customFees,
            static function (&$customFee) use (
                $baseSubtotalDelta,
                $subtotalDelta,
                &$baseTotalCustomFees,
                &$totalCustomFees,
            ): void {
                $customFee['base_value'] *= $baseSubtotalDelta;
                $customFee['value'] *= $subtotalDelta;
                $baseTotalCustomFees += $customFee['base_value'];
                $totalCustomFees += $customFee['value'];
            },
        );

        $invoice->setBaseGrandTotal($invoice->getBaseGrandTotal() + $baseTotalCustomFees);
        $invoice->setGrandTotal($invoice->getGrandTotal() + $totalCustomFees);

        /** @var InvoiceExtensionInterface $invoiceExtensionAttributes */
        $invoiceExtensionAttributes = $invoice->getExtensionAttributes();

        $invoiceExtensionAttributes->setInvoicedCustomFees($customFees);

        return $this;
    }
}
