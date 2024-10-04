<?php

declare(strict_types=1);

use JosephLeedy\CustomFees\Model\CustomOrderFees;
use JosephLeedy\CustomFees\Model\ResourceModel\CustomOrderFees\Collection as CustomOrderFeesCollection;
use Magento\TestFramework\Helper\Bootstrap;

$objectManager = Bootstrap::getObjectManager();
/** @var CustomOrderFeesCollection $customOrderFeesCollection */
$customOrderFeesCollection = $objectManager->create(CustomOrderFeesCollection::class);

/** @var CustomOrderFees $customOrderFees */
foreach ($customOrderFeesCollection as $customOrderFees) {
    $customOrderFees->delete();
}
