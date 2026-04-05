<?php

declare(strict_types=1);

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

$resolver = Resolver::getInstance();

$resolver->requireDataFixture('Magento/Customer/_files/customer.php');
$resolver->requireDataFixture('Magento/Catalog/_files/products.php');

$objectManager = Bootstrap::getObjectManager();
/** @var ProductRepositoryInterface $productRepository */
$productRepository = $objectManager->create(ProductRepositoryInterface::class);
$product = $productRepository->get('simple');
/** @var AccountManagementInterface $accountManagement */
$accountManagement = $objectManager->create(AccountManagementInterface::class);
/** @var CustomerRepositoryInterface $customerRepository */
$customerRepository = $objectManager->create(CustomerRepositoryInterface::class);
$customer = $customerRepository->getById(1);
/** @var Address $billingAddress */
$billingAddress = $objectManager->create(Address::class);
$shippingAddress = clone $billingAddress;
/** @var Quote $quote */
$quote = $objectManager->create(Quote::class);

$quote
    ->setStoreId(1)
    ->setIsActive(true)
    ->setIsMultiShipping(0)
    ->assignCustomerWithAddressChange($customer, $billingAddress, $shippingAddress)
    ->setCheckoutMethod('customer')
    ->setPasswordHash($accountManagement->getPasswordHash('password'))
    ->setReservedOrderId('test_order_1')
    ->setCustomerEmail('aaa@aaa.com')
    ->addProduct($product, 2);
$quote->save();
