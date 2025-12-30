<?php

declare(strict_types=1);

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\SalesRule\Api\Data\RuleInterface;
use Magento\SalesRule\Api\RuleRepositoryInterface;
use Magento\SalesRule\Model\Converter\ToModel;
use Magento\SalesRule\Model\Rule;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

$resolver = Resolver::getInstance();

$resolver->requireDataFixture('Magento/SalesRule/_files/cart_rule_10_percent_off.php');

$objectManager = Bootstrap::getObjectManager();
/** @var StoreManagerInterface $storeManager */
$storeManager = $objectManager->get(StoreManagerInterface::class);
/** @var SearchCriteriaBuilder $searchCriteriaBuilder */
$searchCriteriaBuilder = $objectManager->create(SearchCriteriaBuilder::class);
/** @var RuleRepositoryInterface $ruleRepository */
$ruleRepository = $objectManager->create(RuleRepositoryInterface::class);
$searchCriteria = $searchCriteriaBuilder
    ->addFilter('name', '10% Off on orders with two items')
    ->create();
/** @var RuleInterface $ruleData */
$ruleData = array_first($ruleRepository->getList($searchCriteria)->getItems());
/** @var ToModel $ruleDataConverter */
$ruleDataConverter = $objectManager->create(ToModel::class);
/** @var Rule $ruleModel */
$ruleModel = $ruleDataConverter->toModel($ruleData);

$ruleModel->setCustomerGroupIds('0,1');
$ruleModel->setData(
    'store_labels',
    [
        'store_id' => array_first($storeManager->getWebsite($ruleModel->getWebsiteIds()[0])->getStoreIds()),
        'store_label' => $ruleModel->getName(),
    ],
);
$ruleModel->setData('apply_to_custom_fees', '1');

/* We need to use the rule model to save the changes because the rule repository does not save the
   `apply_to_custom_fees` attribute */
$ruleModel->save();
