<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Model\Sales\Pdf;

use JosephLeedy\CustomFees\Api\ConfigInterface;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFeeInterface;
use JosephLeedy\CustomFees\Model\DisplayType;
use JosephLeedy\CustomFees\Model\FeeType;
use JosephLeedy\CustomFees\Service\CustomFeesRetriever;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Pdf\Total\DefaultTotal;
use Magento\Tax\Helper\Data;
use Magento\Tax\Model\Calculation;
use Magento\Tax\Model\ResourceModel\Sales\Order\Tax\CollectionFactory;

use function array_map;
use function array_merge;
use function array_reduce;
use function array_values;
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
        private readonly ConfigInterface $config,
        array $data = [],
    ) {
        parent::__construct($taxHelper, $taxCalculation, $ordersFactory, $data);
    }

    /**
     * @return array{}|array{array{amount: string, label: string, font_size: int}}
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

        $order = $this->getOrder();
        $fontSize = $this->getFontSize() ?? 7;
        $totals = array_map(
            fn(CustomOrderFeeInterface $customOrderFee): array
                => match ($this->config->getSalesDisplayType($order->getStoreId())) {
                    DisplayType::ExcludingTax => [
                        [
                            'amount' => $order->formatPriceTxt($customOrderFee->getValue()),
                            'label' => ((string) $customOrderFee->formatLabel()) . ':',
                            'font_size' => $fontSize,
                        ],
                    ],
                    DisplayType::IncludingTax => [
                        [
                            'amount' => $order->formatPriceTxt($customOrderFee->getValueWithTax()),
                            'label' => ((string) $customOrderFee->formatLabel()) . ':',
                            'font_size' => $fontSize,
                        ],
                    ],
                    DisplayType::Both => [
                        [
                            'amount' => $order->formatPriceTxt($customOrderFee->getValue()),
                            'label' => ((string) $customOrderFee->formatLabel(suffix: '(Excl. Tax)')) . ':',
                            'font_size' => $fontSize,
                        ],
                        [
                            'amount' => $order->formatPriceTxt($customOrderFee->getValueWithTax()),
                            'label' => ((string) $customOrderFee->formatLabel(suffix: '(Incl. Tax)')) . ':',
                            'font_size' => $fontSize,
                        ],
                    ],
                },
            $allCustomFees,
        );
        $totals = array_merge(...array_values($totals));

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

        /** @var Order $order */
        $order = $this->getOrder();
        $customFees = match (true) {
            $this->getSource() instanceof Invoice => $this->customFeesRetriever->retrieveInvoicedCustomFees($order),
            $this->getSource() instanceof Creditmemo => $this->customFeesRetriever->retrieveRefundedCustomFees($order),
            default => $this->customFeesRetriever->retrieveOrderedCustomFees($order),
        };

        if ($this->getSource() instanceof Invoice || $this->getSource() instanceof Creditmemo) {
            /** @var int|string $entityId */
            $entityId = $this->getSource()->getEntityId();
            $customFees = $customFees[$entityId] ?? [];
        }

        /**
         * @var array{}|array<string, array{
         *     code: string,
         *     title: string,
         *     type: value-of<FeeType>,
         *     percent: float|null,
         *     show_percentage: bool,
         *     base_value: float,
         *     value: float,
         *     invoice_id?: int,
         *     credit_memo_id?: int,
         * }> $customFees
         */

        $this->customFees = $customFees;

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
