<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Plugin\Framework\View\Element\UiComponent\DataProvider;

use JosephLeedy\CustomFees\Model\ResourceModel\CustomOrderFees as CustomOrderFeesResource;
use Magento\Framework\Data\Collection;
use Magento\Framework\View\Element\UiComponent\DataProvider\CollectionFactory;

use function method_exists;

class CollectionFactoryPlugin
{
    public function __construct(private readonly CustomOrderFeesResource $resource) {}

    public function afterGetReport(CollectionFactory $subject, Collection $result, string $requestName): Collection
    {
        if ($requestName !== 'sales_order_grid_data_source' || !method_exists($result, 'getSelect')) {
            return $result;
        }

        $idFieldName = method_exists($result, 'getIdFieldName') ? $result->getIdFieldName() : 'entity_id';
        $customOrderFeesTable = $this->resource->getTable(CustomOrderFeesResource::TABLE_NAME);

        $result
            ->getSelect()
            ->joinLeft(
                $customOrderFeesTable,
                "$customOrderFeesTable.order_entity_id = main_table.$idFieldName",
                "$customOrderFeesTable.custom_fees",
            );

        return $result;
    }
}
