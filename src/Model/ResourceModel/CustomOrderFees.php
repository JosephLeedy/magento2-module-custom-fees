<?php

/** @noinspection MagicMethodsValidityInspection */

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Model\ResourceModel;

use InvalidArgumentException;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

use function is_string;
use function json_validate;

class CustomOrderFees extends AbstractDb
{
    public const TABLE_NAME = 'custom_order_fees';

    /**
     * Mark JSON fields as serializable
     *
     * @var array{
     *     custom_fees_ordered: array{array{}},
     *     custom_fees_refunded: array{array{}},
     * }
     */
    protected $_serializableFields = [
        'custom_fees_ordered' => [
            [],
            [],
        ],
        'custom_fees_refunded' => [
            [],
            [],
        ],
    ];
    /**
     * Mark `order_entity_id` field as unique
     *
     * @var array{array{field: string, title:string}}
     */
    protected $_uniqueFields = [
        [
            'field' => 'order_entity_id',
            'title' => 'Custom fees order ID',
        ],
    ];
    private OrderInterface $order;

    public function __construct(
        Context $context,
        private readonly OrderRepositoryInterface $orderRepository,
        $connectionName = null,
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
            throw new InvalidArgumentException((string) __('Order with ID "%1" does not exist.', $orderId));
        }

        return $this->order;
    }

    /**
     * Initialize resource model
     */
    protected function _construct(): void
    {
        $this->_init(self::TABLE_NAME, 'id');

        $this->_useIsObjectNew = true;
    }

    protected function _serializeField(DataObject $object, $field, $defaultValue = null, $unsetEmpty = false): static
    {
        $value = $object->getData($field);

        // Prevent the field from being serialized again if it's already been serialized
        // phpcs:ignore PHPCompatibility.FunctionUse.NewFunctions.json_validateFound -- Provided by Symfony Polyfill
        if (is_string($value) && $value !== '' && json_validate($value)) {
            return $this;
        }

        return parent::_serializeField($object, $field, $defaultValue, $unsetEmpty);
    }
}
