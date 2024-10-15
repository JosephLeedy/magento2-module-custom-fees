<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Model\Total\Creditmemo;

use JosephLeedy\CustomFees\Service\CustomFeesRetriever;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Creditmemo\Total\AbstractTotal;

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

    public function collect(Creditmemo $creditmemo): self
    {
        parent::collect($creditmemo);

        $customFees = $this->customFeesRetriever->retrieve($creditmemo->getOrder());

        if (count($customFees) === 0) {
            return $this;
        }

        $baseTotalCustomFees = array_sum(array_column($customFees, 'base_value'));
        $totalCustomFees = array_sum(array_column($customFees, 'value'));

        $creditmemo->setBaseGrandTotal($creditmemo->getBaseGrandTotal() + $baseTotalCustomFees);
        $creditmemo->setGrandTotal($creditmemo->getGrandTotal() + $totalCustomFees);

        return $this;
    }
}
