<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Model\Sales\Pdf;

use JosephLeedy\CustomFees\Api\Data\CustomOrderFeeInterface;
use JosephLeedy\CustomFees\Service\CustomFeesRetriever;
use Magento\Framework\Phrase;
use Magento\Sales\Model\Order\Pdf\Total\DefaultTotal;
use Magento\Tax\Helper\Data;
use Magento\Tax\Model\Calculation;
use Magento\Tax\Model\ResourceModel\Sales\Order\Tax\CollectionFactory;

use function array_map;
use function array_reduce;
use function count;
use function filter_var;

use const FILTER_VALIDATE_BOOLEAN;

/**
 * @method int|null getFontSize()
 * @method CustomFees setDisplayZero(string $canDisplayZero)
 * @method string getDisplayZero()
 */
class CustomFees extends DefaultTotal
{
    /**
     * @var array{}|array<string, CustomOrderFeeInterface>|null
     */
    private array|null $customFees = null;

    /**
     * @param mixed[] $data
     */
    public function __construct(
        Data $taxHelper,
        Calculation $taxCalculation,
        CollectionFactory $ordersFactory,
        private readonly CustomFeesRetriever $customFeesRetriever,
        array $data = [],
    ) {
        parent::__construct($taxHelper, $taxCalculation, $ordersFactory, $data);
    }

    /**
     * @return array{}|array{array{amount: string, label: Phrase, font_size: int}}
     */
    public function getTotalsForDisplay(): array
    {
        $allCustomFees = $this->getCustomFees();
        $totalCustomFeesAmount = $this->calculateTotalCustomFeeValue();

        if (
            count($allCustomFees) === 0
            || (
                !filter_var($this->getDisplayZero(), FILTER_VALIDATE_BOOLEAN)
                && $totalCustomFeesAmount === 0.0
            )
        ) {
            return [];
        }

        $fontSize = $this->getFontSize() ?? 7;
        $totals = array_map(
            fn(CustomOrderFeeInterface $customOrderFee): array => [
                'amount' => $this->getOrder()->formatPriceTxt($customOrderFee->getValue()),
                'label' => ((string) $customOrderFee->formatLabel()) . ':',
                'font_size' => $fontSize,
            ],
            $allCustomFees,
        );

        return $totals;
    }

    public function canDisplay(): bool
    {
        return filter_var($this->getDisplayZero(), FILTER_VALIDATE_BOOLEAN)
            || $this->calculateTotalCustomFeeValue() !== 0.0;
    }

    /**
     * @return array{}|array<string, CustomOrderFeeInterface>
     */
    private function getCustomFees(): array
    {
        if ($this->customFees !== null) {
            return $this->customFees;
        }

        $this->customFees = $this->customFeesRetriever->retrieveOrderedCustomFees($this->getOrder());

        return $this->customFees;
    }

    private function calculateTotalCustomFeeValue(): float
    {
        $allCustomFees = $this->getCustomFees();
        $totalCustomFeesAmount = array_reduce(
            $allCustomFees,
            static fn(float $total, CustomOrderFeeInterface $customOrderFee): float
                => $total + $customOrderFee->getValue(),
            0.0,
        );

        return $totalCustomFeesAmount;
    }
}
