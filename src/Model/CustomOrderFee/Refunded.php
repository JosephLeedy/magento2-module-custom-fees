<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Model\CustomOrderFee;

use JosephLeedy\CustomFees\Api\Data\CustomOrderFee\RefundedInterface;
use JosephLeedy\CustomFees\Model\CustomOrderFee;

class Refunded extends CustomOrderFee implements RefundedInterface
{
    public function setCreditMemoId(int $creditMemoId): static
    {
        $this->setData(self::CREDIT_MEMO_ID, $creditMemoId);

        return $this;
    }

    public function getCreditMemoId(): int
    {
        return (int) $this->_get(self::CREDIT_MEMO_ID);
    }
}
