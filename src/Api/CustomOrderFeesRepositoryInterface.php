<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Api;

use JosephLeedy\CustomFees\Api\Data\CustomOrderFeesInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

interface CustomOrderFeesRepositoryInterface
{
    /**
     * @throws NoSuchEntityException
     */
    public function get(int|string $id): CustomOrderFeesInterface;

    /**
     * @throws NoSuchEntityException
     */
    public function getByOrderId(int|string $orderId): CustomOrderFeesInterface;

    public function getList(SearchCriteriaInterface $searchCriteria): SearchResultsInterface;

    /**
     * @throws AlreadyExistsException
     * @throws CouldNotSaveException
     */
    public function save(CustomOrderFeesInterface $customOrderFees): CustomOrderFeesInterface;

    /**
     * @throws NoSuchEntityException
     * @throws CouldNotDeleteException
     */
    public function delete(CustomOrderFeesInterface|int|string $customOrderFees): bool;
}
