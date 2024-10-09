<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Model\Total\Invoice;

use JosephLeedy\CustomFees\Service\CustomFeesRetriever;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Invoice\Total\AbstractTotal;

use function array_column;
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

        $customFees = $this->customFeesRetriever->retrieve($invoice->getOrder());

        if (count($customFees) === 0) {
            return $this;
        }

        $baseTotalCustomFees = array_sum(array_column($customFees, 'base_value'));
        $totalCustomFees = array_sum(array_column($customFees, 'value'));

        $invoice->setBaseGrandTotal($invoice->getBaseGrandTotal() + $baseTotalCustomFees);
        $invoice->setGrandTotal($invoice->getGrandTotal() + $totalCustomFees);

        return $this;
    }
}
