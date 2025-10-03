<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Plugin\Sales\Api;

use JosephLeedy\CustomFees\Api\CustomOrderFeesRepositoryInterface;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFee\RefundedInterface as RefundedCustomFeeInterface;
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
        /** @var RefundedCustomFeeInterface[] $refundedCustomFees */
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
        $customFeesRefunded[$creditMemoId] = $refundedCustomFees;

        array_walk(
            $customFeesRefunded[$creditMemoId],
            static function (RefundedCustomFeeInterface $refundedCustomFee) use ($creditMemoId): void {
                $refundedCustomFee->setCreditMemoId($creditMemoId);
            },
        );

        /** @var RefundedCustomFeeInterface[] $customFeesRefunded */

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
