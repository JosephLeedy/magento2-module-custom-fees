<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Plugin\Sales\Model\Order;

use JosephLeedy\CustomFees\Api\CustomOrderFeesRepositoryInterface;
use JosephLeedy\CustomFees\Model\FeeType;
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
        /**
         * @var array{
         *     code: string,
         *     title: string,
         *     type: value-of<FeeType>,
         *     percent: float|null,
         *     show_percentage: bool,
         *     base_value: float,
         *     value: float,
         * }[] $invoicedCustomFees
         */
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
            static function (array &$customFee) use ($invoiceId): void {
                $customFee['invoice_id'] = $invoiceId;
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
