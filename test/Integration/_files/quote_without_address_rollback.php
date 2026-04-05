<?php

declare(strict_types=1);

use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

$resolver = Resolver::getInstance();

$resolver->requireDataFixture('Magento/Customer/_files/customer_rollback.php');
$resolver->requireDataFixture('Magento/Catalog/_files/products_rollback.php');
