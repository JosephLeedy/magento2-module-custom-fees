<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Service;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Event\ManagerInterface;
use Magento\Quote\Model\Quote\Address;
use Magento\SalesRule\Api\Data\DiscountAppliedToInterface as DiscountAppliedTo;
use Magento\SalesRule\Api\Data\DiscountDataInterfaceFactory;
use Magento\SalesRule\Api\Data\RuleDiscountInterfaceFactory;
use Magento\SalesRule\Model\Quote\ChildrenValidationLocator;
use Magento\SalesRule\Model\Rule;
use Magento\SalesRule\Model\Rule\Action\Discount\CalculatorFactory;
use Magento\SalesRule\Model\Rule\Action\Discount\DataFactory;
use Magento\SalesRule\Model\RulesApplier;
use Magento\SalesRule\Model\SelectRuleCoupon;
use Magento\SalesRule\Model\Utility;

use function is_string;

/**
 * @internal
 */
class CustomFeeDiscountRulesApplier extends RulesApplier
{
    private readonly RuleDiscountInterfaceFactory $discountInterfaceFactory;
    private readonly DiscountDataInterfaceFactory $discountDataInterfaceFactory;
    private readonly SelectRuleCoupon $selectRuleCoupon;

    public function __construct(
        CalculatorFactory $calculatorFactory,
        ManagerInterface $eventManager,
        Utility $utility,
        ChildrenValidationLocator $childrenValidationLocator = null,
        DataFactory $discountDataFactory = null,
        RuleDiscountInterfaceFactory $discountInterfaceFactory = null,
        DiscountDataInterfaceFactory $discountDataInterfaceFactory = null,
        SelectRuleCoupon $selectRuleCoupon = null,
    ) {
        parent::__construct(
            $calculatorFactory,
            $eventManager,
            $utility,
            $childrenValidationLocator,
            $discountDataFactory,
            $discountInterfaceFactory,
            $discountDataInterfaceFactory,
            $selectRuleCoupon,
        );

        $this->discountInterfaceFactory = $discountInterfaceFactory
            ?? ObjectManager::getInstance()->get(RuleDiscountInterfaceFactory::class);
        $this->discountDataInterfaceFactory = $discountDataInterfaceFactory
            ?? ObjectManager::getInstance()->get(DiscountDataInterfaceFactory::class);
        $this->selectRuleCoupon = $selectRuleCoupon ?? ObjectManager::getInstance()->get(SelectRuleCoupon::class);
    }

    /**
     * @param float[] $discount
     * @param string[] $couponCodes
     */
    public function addCustomFeeDiscountDescription(
        Address $address,
        Rule $rule,
        array $discount,
        array $couponCodes,
    ): void {
        $addressDiscounts = $address->getExtensionAttributes()?->getDiscounts() ?? [];
        $ruleLabel = $this->getRuleLabel($address, $rule, $couponCodes);
        $discount[DiscountAppliedTo::APPLIED_TO] = 'CUSTOM_FEE';
        $discountData = $this->discountDataInterfaceFactory->create(['data' => $discount]);
        $data = [
            'discount' => $discountData,
            'rule' => $ruleLabel,
            'rule_id' => $rule->getRuleId(),
        ];
        $addressDiscounts[] = $this->discountInterfaceFactory->create(['data' => $data]);

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
}
