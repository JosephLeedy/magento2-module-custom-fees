<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Service;

use JosephLeedy\CustomFees\Model\Rule\CustomFees;
use JosephLeedy\CustomFees\Model\Rule\CustomFeesFactory;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Model\Quote\Address;

class RulesApplier
{
    /**
     * @var array<string, CustomFees> $rules
     */
    private array $rules = [];

    public function __construct(private readonly CustomFeesFactory $ruleFactory) {}

    /**
     * @phpstan-param AddressInterface&Address $quoteAddress
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
    public function isApplicable(AddressInterface $quoteAddress, string $feeCode, array $conditions): bool
    {
        return $this->getRule($feeCode, $conditions)->validate($quoteAddress);
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
