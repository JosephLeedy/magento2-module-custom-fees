<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Model\CustomOrderFee;

use JosephLeedy\CustomFees\Api\Data\CustomOrderFee\RefundedInterface;
use JosephLeedy\CustomFees\Model\CustomOrderFee;

/**
 * Refunded custom order fee data model
 *
 * @phpstan-method CustomCreditMemoFeeData jsonSerialize()
 */
class Refunded extends CustomOrderFee implements RefundedInterface
{
    public function setCreditMemoId(?int $creditMemoId): static
    {
        $this->setData(self::CREDIT_MEMO_ID, $creditMemoId);

        return $this;
    }

    public function getCreditMemoId(): ?int
    {
        return $this->_get(self::CREDIT_MEMO_ID);
    }
}
