<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Plugin\Sales\Api;

use JosephLeedy\CustomFees\Api\CustomOrderFeesRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

use function method_exists;

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

        if (
            $orderExtension === null
            || !method_exists($orderExtension, 'getCustomOrderFees')
            || $orderExtension->getCustomOrderFees() !== null
        ) {
            return $result;
        }

        try {
            $customOrderFees = $this->customOrderFeesRepository->getByOrderId($id);
        } catch (NoSuchEntityException) {
            $customOrderFees = null;
        }

        if ($customOrderFees === null || !method_exists($orderExtension, 'setCustomOrderFees')) {
            return $result;
        }

        $orderExtension->setCustomOrderFees($customOrderFees);

        return $result;
    }
}
