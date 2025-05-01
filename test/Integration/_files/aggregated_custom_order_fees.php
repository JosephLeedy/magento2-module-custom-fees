<?php

declare(strict_types=1);

use JosephLeedy\CustomFees\Model\ResourceModel\Report\CustomOrderFees;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

Resolver::getInstance()
    ->requireDataFixture(
        'JosephLeedy_CustomFees::../test/Integration/_files/orders_with_custom_fees_multicurrency.php',
    );

$objectManager = Bootstrap::getObjectManager();
/** @var CustomOrderFees $customOrderFeesReportResource */
$customOrderFeesReportResource = $objectManager->create(CustomOrderFees::class);

$customOrderFeesReportResource->aggregate();
