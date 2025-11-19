<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Service;

use JosephLeedy\CustomFees\Api\CustomOrderFeesRepositoryInterface;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFeesInterface;
use JosephLeedy\CustomFees\Model\CustomOrderFees;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\SerializerInterface;

use function array_key_exists;

/**
 * @internal
 */
class CustomOrderFeeTaxValueAdder
{
    public function __construct(
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly CustomOrderFeesRepositoryInterface $customOrderFeesRepository,
        private readonly SerializerInterface $serializer,
    ) {}

    /**
     * @throws LocalizedException
     */
    public function addTaxValues(): void
    {
        $searchCriteria = $this->searchCriteriaBuilder->create();
        $searchResults = $this->customOrderFeesRepository->getList($searchCriteria);

        if ($searchResults->getTotalCount() === 0) {
            return;
        }

        /** @var CustomOrderFees[] $customOrderFeesCollection */
        $customOrderFeesCollection = $searchResults->getItems();

        foreach ($customOrderFeesCollection as $customOrderFees) {
            $customFeesOrderedHasChanges = false;
            $customFeesInvoicedHasChanges = false;
            $customFeesRefundedHasChanges = false;
            /** @var array<string, CustomOrderFeeData> $customFeesOrdered */
            $customFeesOrdered = $this->serializer->unserialize(
                $customOrderFees->getData(CustomOrderFeesInterface::CUSTOM_FEES_ORDERED) ?? '[]',
            );
            /** @var array<int, array<string, CustomInvoiceFeeData>> $customFeesInvoiced */
            $customFeesInvoiced = $this->serializer->unserialize(
                $customOrderFees->getData(CustomOrderFeesInterface::CUSTOM_FEES_INVOICED) ?? '[]',
            );
            /** @var array<int, array<string, CustomCreditMemoFeeData>> $customFeesRefunded */
            $customFeesRefunded = $this->serializer->unserialize(
                $customOrderFees->getData(CustomOrderFeesInterface::CUSTOM_FEES_REFUNDED) ?? '[]',
            );

            foreach ($customFeesOrdered as &$customFeeOrdered) {
                if (array_key_exists('base_value_with_tax', $customFeeOrdered)) {
                    continue;
                }

                $this->setTaxValues($customFeeOrdered);

                $customFeesOrderedHasChanges = true;
            }

            unset($customFeeOrdered);

            foreach ($customFeesInvoiced as &$customFeeInvoiced) {
                foreach ($customFeeInvoiced as &$customFee) {
                    if (array_key_exists('base_value_with_tax', $customFeeInvoiced)) {
                        continue;
                    }

                    $this->setTaxValues($customFee);

                    $customFeesInvoicedHasChanges = true;
                }

                unset($customFee);
            }

            unset($customFeeInvoiced);

            foreach ($customFeesRefunded as &$customFeeRefunded) {
                foreach ($customFeeRefunded as &$customFee) {
                    if (array_key_exists('base_value_with_tax', $customFeeRefunded)) {
                        continue;
                    }

                    $this->setTaxValues($customFee);

                    $customFeesRefundedHasChanges = true;
                }

                unset($customFee);
            }

            unset($customFeeRefunded);

            if ($customFeesOrderedHasChanges) {
                /** @var string $customFeesOrderedJson */
                $customFeesOrderedJson = $this->serializer->serialize($customFeesOrdered);

                $customOrderFees->setCustomFeesOrdered($customFeesOrderedJson);
            }

            if ($customFeesInvoicedHasChanges) {
                /** @var string $customFeesInvoicedJson */
                $customFeesInvoicedJson = $this->serializer->serialize($customFeesInvoiced);

                $customOrderFees->setCustomFeesInvoiced($customFeesInvoicedJson);
            }

            if ($customFeesRefundedHasChanges) {
                /** @var string $customFeesRefundedJson */
                $customFeesRefundedJson = $this->serializer->serialize($customFeesRefunded);

                $customOrderFees->setCustomFeesRefunded($customFeesRefundedJson);
            }

            if ($customFeesOrderedHasChanges || $customFeesInvoicedHasChanges || $customFeesRefundedHasChanges) {
                try {
                    $this->customOrderFeesRepository->save($customOrderFees);
                } catch (AlreadyExistsException | CouldNotSaveException $exception) {
                    throw new LocalizedException(
                        __('Could not add taxes to custom order fees with ID "%1".', $customOrderFees->getId()),
                        $exception,
                    );
                }
            }
        }
    }

    /**
     * @param CustomOrderFeeData|CustomInvoiceFeeData|CustomCreditMemoFeeData $customFee
     */
    private function setTaxValues(array &$customFee): void
    {
        $customFee['base_value_with_tax'] = $customFee['base_value'];
        $customFee['value_with_tax'] = $customFee['value'];
        $customFee['base_tax_amount'] = 0.00;
        $customFee['tax_amount'] = 0.00;
        $customFee['tax_rate'] = 0.00;
    }
}
