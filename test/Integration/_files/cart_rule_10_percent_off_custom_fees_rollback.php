<?php

declare(strict_types=1);

use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

$resolver = Resolver::getInstance();

$resolver->requireDataFixture('Magento/SalesRule/_files/cart_rule_10_percent_off_rollback.php');
