<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Model\Total\Quote;

use JosephLeedy\CustomFees\Api\Data\CustomOrderFeeInterface;
use JosephLeedy\CustomFees\Service\CustomFeeDiscountRulesApplier;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Address\Total\AbstractTotal;
use Magento\SalesRule\Model\Quote\Discount;
use Magento\SalesRule\Model\Rule;
use Magento\SalesRule\Model\Validator;
use Psr\Log\LoggerInterface;
use Zend_Db_Select_Exception;

use function __;
use function array_walk;

class CustomFeesDiscount extends AbstractTotal
{
    public function __construct(
        private readonly Validator $validator,
        private readonly LoggerInterface $logger,
        private readonly CustomFeeDiscountRulesApplier $discountRulesApplier,
    ) {}

    /**
     * @throws LocalizedException
     */
    public function collect(Quote $quote, ShippingAssignmentInterface $shippingAssignment, Total $total): self
    {
        parent::collect($quote, $shippingAssignment, $total);

        $items = $shippingAssignment->getItems();

        if ($items === []) {
            return $this;
        }

        $customFees = $quote->getExtensionAttributes()?->getCustomFees() ?? [];

        if ($customFees === []) {
            return $this;
        }

        array_walk(
            $customFees,
            static function (CustomOrderFeeInterface $customFee): void {
                $customFee->setBaseDiscountAmount(0.00);
                $customFee->setDiscountAmount(0.00);
                $customFee->setDiscountRate(0.00);
            },
        );

        $address = $this->_getAddress();

        $this->validator->reset($address);

        try {
            /** @var Rule[] $rules */
            $rules = $this->validator->getRules($address)->getItems();
        } catch (Zend_Db_Select_Exception $databaseSelectException) {
            $this->logger->critical(
                'Could not retrieve sales rules to apply to custom fees.',
                [
                    'exception' => $databaseSelectException,
                ],
            );

            $rules = [];
        }

        if ($rules === []) {
            return $this;
        }

        $this->discountRulesApplier->applyRules($address, $rules);

        $this->processDiscounts($customFees, $total);

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

    /**
     * @param CustomOrderFeeInterface[] $customFees
     */
    private function processDiscounts(array $customFees, Total $total): void
    {
        $baseTotalCustomFeeAmount = 0.00;
        $totalCustomFeeAmount = 0.00;
        $baseTotalCustomFeeDiscount = 0.00;
        $totalCustomFeeDiscount = 0.00;
        $address = $this->_getAddress();

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

                $total->setData(
                    'base_' . $customFee->getCode() . '_discount_amount',
                    -$customFee->getBaseDiscountAmount(),
                );
                $total->setData($customFee->getCode() . '_discount_amount', -$customFee->getDiscountAmount());

                $baseTotalCustomFeeAmount += $customFee->getBaseValue();
                $totalCustomFeeAmount += $customFee->getValue();
                $baseTotalCustomFeeDiscount += $customFee->getBaseDiscountAmount();
                $totalCustomFeeDiscount += $customFee->getDiscountAmount();
            },
        );

        if ($baseTotalCustomFeeDiscount === 0.00) {
            return;
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
    }
}
