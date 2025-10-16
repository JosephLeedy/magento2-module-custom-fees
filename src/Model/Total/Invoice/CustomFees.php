<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Model\Total\Invoice;

use JosephLeedy\CustomFees\Api\Data\CustomOrderFee\InvoicedInterface as InvoicedCustomFee;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFee\InvoicedInterfaceFactory as InvoicedCustomFeeFactory;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFeeInterface;
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
        private readonly InvoicedCustomFeeFactory $invoicedCustomFeeFactory,
        array $data = [],
    ) {
        parent::__construct($data);
    }

    public function collect(Invoice $invoice): self
    {
        parent::collect($invoice);

        $orderedCustomFees = $this->customFeesRetriever->retrieveOrderedCustomFees($invoice->getOrder());

        if ($orderedCustomFees === []) {
            return $this;
        }

        $invoicedCustomFees = array_map(
            fn(CustomOrderFeeInterface $customOrderFee): InvoicedCustomFee
                => $this->invoicedCustomFeeFactory->create(['data' => $customOrderFee->__toArray()]),
            $orderedCustomFees,
        );
        $baseSubtotalDelta = (float) $invoice->getBaseSubtotal() / (float) $invoice->getOrder()->getBaseSubtotal();
        $subtotalDelta = (float) $invoice->getSubtotal() / (float) $invoice->getOrder()->getSubtotal();
        $baseTotalCustomFees = 0;
        $totalCustomFees = 0;

        array_walk(
            $invoicedCustomFees,
            static function (InvoicedCustomFee $invoicedCustomFee) use (
                $baseSubtotalDelta,
                $subtotalDelta,
                &$baseTotalCustomFees,
                &$totalCustomFees,
            ): void {
                $invoicedCustomFee->setBaseValue($invoicedCustomFee->getBaseValue() * $baseSubtotalDelta);
                $invoicedCustomFee->setValue($invoicedCustomFee->getValue() * $subtotalDelta);

                $baseTotalCustomFees += $invoicedCustomFee->getBaseValue();
                $totalCustomFees += $invoicedCustomFee->getValue();
            },
        );

        $invoice->setBaseGrandTotal($invoice->getBaseGrandTotal() + $baseTotalCustomFees);
        $invoice->setGrandTotal($invoice->getGrandTotal() + $totalCustomFees);

        /** @var InvoiceExtensionInterface $invoiceExtensionAttributes */
        $invoiceExtensionAttributes = $invoice->getExtensionAttributes();

        $invoiceExtensionAttributes->setInvoicedCustomFees($invoicedCustomFees);

        return $this;
    }
}
