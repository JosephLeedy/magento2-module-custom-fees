<?php

/** @noinspection MagicMethodsValidityInspection */

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Model\ResourceModel\CustomOrderFees;

use JosephLeedy\CustomFees\Model\CustomOrderFees as Model;
use JosephLeedy\CustomFees\Model\ResourceModel\CustomOrderFees as ResourceModel;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_eventPrefix = 'custom_order_fees_collection';

    /**
     * Initialize collection model
     */
    protected function _construct(): void
    {
        $this->_init(Model::class, ResourceModel::class);
    }
}
