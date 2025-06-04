<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Service;

use JosephLeedy\CustomFees\Model\Rule\CustomFees;
use JosephLeedy\CustomFees\Model\Rule\CustomFeesFactory;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;

class RulesApplier
{
    /**
     * @var array<string, CustomFees> $rules
     */
    private array $rules = [];

    public function __construct(private readonly CustomFeesFactory $ruleFactory) {}

    /**
     * @phpstan-param CartInterface&Quote $quote
     * @param array{
     *     type: class-string,
     *     aggregator: string,
     *     value: '0'|'1',
     *     conditions: array<
     *         int,
     *         array{
     *             type: class-string,
     *             attribute: string,
     *             operator: string,
     *             value: string
     *         }
     *     >
     * } $conditions
     */
    public function isApplicable(CartInterface $quote, string $feeCode, array $conditions): bool
    {
        $customFeesRule = $this->getRule($feeCode, $conditions);

        /* Iterate through cart items to find any that match the configured conditions and return true if at least one
           is found */
        /** @var Item $item */
        foreach ($quote->getAllItems() as $item) {
            $isApplicable = $customFeesRule->validate($item);

            if (!$isApplicable) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * @param array{
     *     type: class-string,
     *     aggregator: string,
     *     value: '0'|'1',
     *     conditions: array<
     *         int,
     *         array{
     *             type: class-string,
     *             attribute: string,
     *             operator: string,
     *             value: string
     *         }
     *     >
     * } $conditions
     */
    private function getRule(string $feeCode, array $conditions): CustomFees
    {
        if (isset($this->rules[$feeCode])) {
            return $this->rules[$feeCode];
        }

        $this->rules[$feeCode] = $this->ruleFactory->create();

        $this->rules[$feeCode]->setFeeCode($feeCode);
        $this->rules[$feeCode]->getConditions()->setConditions([])->loadArray($conditions);

        return $this->rules[$feeCode];
    }
}
