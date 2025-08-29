<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Plugin\Sales\Api;

use JosephLeedy\CustomFees\Api\CustomOrderFeesRepositoryInterface;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFeesInterface;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFeesInterfaceFactory;
use JosephLeedy\CustomFees\Model\FeeType;
use JosephLeedy\CustomFees\Service\CustomFeesRetriever;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Psr\Log\LoggerInterface;

use function array_find;

class CreditmemoRepositoryInterfacePlugin
{
    public function __construct(
        private readonly CustomOrderFeesRepositoryInterface $customOrderFeesRepository,
        private readonly CustomOrderFeesInterfaceFactory $customOrderFeesFactory,
        private readonly CustomFeesRetriever $customFeesRetriever,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @param CreditmemoInterface&Creditmemo $result
     */
    public function afterSave(CreditmemoRepositoryInterface $subject, CreditmemoInterface $result): CreditmemoInterface
    {
        $refundedCustomFees = $result->getExtensionAttributes()?->getRefundedCustomFees() ?? [];

        if ($refundedCustomFees === []) {
            return $result;
        }

        $orderId = $result->getOrderId();

        try {
            $customOrderFees = $this->customOrderFeesRepository->getByOrderId($orderId);
        } catch (NoSuchEntityException) {
            /** @var CustomOrderFeesInterface $customOrderFees */
            $customOrderFees = $this->customOrderFeesFactory->create();
            $customFeesOrdered = $this->customFeesRetriever->retrieve($result->getOrder());

            $customOrderFees->setOrderId($orderId);
            $customOrderFees->setCustomFeesOrdered($customFeesOrdered);
        }

        $customFeesOrdered = $customOrderFees->getCustomFeesOrdered();
        $customFeesRefunded = [];

        /**
         * @var string $code
         * @var string|float $baseValue
         */
        foreach ($refundedCustomFees as $code => $baseValue) {
            /**
             * @var array{
             *     code?: string,
             *     title?: string,
             *     type?: value-of<FeeType>,
             *     percent?: float|null,
             *     show_percentage?: bool,
             *     base_value?: float,
             * } $orderedCustomFee
             */
            $orderedCustomFee = array_find(
                $customFeesOrdered,
                static fn(array $customFeeOrdered): bool => $customFeeOrdered['code'] === $code,
            ) ?? [];
            $customFeesRefunded[$code] = [
                'credit_memo_id' => (int) $result->getId(),
                'code' => $code,
                'title' => $orderedCustomFee['title'] ?? '',
                'type' => $orderedCustomFee['type'] ?? FeeType::Fixed->value,
                'percent' => $orderedCustomFee['percent'] ?? null,
                'show_percentage' => $orderedCustomFee['show_percentage'] ?? false,
                'base_value' => (float) $baseValue,
                'value' => $this->priceCurrency->convert(
                    (float) $baseValue,
                    $result->getStore(),
                    $result->getOrderCurrencyCode(),
                ),
            ];
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
