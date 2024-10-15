<?php

declare(strict_types=1);

use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

$resolver = Resolver::getInstance();

$resolver->requireDataFixture('Magento/Sales/_files/default_rollback.php');
