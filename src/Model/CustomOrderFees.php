<?php

/** @noinspection MagicMethodsValidityInspection */

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Model;

use InvalidArgumentException;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFeesInterface;
use JosephLeedy\CustomFees\Model\ResourceModel\CustomOrderFees as ResourceModel;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Sales\Api\Data\OrderInterface;

use function __;
use function is_string;

class CustomOrderFees extends AbstractModel implements CustomOrderFeesInterface
{
    protected $_eventPrefix = 'custom_order_fees_model';
    private ?OrderInterface $order = null;

    /**
     * @param mixed[] $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        private readonly SerializerInterface $serializer,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = [],
    ) {
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    public function setOrderId(int|string $orderId): CustomOrderFeesInterface
    {
        $this->setData(self::ORDER_ID, (int) $orderId);

        return $this;
    }

    public function getOrderId(): ?int
    {
        /** @var int|string|null $orderId */
        $orderId = $this->getData(self::ORDER_ID);

        if ($orderId !== null) {
            $orderId = (int) $orderId;
        }

        return $orderId;
    }

    public function setCustomFeesOrdered(string|array $customFeesOrdered): CustomOrderFeesInterface
    {
        if (is_string($customFeesOrdered)) {
            try {
                $customFeesOrdered = (array) (
                    $this->serializer->unserialize($customFeesOrdered)
                        ?: throw new InvalidArgumentException((string) __('Invalid custom fees'))
                );
            } catch (InvalidArgumentException) {
                throw new InvalidArgumentException((string) __('Invalid custom fees'));
            }
        }

        $this->setData(self::CUSTOM_FEES_ORDERED, $customFeesOrdered);

        return $this;
    }

    public function getCustomFeesOrdered(): array
    {
        /**
         * @var array<string, array{
         *     code: string,
         *     title: string,
         *     type: value-of<FeeType>,
         *     percent: float|null,
         *     show_percentage: bool,
         *     base_value: float,
         *     value: float
         * }>|string|null $customFeesOrdered
         */
        $customFeesOrdered = $this->getData(self::CUSTOM_FEES_ORDERED);

        if (is_string($customFeesOrdered)) {
            $customFeesOrdered = (array) $this->serializer->unserialize($customFeesOrdered);
        }

        return $customFeesOrdered ?? [];
    }

    public function getOrder(): ?OrderInterface
    {
        if ($this->order === null && $this->getOrderId() !== null) {
            try {
                /** @var ResourceModel $resource */
                $resource = $this->_resource;
                $this->order = $resource->getOrder($this->getOrderId());
            } catch (InvalidArgumentException) { // phpcs:ignore Magento2.CodeAnalysis.EmptyBlock.DetectedCatch
                // no-op
            }
        }

        return $this->order;
    }

    /**
     * Initialize Model
     */
    protected function _construct(): void
    {
        $this->_init(ResourceModel::class);
    }
}
