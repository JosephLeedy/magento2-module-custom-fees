<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Plugin\Sales\Api;

use JosephLeedy\CustomFees\Api\CustomOrderFeesRepositoryInterface;
use JosephLeedy\CustomFees\Model\FeeType;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Psr\Log\LoggerInterface;

class CreditmemoRepositoryInterfacePlugin
{
    public function __construct(
        private readonly CustomOrderFeesRepositoryInterface $customOrderFeesRepository,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @param CreditmemoInterface&Creditmemo $result
     */
    public function afterSave(CreditmemoRepositoryInterface $subject, CreditmemoInterface $result): CreditmemoInterface
    {
        /**
         * @var array<string, array{
         *     code: string,
         *     title: string,
         *     type: value-of<FeeType>,
         *     percent: float|null,
         *     show_percentage: bool,
         *     base_value: float,
         *     value: float,
         * }> $refundedCustomFees
         */
        $refundedCustomFees = $result->getExtensionAttributes()?->getRefundedCustomFees() ?? [];

        if ($refundedCustomFees === []) {
            return $result;
        }

        try {
            $customOrderFees = $this->customOrderFeesRepository->getByOrderId($result->getOrderId());
        } catch (NoSuchEntityException) {
            return $result;
        }

        $customFeesRefunded = $customOrderFees->getCustomFeesRefunded();
        $creditMemoId = (int) $result->getId();

        foreach ($refundedCustomFees as $refundedCustomFee) {
            $code = $refundedCustomFee['code'];
            $refundedCustomFee['credit_memo_id'] = $creditMemoId;
            $customFeesRefunded[$creditMemoId][$code] = $refundedCustomFee;
        }

        $customOrderFees->setCustomFeesRefunded($customFeesRefunded);

        try {
            $this->customOrderFeesRepository->save($customOrderFees);
        } catch (AlreadyExistsException | CouldNotSaveException $exception) {
            $this->logger->critical(
                __('Could not save refunded custom fees. Error: "%1"', $exception->getMessage()),
                [
                    'exception' => $exception,
                ],
            );
        }

        return $result;
    }
}
