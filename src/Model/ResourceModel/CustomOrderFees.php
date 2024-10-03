<?php

/** @noinspection MagicMethodsValidityInspection */

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Model\ResourceModel;

use InvalidArgumentException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

class CustomOrderFees extends AbstractDb
{
    /**
     * Mark `custom_fees` field as serializable
     *
     * @var array{custom_fees: array{array{}}}
     */
    protected $_serializableFields = [
        'custom_fees' => [
            [],
            []
        ]
    ];
    /**
     * Mark `order_entity_id` field as unique
     *
     * @var array{array{field: string, title:string}}
     */
    protected $_uniqueFields = [
        [
            'field' => 'order_entity_id',
            'title' => 'Custom fees order ID'
        ]
    ];
    private OrderInterface $order;

    public function __construct(
        Context $context,
        private readonly OrderRepositoryInterface $orderRepository,
        $connectionName = null
    ) {
        parent::__construct($context, $connectionName);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function getOrder(int $orderId): OrderInterface
    {
        if (isset($this->order)) {
            return $this->order;
        }

        try {
            $this->order = $this->orderRepository->get($orderId);
        } catch (InputException) { // phpcs:ignore Magento2.CodeAnalysis.EmptyBlock.DetectedCatch
            // No-op; this exception will never be thrown
        } catch (NoSuchEntityException) {
            throw new InvalidArgumentException((string)__('Order with ID "%1" does not exist.', $orderId));
        }

        return $this->order;
    }

    /**
     * Initialize resource model
     */
    protected function _construct(): void
    {
        $this->_init('custom_order_fees', 'id');

        $this->_useIsObjectNew = true;
    }
}
