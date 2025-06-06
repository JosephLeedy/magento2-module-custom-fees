<?php

declare(strict_types=1);

use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

$resolver = Resolver::getInstance();

$resolver->requireDataFixture('JosephLeedy_CustomFees::../test/Integration/_files/creditmemo_rollback.php');
$resolver->requireDataFixture('JosephLeedy_CustomFees::../test/Integration/_files/custom_fees_rollback.php');
