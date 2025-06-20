<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Model\Sales\Pdf;

use JosephLeedy\CustomFees\Model\FeeType;
use JosephLeedy\CustomFees\Service\CustomFeesRetriever;
use Magento\Framework\Phrase;
use Magento\Sales\Model\Order\Pdf\Total\DefaultTotal;
use Magento\Tax\Helper\Data;
use Magento\Tax\Model\Calculation;
use Magento\Tax\Model\ResourceModel\Sales\Order\Tax\CollectionFactory;

use function __;
use function array_column;
use function array_map;
use function array_sum;
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
     * @var array{}|array<string, array{
     *     code: string,
     *     title: string,
     *     type: value-of<FeeType>,
     *     percent: float|null,
     *     base_value: float,
     *     value: float,
     * }>|null
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
        $totalCustomFeesAmount = array_sum(array_column($allCustomFees, 'value'));

        if (
            count($allCustomFees) === 0
            || (
                !filter_var($this->getDisplayZero(), FILTER_VALIDATE_BOOLEAN)
                && $totalCustomFeesAmount === 0
            )
        ) {
            return [];
        }

        $fontSize = $this->getFontSize() ?? 7;
        $totals = array_map(
            fn(array $customFees): array => [
                'amount' => $this->getOrder()->formatPriceTxt($customFees['value']),
                'label' => (
                    FeeType::Percent->equals($customFees['type']) && $customFees['percent'] !== null
                        ? __($customFees['title'] . ' (%1%)', $customFees['percent'])
                        : __($customFees['title'])
                ) . ':',
                'font_size' => $fontSize,
            ],
            $allCustomFees,
        );

        return $totals;
    }

    public function canDisplay(): bool
    {
        $allCustomFees = $this->getCustomFees();
        $totalCustomFeesAmount = array_sum(array_column($allCustomFees, 'value'));

        return filter_var($this->getDisplayZero(), FILTER_VALIDATE_BOOLEAN) || $totalCustomFeesAmount !== 0;
    }

    /**
     * @return array{}|array<string, array{
     *     code: string,
     *     title: string,
     *     type: value-of<FeeType>,
     *     percent: float|null,
     *     base_value: float,
     *     value: float,
     * }>
     */
    private function getCustomFees(): array
    {
        if ($this->customFees !== null) {
            return $this->customFees;
        }

        $this->customFees = $this->customFeesRetriever->retrieve($this->getOrder());

        return $this->customFees;
    }
}
