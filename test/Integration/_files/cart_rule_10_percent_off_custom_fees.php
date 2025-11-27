<?php

declare(strict_types=1);

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\SalesRule\Api\Data\RuleInterface;
use Magento\SalesRule\Api\RuleRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

$resolver = Resolver::getInstance();

$resolver->requireDataFixture('Magento/SalesRule/_files/cart_rule_10_percent_off.php');

$objectManager = Bootstrap::getObjectManager();
/** @var SearchCriteriaBuilder $searchCriteriaBuilder */
$searchCriteriaBuilder = $objectManager->create(SearchCriteriaBuilder::class);
/** @var RuleRepositoryInterface $ruleRepository */
$ruleRepository = $objectManager->create(RuleRepositoryInterface::class);
$searchCriteria = $searchCriteriaBuilder
    ->addFilter('name', '10% Off on orders with two items')
    ->create();
/** @var RuleInterface $rule */
$rule = array_first($ruleRepository->getList($searchCriteria)->getItems());

$rule->setData('apply_to_custom_fees', '1');

$ruleRepository->save($rule);
