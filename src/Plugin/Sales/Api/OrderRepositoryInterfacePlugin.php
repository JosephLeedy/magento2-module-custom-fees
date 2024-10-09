<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Plugin\Sales\Api;

use JosephLeedy\CustomFees\Api\CustomOrderFeesRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

class OrderRepositoryInterfacePlugin
{
    public function __construct(
        private readonly CustomOrderFeesRepositoryInterface $customOrderFeesRepository,
    ) {}

    public function afterGet(
        OrderRepositoryInterface $subject,
        OrderInterface $result,
        int|string $id
    ): OrderInterface {
        $orderExtension = $result->getExtensionAttributes();

        if ($orderExtension === null || $orderExtension->getCustomOrderFees() !== null) {
            return $result;
        }

        try {
            $customOrderFees = $this->customOrderFeesRepository->getByOrderId($id);
        } catch (NoSuchEntityException) {
            $customOrderFees = null;
        }

        if ($customOrderFees === null) {
            return $result;
        }

        $orderExtension->setCustomOrderFees($customOrderFees);

        return $result;
    }

    public function afterSave(OrderRepositoryInterface $subject, OrderInterface $result): OrderInterface
    {
        $orderExtension = $result->getExtensionAttributes();

        if ($orderExtension === null || $orderExtension->getCustomOrderFees() === null) {
            return $result;
        }

        $customOrderFees = $orderExtension->getCustomOrderFees();

        if (!$customOrderFees->hasDataChanges()) {
            return $result;
        }

        if ($customOrderFees->getOrderId() === null && $result->getEntityId() !== null) {
            $customOrderFees->setOrderId($result->getEntityId());
        }

        $this->customOrderFeesRepository->save($customOrderFees);

        return $result;
    }
}
