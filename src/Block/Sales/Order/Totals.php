<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Block\Sales\Order;

use JosephLeedy\CustomFees\Api\ConfigInterface;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFeeInterface;
use JosephLeedy\CustomFees\Model\DisplayType;
use JosephLeedy\CustomFees\Service\CustomFeesRetriever;
use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Block\Order\Totals as OrderTotals;
use Magento\Sales\Model\Order;

use function array_key_first;
use function array_walk;

/**
 * Initializes and renders Custom Fees order total columns
 *
 * @api
 * @method OrderTotals getParentBlock()
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
        private readonly ConfigInterface $config,
        array $data = [],
    ) {
        parent::__construct($context, $data);
    }

    public function getSource(): Order
    {
        return $this->getParentBlock()->getSource();
    }

    public function initTotals(): self
    {
        $orderedCustomFees = $this->customFeesRetriever->retrieveOrderedCustomFees($this->getSource());

        if ($orderedCustomFees === []) {
            return $this;
        }

        $firstOrderedFeeKey = array_key_first($orderedCustomFees);

        array_walk(
            $orderedCustomFees,
            function (CustomOrderFeeInterface $customOrderFee) use ($firstOrderedFeeKey): void {
                $displayType = $this->config->getSalesDisplayType($this->getParentBlock()->getOrder()->getStoreId());
                $totalExcludingTax = null;
                $totalIncludingTax = null;

                if ($displayType === DisplayType::ExcludingTax || $displayType === DisplayType::Both) {
                    $totalExcludingTax = $this->buildTotal($customOrderFee, false);
                }

                if ($displayType === DisplayType::IncludingTax || $displayType === DisplayType::Both) {
                    $totalIncludingTax = $this->buildTotal($customOrderFee, true);
                }

                if ($displayType === DisplayType::Both) {
                    $totalExcludingTax->setLabel(__('%1 Excl. Tax', $totalExcludingTax->getLabel()));

                    $totalIncludingTax->setLabel(__('%1 Incl. Tax', $totalIncludingTax->getLabel()));
                    $totalIncludingTax->setCode($totalIncludingTax->getCode() . '_with_tax');
                }

                if ($totalExcludingTax !== null) {
                    $this->addTotal($totalExcludingTax, $firstOrderedFeeKey);
                }

                if ($totalIncludingTax !== null) {
                    $this->addTotal($totalIncludingTax, $firstOrderedFeeKey);
                }
            },
        );

        return $this;
    }

    private function buildTotal(CustomOrderFeeInterface $customOrderFee, bool $includeTax): DataObject
    {
        $totalData = [
            'code' => $customOrderFee->getCode(),
            'label' => $customOrderFee->formatLabel(),
            'base_value' => $customOrderFee->getBaseValue(),
            'value' => $customOrderFee->getValue(),
        ];

        if ($includeTax) {
            $totalData['base_value'] = $customOrderFee->getBaseValueWithTax();
            $totalData['value'] = $customOrderFee->getValueWithTax();
        }

        return $this->dataObjectFactory->create(['data' => $totalData]);
    }

    private function addTotal(DataObject $total, string $firstTotalKey): void
    {
        if ($total->getCode() === $firstTotalKey) {
            if ($this->getBeforeCondition() !== null) {
                $this->getParentBlock()->addTotalBefore($total, $this->getBeforeCondition());
            } else {
                $this->getParentBlock()->addTotal($total, $this->getAfterCondition());
            }
        } else {
            $this->getParentBlock()->addTotal($total);
        }
    }
}
