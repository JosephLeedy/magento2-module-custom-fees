<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Service;

use JosephLeedy\CustomFees\Api\Data\CustomOrderFeeInterface;
use Magento\Directory\Model\PriceCurrency;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\SalesRule\Model\ResourceModel\Rule\Collection;
use Magento\SalesRule\Model\Rule;
use Magento\SalesRule\Model\Utility;
use Magento\SalesRule\Model\Validator;
use Psr\Log\LoggerInterface;
use Zend_Db_Select_Exception;

use function array_key_exists;
use function floor;
use function max;
use function min;

/**
 * @api
 */
class CustomFeesDiscountApplier
{
    public function __construct(
        private readonly Validator $validator,
        private readonly LoggerInterface $logger,
        private readonly PriceCurrency $priceCurrency,
        private readonly CustomFeeDiscountRulesApplier $discountRulesApplier,
        private readonly Utility $validatorUtility,
    ) {}

    /**
     * @param Address $address
     */
    public function applyCustomFeeDiscounts(Address $address): void
    {
        $quote = $address->getQuote();
        $customFees = $quote->getExtensionAttributes()?->getCustomFees() ?? [];

        if ($customFees === []) {
            return;
        }

        $appliedRuleIds = [];

        foreach ($customFees as $customFee) {
            $customFee->setBaseDiscountAmount(0.00);
            $customFee->setDiscountAmount(0.00);
            $customFee->setDiscountRate(0.00);

            $this->applyRulesToCustomFee($customFee, $address, $appliedRuleIds);
        }

        if ($appliedRuleIds === []) {
            return;
        }

        $address->setAppliedRuleIds($this->validatorUtility->mergeIds($address->getAppliedRuleIds(), $appliedRuleIds));

        $quote->setAppliedRuleIds($this->validatorUtility->mergeIds($quote->getAppliedRuleIds(), $appliedRuleIds));
    }

    /**
     * @param int[] $appliedRuleIds
     */
    private function applyRulesToCustomFee(
        CustomOrderFeeInterface $customFee,
        Address $address,
        array &$appliedRuleIds,
    ): void {
        try {
            /** @var Collection<Rule> $rules */
            $rules = $this->validator->getRules($address);
        } catch (Zend_Db_Select_Exception $databaseSelectException) {
            $this->logger->critical(
                "Could not get sales rules to apply to custom fee \"{$customFee->getCode()}\".",
                [
                    'exception' => $databaseSelectException,
                ],
            );

            $rules = [];
        }

        $quote = $address->getQuote();

        /** @var Rule $rule */
        foreach ($rules as $rule) {
            if (
                !$rule->getApplyToCustomFees()
                || ($rule->getCustomFee() !== null && $rule->getCustomFee() !== $customFee->getCode())
                || !$this->validatorUtility->canProcessRule($rule, $address)
            ) {
                continue;
            }

            $baseDiscountAmount = 0.00;
            $discountAmount = 0.00;
            $ruleRate = (float) min(100, $rule->getDiscountAmount());

            switch ($rule->getSimpleAction()) {
                case Rule::BY_PERCENT_ACTION:
                    $baseDiscountAmount = ($customFee->getBaseValue() - $customFee->getBaseDiscountAmount())
                        * $ruleRate / 100;
                    $discountAmount = ($customFee->getValue() - $customFee->getDiscountAmount()) * $ruleRate / 100;

                    $customFee->setDiscountRate((float) min(100, $customFee->getDiscountRate() + $ruleRate));

                    break;
                case Rule::BY_FIXED_ACTION:
                    $baseDiscountAmount = $rule->getDiscountAmount();
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
                    $quoteDiscountAmount = $customFee->getBaseValue() / $quote->getItemsQty()
                        * $allQuantityDiscount;
                    $baseDiscountAmount = $quoteDiscountAmount;
                    $discountAmount = $this->priceCurrency->convert($quoteDiscountAmount, $quote->getStore());

                    break;
            }

            $this->processCustomFeeDiscounts($customFee, $baseDiscountAmount, $discountAmount, $address, $rule);

            $ruleId = $rule->getRuleId();
            $appliedRuleIds[$ruleId] = $ruleId;

            $this->discountRulesApplier->addDiscountDescription(
                $address,
                $rule,
                $this->validator->getCouponCodes() !== []
                    ? $this->validator->getCouponCodes()
                    : [$this->validator->getCouponCode()],
            );

            if ($rule->getStopRulesProcessing()) {
                break;
            }
        }
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
        $cartRules = $address->getCartFixedRules();
        $baseDiscountAmount = 0.00;
        $discountAmount = 0.00;
        $baseQuoteDiscountAmount = $rule->getDiscountAmount();
        $quoteDiscountAmount = $this->priceCurrency->convert($baseQuoteDiscountAmount, $quote->getStore());
        $isAppliedToCustomFees = (bool) $rule->getApplyToCustomFees();

        if (!array_key_exists($ruleId, $cartRules)) {
            $cartRules[$ruleId] = $baseQuoteDiscountAmount;
        }

        if ($cartRules[$ruleId] > 0) {
            $baseCustomFeeAmount = $customFee->getBaseValue();
            $quoteBaseSubtotal = (float) $quote->getBaseSubtotal();

            if ($isAppliedToCustomFees) {
                $quoteBaseSubtotal += $baseCustomFeeAmount;
                $ratio = $quoteBaseSubtotal !== 0.0 ? $baseCustomFeeAmount / $quoteBaseSubtotal : 0.0;
                $baseDiscountAmount = $baseQuoteDiscountAmount * $ratio;
                $discountAmount = $this->priceCurrency->convert($baseDiscountAmount, $quote->getStore());
            } else {
                $baseDiscountAmount = min($baseCustomFeeAmount, $cartRules[$ruleId]);
                $discountAmount = min($customFee->getValue(), $quoteDiscountAmount);
            }

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
            $discountAmount = $rule->getDiscountAmount();

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

            $this->discountRulesApplier->addCustomFeeDiscountDescription(
                $address,
                $rule,
                $discountData,
                $this->validator->getCouponCodes() !== []
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
}
