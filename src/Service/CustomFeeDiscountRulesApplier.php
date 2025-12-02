<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Service;

use JosephLeedy\CustomFees\Api\Data\CustomOrderFeeInterface;
use Magento\Directory\Model\PriceCurrency;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\SalesRule\Api\Data\DiscountAppliedToInterface as DiscountAppliedTo;
use Magento\SalesRule\Api\Data\DiscountDataInterfaceFactory;
use Magento\SalesRule\Api\Data\RuleDiscountInterfaceFactory;
use Magento\SalesRule\Model\Rule;
use Magento\SalesRule\Model\SelectRuleCoupon;
use Magento\SalesRule\Model\Utility;
use Magento\SalesRule\Model\Validator;

use function array_key_exists;
use function floor;
use function is_string;
use function min;

/**
 * @api
 */
class CustomFeeDiscountRulesApplier
{
    public function __construct(
        private readonly Validator $validator,
        private readonly Utility $validatorUtility,
        private readonly PriceCurrency $priceCurrency,
        private readonly DiscountDataInterfaceFactory $discountDataFactory,
        private readonly RuleDiscountInterfaceFactory $ruleDiscountFactory,
        private readonly SelectRuleCoupon $selectRuleCoupon,
    ) {}

    /**
     * @param Rule[] $rules
     */
    public function applyRules(Address $address, array $rules): void
    {
        $quote = $address->getQuote();
        $customFees = $quote->getExtensionAttributes()?->getCustomFees() ?? [];

        if ($customFees === []) {
            return;
        }

        $appliedRuleIds = [];

        foreach ($rules as $rule) {
            if (
                !$rule->getApplyToCustomFees()
                || !$this->validatorUtility->canProcessRule($rule, $address)
                || !$rule->getActions()->validate($quote)
            ) {
                continue;
            }

            $isRuleApplied = false;

            foreach ($customFees as $customFee) {
                if ($rule->getCustomFee() !== null && $rule->getCustomFee() !== $customFee->getCode()) {
                    continue;
                }

                $this->applyRule($customFee, $rule, $address);

                $isRuleApplied = true;
            }

            if ($isRuleApplied) {
                $ruleId = $rule->getRuleId();
                $appliedRuleIds[$ruleId] = $ruleId;

                $this->addDiscountDescription(
                    $address,
                    $rule,
                    $this->validator->getCouponCodes() !== [] && $this->validator->getCouponCode() !== null
                        ? $this->validator->getCouponCodes()
                        : [$this->validator->getCouponCode()],
                );

                if ((int) $rule->getCouponType() !== Rule::COUPON_TYPE_NO_COUPON) {
                    $address->setCouponCode($address->getQuote()->getCouponCode());
                }
            }

            if ($rule->getStopRulesProcessing()) {
                break;
            }
        }

        if ($appliedRuleIds === []) {
            return;
        }

        $address->setAppliedRuleIds($this->validatorUtility->mergeIds($address->getAppliedRuleIds(), $appliedRuleIds));

        $quote->setAppliedRuleIds($this->validatorUtility->mergeIds($quote->getAppliedRuleIds(), $appliedRuleIds));
    }

    private function applyRule(CustomOrderFeeInterface $customFee, Rule $rule, Address $address): void
    {
        $quote = $address->getQuote();
        $baseDiscountAmount = 0.00;
        $discountAmount = 0.00;

        switch ($rule->getSimpleAction()) {
            case Rule::BY_PERCENT_ACTION:
                $ruleRate = (float) min(100, $rule->getDiscountAmount());
                $baseDiscountAmount = ($customFee->getBaseValue() - $customFee->getBaseDiscountAmount())
                    * $ruleRate / 100;
                $discountAmount = ($customFee->getValue() - $customFee->getDiscountAmount()) * $ruleRate / 100;

                $customFee->setDiscountRate((float) min(100, $customFee->getDiscountRate() + $ruleRate));

                break;
            case Rule::BY_FIXED_ACTION:
                $baseDiscountAmount = (float) $rule->getDiscountAmount();
                $discountAmount = $this->priceCurrency->convert($baseDiscountAmount, $quote->getStore());

                break;
            case Rule::CART_FIXED_ACTION:
                [$baseDiscountAmount, $discountAmount] = $this->calculateFixedCustomFeeDiscounts(
                    $customFee,
                    $rule,
                    $address,
                );

                break;
            case Rule::BUY_X_GET_Y_ACTION:
                $allQuantityDiscount = $this->getDiscountQuantityAllItemsBuyXGetYAction($quote, $rule);
                $quoteDiscountAmount = $customFee->getBaseValue() / (float) $quote->getItemsQty()
                    * $allQuantityDiscount;
                $baseDiscountAmount = $quoteDiscountAmount;
                $discountAmount = $this->priceCurrency->convert($quoteDiscountAmount, $quote->getStore());

                break;
            default:
                break;
        }

        $this->processCustomFeeDiscounts($customFee, $baseDiscountAmount, $discountAmount, $address, $rule);
    }

    /**
     * @return float[]
     */
    private function calculateFixedCustomFeeDiscounts(
        CustomOrderFeeInterface $customFee,
        Rule $rule,
        Address $address,
    ): array {
        $quote = $address->getQuote();
        $ruleId = $rule->getRuleId();
        /** @var float[] $cartRules */
        $cartRules = $address->getCartFixedRules() ?? [];
        $baseDiscountAmount = 0.00;
        $discountAmount = 0.00;
        $baseQuoteDiscountAmount = (float) $rule->getDiscountAmount();

        if (!array_key_exists($ruleId, $cartRules)) {
            $cartRules[$ruleId] = $baseQuoteDiscountAmount;
        }

        if ($cartRules[$ruleId] > 0) {
            $baseCustomFeeAmount = $customFee->getBaseValue();
            $quoteBaseSubtotal = (float) $quote->getBaseSubtotal() + $baseCustomFeeAmount;
            $ratio = $quoteBaseSubtotal !== 0.0 ? $baseCustomFeeAmount / $quoteBaseSubtotal : 0.0;
            $baseDiscountAmount = $baseQuoteDiscountAmount * $ratio;
            $discountAmount = $this->priceCurrency->convert($baseDiscountAmount, $quote->getStore());
            $cartRules[$ruleId] -= $baseDiscountAmount;
        }

        $address->setCartFixedRules($cartRules);

        return [$baseDiscountAmount, $discountAmount];
    }

    private function getDiscountQuantityAllItemsBuyXGetYAction(Quote $quote, Rule $rule): float
    {
        $discountAllQuantity = 0.0;
        $quoteItems = $quote->getItems() ?? [];

        foreach ($quoteItems as $item) {
            $quantity = $item->getQty();
            $discountStep = $rule->getDiscountStep();
            $discountAmount = (float) $rule->getDiscountAmount();

            if ($discountStep !== null) {
                $discountStep = (int) $discountStep;
            }

            if ($discountStep === null || $discountAmount > $discountStep) {
                continue;
            }

            $buyAndDiscountQuantity = $discountStep + $discountAmount;
            $fullRuleQuantityPeriod = floor($quantity / $buyAndDiscountQuantity);
            $freeQuantity = $quantity - $fullRuleQuantityPeriod * $buyAndDiscountQuantity;
            $discountQuantity = $fullRuleQuantityPeriod * $discountAmount;

            if ($freeQuantity > $discountStep) {
                $discountQuantity += $freeQuantity - $discountStep;
            }

            $discountAllQuantity += $discountQuantity;
        }

        return $discountAllQuantity;
    }

    private function processCustomFeeDiscounts(
        CustomOrderFeeInterface $customFee,
        float $baseDiscountAmount,
        float $discountAmount,
        Address $address,
        Rule $rule,
    ): void {
        if ($customFee->getDiscountAmount() + $discountAmount <= $customFee->getValue()) {
            $discountData = [
                'base_amount' => $baseDiscountAmount,
                'amount' => $discountAmount,
            ];

            $this->addCustomFeeDiscountDescription(
                $address,
                $rule,
                $discountData,
                $this->validator->getCouponCodes() !== [] && $this->validator->getCouponCode() !== null
                    ? $this->validator->getCouponCodes()
                    : [$this->validator->getCouponCode()],
            );
        }

        $baseDiscountAmount = (float) min(
            $customFee->getBaseDiscountAmount() + $baseDiscountAmount,
            $customFee->getBaseValue(),
        );
        $discountAmount = (float) min($customFee->getDiscountAmount() + $discountAmount, $customFee->getValue());

        $customFee->setBaseDiscountAmount($this->priceCurrency->roundPrice($baseDiscountAmount));
        $customFee->setDiscountAmount($this->priceCurrency->roundPrice($discountAmount));
    }

    /**
     * @param float[] $discount
     * @param string[] $couponCodes
     */
    private function addCustomFeeDiscountDescription(
        Address $address,
        Rule $rule,
        array $discount,
        array $couponCodes,
    ): void {
        $addressDiscounts = $address->getExtensionAttributes()?->getDiscounts() ?? [];
        $ruleLabel = $this->getRuleLabel($address, $rule, $couponCodes);
        $discount[DiscountAppliedTo::APPLIED_TO] = 'CUSTOM_FEE';
        $discountData = $this->discountDataFactory->create(['data' => $discount]);
        $data = [
            'discount' => $discountData,
            'rule' => $ruleLabel,
            'rule_id' => $rule->getRuleId(),
        ];
        $addressDiscounts[] = $this->ruleDiscountFactory->create(['data' => $data]);

        $address->getExtensionAttributes()?->setDiscounts($addressDiscounts);
    }

    /**
     * @param string[] $couponCodes
     */
    private function getRuleLabel(Address $address, Rule $rule, array $couponCodes = []): string
    {
        $ruleLabel = $rule->getStoreLabel($address->getQuote()->getStore());

        if (is_string($ruleLabel)) {
            return $ruleLabel;
        }

        $ruleCoupon = $this->selectRuleCoupon->execute($rule, $couponCodes);

        if ($ruleCoupon === null) {
            return '';
        }

        return $rule->getDescription() ?? $ruleCoupon;
    }

    /**
     * @param string[] $couponCodes
     */
    private function addDiscountDescription(Address $address, Rule $rule, array $couponCodes = []): self
    {
        $description = $address->getDiscountDescriptionArray();
        $label = $this->getRuleLabel($address, $rule, $couponCodes);

        if ($label !== '') {
            $description[$rule->getId()] = $label;
        }

        $address->setDiscountDescriptionArray($description);

        return $this;
    }
}
