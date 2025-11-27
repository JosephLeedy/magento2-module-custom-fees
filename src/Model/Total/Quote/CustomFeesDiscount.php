<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Model\Total\Quote;

use JosephLeedy\CustomFees\Api\Data\CustomOrderFeeInterface;
use JosephLeedy\CustomFees\Service\CustomFeesDiscountApplier;
use Magento\Framework\Phrase;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Address\Total\AbstractTotal;
use Magento\SalesRule\Model\Quote\Discount;
use Magento\SalesRule\Model\Validator;

use function __;
use function array_walk;

class CustomFeesDiscount extends AbstractTotal
{
    public function __construct(
        private readonly CustomFeesDiscountApplier $discountApplier,
        private readonly Validator $validator,
    ) {}

    public function collect(Quote $quote, ShippingAssignmentInterface $shippingAssignment, Total $total): self
    {
        parent::collect($quote, $shippingAssignment, $total);

        $items = $shippingAssignment->getItems();

        if ($items === []) {
            return $this;
        }

        /** @var Address $address */
        $address = $shippingAssignment->getShipping()->getAddress();

        $this->validator->reset($address);

        $this->discountApplier->applyCustomFeeDiscounts($address);

        $customFees = $quote->getExtensionAttributes()?->getCustomFees() ?? [];

        if ($customFees === []) {
            return $this;
        }

        $baseTotalCustomFeeAmount = 0.00;
        $totalCustomFeeAmount = 0.00;
        $baseTotalCustomFeeDiscount = 0.00;
        $totalCustomFeeDiscount = 0.00;

        array_walk(
            $customFees,
            static function (CustomOrderFeeInterface $customFee) use (
                $total,
                &$baseTotalCustomFeeAmount,
                &$totalCustomFeeAmount,
                &$baseTotalCustomFeeDiscount,
                &$totalCustomFeeDiscount,
            ): void {
                if ($customFee->getBaseDiscountAmount() === 0.00) {
                    return;
                }

                $total->setData('base_' . $customFee->getCode() . '_discount', $customFee->getBaseDiscountAmount());
                $total->setData($customFee->getCode() . '_discount', $customFee->getDiscountAmount());

                $baseTotalCustomFeeAmount += $customFee->getBaseValue();
                $totalCustomFeeAmount += $customFee->getValue();
                $baseTotalCustomFeeDiscount += $customFee->getBaseDiscountAmount();
                $totalCustomFeeDiscount += $customFee->getDiscountAmount();
            },
        );

        if ($baseTotalCustomFeeDiscount === 0.00) {
            return $this;
        }

        $total->addBaseTotalAmount(Discount::COLLECTOR_TYPE_CODE, -$baseTotalCustomFeeDiscount);
        $total->addTotalAmount(Discount::COLLECTOR_TYPE_CODE, -$totalCustomFeeDiscount);

        $this->validator->prepareDescription($address);

        $total->setDiscountDescription($address->getDiscountDescription());
        $total->setBaseSubtotalWithDiscount(
            $total->getBaseSubtotal() + $baseTotalCustomFeeAmount + $total->getBaseDiscountAmount(),
        );
        $total->setSubtotalWithDiscount($total->getSubtotal() + $totalCustomFeeAmount + $total->getDiscountAmount());

        $address->setBaseDiscountAmount($total->getBaseDiscountAmount());
        $address->setDiscountAmount($total->getDiscountAmount());

        return $this;
    }

    /**
     * @return array{}|array{'code': string, 'title': Phrase, 'value': float}
     */
    public function fetch(Quote $quote, Total $total): array
    {
        $result = [];
        $amount = (float) $total->getDiscountAmount();

        if ($amount === 0.00) {
            return $result;
        }

        $description = (string) ($total->getDiscountDescription() ?? '');
        $result = [
            'code' => Discount::COLLECTOR_TYPE_CODE,
            'title' => $description !== '' ? __('Discount (%1)', $description) : __('Discount'),
            'value' => $amount,
        ];

        return $result;
    }
}
