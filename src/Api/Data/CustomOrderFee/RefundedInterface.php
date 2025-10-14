<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Api\Data\CustomOrderFee;

use JosephLeedy\CustomFees\Api\Data\CustomOrderFeeInterface;

interface RefundedInterface extends CustomOrderFeeInterface
{
    public const CREDIT_MEMO_ID = 'credit_memo_id';

    /**
     * @param int|null $creditMemoId
     * @return RefundedInterface
     */
    public function setCreditMemoId(?int $creditMemoId): RefundedInterface;

    /**
     * @return int|null
     */
    public function getCreditMemoId(): ?int;

    /**
     * @return array
     * @phpstan-return CustomCreditMemoFeeData
     */
    // phpcs:ignore PHPCompatibility.FunctionNameRestrictions.ReservedFunctionNames.MethodDoubleUnderscore
    public function __toArray();
}
