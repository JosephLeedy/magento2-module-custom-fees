<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Plugin\Sales\Model\Order;

use JosephLeedy\CustomFees\Api\CustomOrderFeesRepositoryInterface;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFee\InvoicedInterface as InvoicedCustomFeeInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Model\Order\Invoice;
use Psr\Log\LoggerInterface;

use function __;
use function array_key_exists;
use function array_walk;

class InvoicePlugin
{
    public function __construct(
        private readonly CustomOrderFeesRepositoryInterface $customOrderFeesRepository,
        private readonly LoggerInterface $logger,
    ) {}

    public function afterSave(Invoice $subject, Invoice $result): InvoiceInterface
    {
        /** @var array<string, InvoicedCustomFeeInterface> $invoicedCustomFees */
        $invoicedCustomFees = $result->getExtensionAttributes()?->getInvoicedCustomFees() ?? [];

        if ($invoicedCustomFees === []) {
            return $result;
        }

        try {
            $customOrderFees = $this->customOrderFeesRepository->getByOrderId($result->getOrderId());
        } catch (NoSuchEntityException) {
            return $result;
        }

        $invoiceId = (int) $result->getEntityId();
        $customFeesInvoiced = $customOrderFees->getCustomFeesInvoiced();

        if (array_key_exists($invoiceId, $customFeesInvoiced)) {
            return $result;
        }

        $customFeesInvoiced[$invoiceId] = $invoicedCustomFees;

        array_walk(
            $customFeesInvoiced[$invoiceId],
            static function (InvoicedCustomFeeInterface $invoicedCustomFee) use ($invoiceId): void {
                $invoicedCustomFee->setInvoiceId($invoiceId);
            },
        );

        $customOrderFees->setCustomFeesInvoiced($customFeesInvoiced);

        try {
            $this->customOrderFeesRepository->save($customOrderFees);
        } catch (AlreadyExistsException | CouldNotSaveException $exception) {
            $this->logger->critical(
                __('Could not save invoiced custom fees. Error: "%1"', $exception->getMessage()),
                [
                    'exception' => $exception,
                ],
            );
        }

        return $result;
    }
}
