<?php

/** @noinspection MagicMethodsValidityInspection */

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Model;

use InvalidArgumentException;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFee\InvoicedInterface as InvoicedCustomOrderFee;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFee\InvoicedInterfaceFactory as InvoicedCustomOrderFeeFactory;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFee\RefundedInterface as RefundedCustomOrderFee;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFee\RefundedInterfaceFactory as RefundedCustomOrderFeeFactory;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFeeInterface as CustomOrderFee;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFeeInterfaceFactory as CustomOrderFeeFactory;
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
use function array_map;
use function is_array;
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
        private readonly CustomOrderFeeFactory $customOrderFeeFactory,
        private readonly InvoicedCustomOrderFeeFactory $invoicedCustomOrderFeeFactory,
        private readonly RefundedCustomOrderFeeFactory $refundedCustomOrderFeeFactory,
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
                /** @var array<string, CustomOrderFeeData> $orderedCustomFees */
                $orderedCustomFees = (array) (
                    $this->serializer->unserialize($customFeesOrdered)
                        ?: throw new InvalidArgumentException((string) __('Invalid custom fees'))
                );
            } catch (InvalidArgumentException) {
                throw new InvalidArgumentException((string) __('Invalid custom fees'));
            }

            $customFeesOrdered = array_map(
                fn(array $orderedCustomFee): CustomOrderFee => $this->customOrderFeeFactory->create(
                    [
                        'data' => $orderedCustomFee,
                    ],
                ),
                $orderedCustomFees,
            );
        }

        $this->setData(self::CUSTOM_FEES_ORDERED, $customFeesOrdered);

        return $this;
    }

    public function getCustomFeesOrdered(): array
    {
        /** @var array<string, CustomOrderFee|CustomOrderFeeData>|string $customFeesOrdered */
        $customFeesOrdered = $this->getData(self::CUSTOM_FEES_ORDERED);

        if (is_string($customFeesOrdered)) {
            $this->setCustomFeesOrdered($customFeesOrdered);

            /** @var array<string, CustomOrderFee> $customFeesOrdered */
            $customFeesOrdered = $this->getData(self::CUSTOM_FEES_ORDERED);
        }

        foreach ($customFeesOrdered as $feeCode => $customFeeData) {
            if (!is_array($customFeeData)) {
                continue;
            }

            $customFeesOrdered[$feeCode] = $this->customOrderFeeFactory->create(['data' => $customFeeData]);
        }

        /** @var array<string, CustomOrderFee> $customFeesOrdered */

        return $customFeesOrdered;
    }

    public function setCustomFeesInvoiced(string|array $customFeesInvoiced): CustomOrderFeesInterface
    {
        if (is_string($customFeesInvoiced)) {
            try {
                /** @var array<int, array<string, CustomInvoiceFeeData>> $invoicedCustomFees */
                $invoicedCustomFees = (array) $this->serializer->unserialize($customFeesInvoiced);
            } catch (InvalidArgumentException) {
                throw new InvalidArgumentException((string) __('Invalid custom fees'));
            }

            $customFeesInvoiced = array_map(
                fn(array $invoicedCustomFee): array => array_map(
                    fn(array $invoicedCustomFeeData): InvoicedCustomOrderFee
                        => $this->invoicedCustomOrderFeeFactory->create(['data' => $invoicedCustomFeeData]),
                    $invoicedCustomFee,
                ),
                $invoicedCustomFees,
            );
        }

        $this->setData(self::CUSTOM_FEES_INVOICED, $customFeesInvoiced);

        return $this;
    }

    public function getCustomFeesInvoiced(): array
    {
        /** @var array<int, array<string, InvoicedCustomOrderFee|CustomInvoiceFeeData>>|string $customFeesInvoiced */
        $customFeesInvoiced = $this->getData(self::CUSTOM_FEES_INVOICED) ?? [];

        if (is_string($customFeesInvoiced)) {
            $this->setCustomFeesInvoiced($customFeesInvoiced);

            /** @var array<int, array<string, InvoicedCustomOrderFee>> $customFeesInvoiced */
            $customFeesInvoiced = $this->getData(self::CUSTOM_FEES_INVOICED);
        }

        foreach ($customFeesInvoiced as $invoiceId => $customFeeInvoiced) {
            foreach ($customFeeInvoiced as $feeCode => $customFeeInvoicedData) {
                if (!is_array($customFeeInvoicedData)) {
                    continue;
                }

                /** @noinspection UnsupportedStringOffsetOperationsInspection */
                $customFeesInvoiced[$invoiceId][$feeCode] = $this->invoicedCustomOrderFeeFactory->create(
                    [
                        'data' => $customFeeInvoicedData,
                    ],
                );
            }
        }

        /** @var array<int, array<string, InvoicedCustomOrderFee>> $customFeesInvoiced */

        return $customFeesInvoiced;
    }

    public function setCustomFeesRefunded(string|array $customFeesRefunded): CustomOrderFeesInterface
    {
        if (is_string($customFeesRefunded)) {
            try {
                /** @var array<int, array<string, CustomCreditMemoFeeData>> $refundedCustomFees */
                $refundedCustomFees = (array) $this->serializer->unserialize($customFeesRefunded);
            } catch (InvalidArgumentException) {
                throw new InvalidArgumentException((string) __('Invalid custom fees'));
            }

            $customFeesRefunded = array_map(
                fn(array $refundedCustomFee): array => array_map(
                    fn(array $refundedCustomFeeData): RefundedCustomOrderFee
                        => $this->refundedCustomOrderFeeFactory->create(['data' => $refundedCustomFeeData]),
                    $refundedCustomFee,
                ),
                $refundedCustomFees,
            );
        }

        $this->setData(self::CUSTOM_FEES_REFUNDED, $customFeesRefunded);

        return $this;
    }

    public function getCustomFeesRefunded(): array
    {
        /** @var array<int, array<string, RefundedCustomOrderFee|CustomCreditMemoFeeData>>|string $customFeesRefunded */
        $customFeesRefunded = $this->getData(self::CUSTOM_FEES_REFUNDED) ?? [];

        if (is_string($customFeesRefunded)) {
            $this->setCustomFeesRefunded($customFeesRefunded);

            /** @var array<int, array<string, RefundedCustomOrderFee>> $customFeesRefunded */
            $customFeesRefunded = $this->getData(self::CUSTOM_FEES_REFUNDED);
        }

        foreach ($customFeesRefunded as $creditMemoId => $customFeeRefunded) {
            foreach ($customFeeRefunded as $feeCode => $customFeeRefundedData) {
                if (!is_array($customFeeRefundedData)) {
                    continue;
                }

                /** @noinspection UnsupportedStringOffsetOperationsInspection */
                $customFeesRefunded[$creditMemoId][$feeCode] = $this->refundedCustomOrderFeeFactory->create(
                    [
                        'data' => $customFeeRefundedData,
                    ],
                );
            }
        }

        /** @var array<int, array<string, RefundedCustomOrderFee>> $customFeesRefunded */

        return $customFeesRefunded;
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
