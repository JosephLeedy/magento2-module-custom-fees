<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Plugin\Sales\Block\Order;

use JosephLeedy\CustomFees\Service\CustomFeesRetriever;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\TotalInterface;
use Magento\Sales\Block\Order\Totals;

use function array_column;
use function array_filter;
use function array_intersect;
use function array_keys;
use function array_slice;
use function count;
use function in_array;

use const ARRAY_FILTER_USE_KEY;

class TotalsPlugin
{
    /**
     * @var string[]
     */
    private array $hyvaLayoutHandles = [
        'hyva_sales_guest_view',
        'hyva_sales_guest_print',
        'hyva_sales_guest_invoice',
        'hyva_sales_guest_printinvoice',
        'hyva_sales_guest_creditmemo',
        'hyva_sales_guest_printcreditmemo',
        'hyva_sales_order_view',
        'hyva_sales_order_print',
        'hyva_sales_order_invoice',
        'hyva_sales_order_printinvoice',
        'hyva_sales_order_creditmemo',
        'hyva_sales_order_printcreditmemo',
    ];

    public function __construct(private readonly CustomFeesRetriever $customFeesRetriever) {}

    /**
     * Ensure that custom fees totals are rendered after tax totals in Hyvä Sales Order frontend
     *
     * **Note**: this is a terrible work-around for the fact that Hyvä always adds the tax totals last, no matter what
     * (setting an after condition doesn't even fix it).
     *
     * @param TotalInterface[]|null $result
     * @return TotalInterface[]|null
     * @see Totals::getTotals
     */
    public function afterGetTotals(Totals $subject, ?array $result): ?array
    {
        if ($result === null || !array_key_exists('tax', $result)) {
            return $result;
        }

        try {
            $layoutHandles = $subject->getLayout()->getUpdate()->getHandles();
        } catch (LocalizedException) {
            return $result;
        }

        if (count(array_intersect($this->hyvaLayoutHandles, $layoutHandles)) === 0) {
            return $result;
        }

        $customOrderFees = $this->customFeesRetriever->retrieveOrderedCustomFees($subject->getOrder());

        if (count($customOrderFees) === 0) {
            return $result;
        }

        $customFeeCodes = array_column($customOrderFees, 'code');
        $customFees = array_filter(
            $result,
            static fn(string $totalCode): bool => in_array($totalCode, $customFeeCodes, true),
            ARRAY_FILTER_USE_KEY,
        );

        if (count($customFees) === 0) {
            return $result;
        }

        $resultWithoutCustomFees = array_diff_key($result, $customFees);
        $offset = array_search('tax', array_keys($resultWithoutCustomFees), true) + 1;

        return
            array_slice($resultWithoutCustomFees, 0, $offset, true)
            + $customFees
            + array_slice(array: $resultWithoutCustomFees, offset: $offset, preserve_keys: true);
    }
}
