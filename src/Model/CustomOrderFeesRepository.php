<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Model;

use Exception;
use InvalidArgumentException;
use JosephLeedy\CustomFees\Api\CustomOrderFeesRepositoryInterface;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFeesInterface;
use JosephLeedy\CustomFees\Api\Data\CustomOrderFeesInterfaceFactory;
use JosephLeedy\CustomFees\Model\ResourceModel\CustomOrderFees as ResourceModel;
use JosephLeedy\CustomFees\Model\ResourceModel\CustomOrderFees\Collection;
use JosephLeedy\CustomFees\Model\ResourceModel\CustomOrderFees\CollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

use function __;

class CustomOrderFeesRepository implements CustomOrderFeesRepositoryInterface
{
    public function __construct(
        private readonly CustomOrderFeesInterfaceFactory $customOrderFeesFactory,
        private readonly ResourceModel $resourceModel,
        private readonly CollectionFactory $collectionFactory,
        private readonly SearchResultsInterfaceFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface $collectionProcessor,
    ) {}

    public function get(int|string $id): CustomOrderFeesInterface
    {
        $customOrderFees = $this->customOrderFeesFactory->create();

        $this->resourceModel->load($customOrderFees, $id);

        if ($customOrderFees->getId() === null) {
            throw new NoSuchEntityException(__('Custom order fees with ID "%1" does not exist.', $id));
        }

        return $customOrderFees;
    }

    public function getByOrderId(int|string $orderId): CustomOrderFeesInterface
    {
        $customOrderFees = $this->customOrderFeesFactory->create();

        $this->resourceModel->load($customOrderFees, $orderId, 'order_entity_id');

        if ($customOrderFees->getId() === null) {
            throw new NoSuchEntityException(__('Custom fees do not exist for order with ID "%1".', $orderId));
        }

        return $customOrderFees;
    }

    public function getList(SearchCriteriaInterface $searchCriteria): SearchResultsInterface
    {
        /** @var Collection $collection */
        $collection = $this->collectionFactory->create();
        /** @var SearchResultsInterface $searchResults */
        $searchResults = $this->searchResultsFactory->create();

        try {
            $this->collectionProcessor->process($searchCriteria, $collection);
        } catch (InvalidArgumentException) { // phpcs:ignore Magento2.CodeAnalysis.EmptyBlock.DetectedCatch
            // No-op; this exception will never be thrown
        }

        $searchResults->setItems($collection->getItems());
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }

    public function save(CustomOrderFeesInterface $customOrderFees): CustomOrderFeesInterface
    {
        try {
            $this->resourceModel->save($customOrderFees);
        } catch (AlreadyExistsException) {
            throw new AlreadyExistsException(
                __('Custom fees have already been saved for order with ID "%1".', $customOrderFees->getOrderId())
            );
        } catch (Exception $exception) {
            throw new CouldNotSaveException(
                __(
                    'Could not save custom fees for order with ID "%1". Error: "%2"',
                    $customOrderFees->getOrderId(),
                    $exception->getMessage()
                )
            );
        }

        return $customOrderFees;
    }

    public function delete(CustomOrderFeesInterface|int|string $customOrderFees): bool
    {
        if (!$customOrderFees instanceof CustomOrderFeesInterface) {
            $customOrderFees = $this->get($customOrderFees);
        }

        try {
            $this->resourceModel->delete($customOrderFees);
        } catch (Exception $exception) {
            throw new CouldNotDeleteException(
                __(
                    'Could not delete custom fees for order with ID "%1". Error: "%2"',
                    $customOrderFees->getOrderId(),
                    $exception->getMessage()
                )
            );
        }

        return true;
    }
}
