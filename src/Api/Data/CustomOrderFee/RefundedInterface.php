<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Api\Data\CustomOrderFee;

use JosephLeedy\CustomFees\Api\Data\CustomOrderFeeInterface;
use JosephLeedy\CustomFees\Model\FeeType;
use Magento\Framework\App\State;

interface RefundedInterface extends CustomOrderFeeInterface
{
    public const CREDIT_MEMO_ID = 'credit_memo_id';

    /**
     * @phpstan-param array{}|array{
     *     credit_memo_id: int,
     *     code: string,
     *     title: string,
     *     type: value-of<FeeType>,
     *     percent: float|null,
     *     show_percentage: bool,
     *     base_value: float,
     *     value: float,
     * } $data
     */
    public function __construct(State $state, array $data = []);

    /**
     * @param int $creditMemoId
     * @return RefundedInterface
     */
    public function setCreditMemoId(int $creditMemoId): RefundedInterface;

    /**
     * @return int
     */
    public function getCreditMemoId(): int;
}
