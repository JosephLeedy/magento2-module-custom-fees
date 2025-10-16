<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Service;

use JosephLeedy\CustomFees\Api\Data\CustomOrderFee\InvoicedInterface as InvoicedCustomFee;
use JosephLeedy\CustomFees\Model\CustomOrderFees;
use JosephLeedy\CustomFees\Model\FeeType;
use JosephLeedy\CustomFees\Model\ResourceModel\CustomOrderFees\Collection as CustomOrderFeesCollection;
use JosephLeedy\CustomFees\Service\InvoicedCustomFeesRecorder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Zend_Db_Expr;

final class InvoicedCustomFeesRecorderTest extends TestCase
{
    #[DataFixture('JosephLeedy_CustomFees::../test/Integration/_files/order_list_with_invoice_and_custom_fees.php')]
    public function testRecordsInvoicedCustomFeesForExistingInvoices(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var InvoicedCustomFeesRecorder $invoicedCustomFeesRecorder */
        $invoicedCustomFeesRecorder = $objectManager->create(InvoicedCustomFeesRecorder::class);
        /** @var CustomOrderFeesCollection $customOrderFeesCollection */
        $customOrderFeesCollection = $objectManager->create(CustomOrderFeesCollection::class);
        /** @var SearchCriteriaBuilder $searchCriteriaBuilder */
        $searchCriteriaBuilder = $objectManager->create(SearchCriteriaBuilder::class);
        $invoiceSearchCriteria = $searchCriteriaBuilder->create();
        /** @var InvoiceRepositoryInterface $invoiceRepository */
        $invoiceRepository = $objectManager->create(InvoiceRepositoryInterface::class);
        $invoices = $invoiceRepository->getList($invoiceSearchCriteria)->getItems();

        $invoicedCustomFeesRecorder->recordForExistingInvoices();

        /** @var CustomOrderFees[] $customOrderFeesItems */
        $customOrderFeesItems = $customOrderFeesCollection
            ->addFieldToFilter([new Zend_Db_Expr('JSON_LENGTH(custom_fees_invoiced)')], [['gt' => 0]])
            ->load()
            ->getItems();

        $expectedInvoicedCustomOrderFees = [];
        $actualInvoicedCustomOrderFees = [];

        foreach ($invoices as $invoice) {
            $invoiceId = $invoice->getEntityId();
            $expectedInvoicedCustomOrderFees[$invoiceId] = [
                'test_fee_0' => $objectManager->create(
                    InvoicedCustomFee::class,
                    [
                        'data' => [
                            'invoice_id' => $invoiceId,
                            'code' => 'test_fee_0',
                            'title' => 'Test Fee',
                            'type' => FeeType::Fixed,
                            'percent' => null,
                            'show_percentage' => false,
                            'base_value' => 5.00,
                            'value' => 5.00,
                        ],
                    ],
                ),
                'test_fee_1' => $objectManager->create(
                    InvoicedCustomFee::class,
                    [
                        'data' => [
                            'invoice_id' => $invoiceId,
                            'code' => 'test_fee_1',
                            'title' => 'Another Test Fee',
                            'type' => FeeType::Fixed,
                            'percent' => null,
                            'show_percentage' => false,
                            'base_value' => 1.50,
                            'value' => 1.50,
                        ],
                    ],
                ),
            ];
        }

        foreach ($customOrderFeesItems as $customOrderFeesItem) {
            $actualInvoicedCustomOrderFees += $customOrderFeesItem->getCustomFeesInvoiced();
        }

        self::assertEquals($expectedInvoicedCustomOrderFees, $actualInvoicedCustomOrderFees);
    }

    #[DataFixture('JosephLeedy_CustomFees::../test/Integration/_files/invoiced_custom_order_fees.php')]
    public function testDoesNotRecordInvoicedCustomFeesForInvoicesWithRecordedInvoicedCustomFees(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var InvoicedCustomFeesRecorder $invoicedCustomFeesRecorder */
        $invoicedCustomFeesRecorder = $objectManager->create(InvoicedCustomFeesRecorder::class);
        /** @var CustomOrderFeesCollection $customOrderFeesCollection */
        $customOrderFeesCollection = $objectManager->create(CustomOrderFeesCollection::class);

        $invoicedCustomFeesRecorder->recordForExistingInvoices();

        $customOrderFeesCollection
            ->addFieldToFilter([new Zend_Db_Expr('JSON_LENGTH(custom_fees_invoiced)')], [['gt' => 0]])
            ->load();

        // The fixture records six invoiced custom order fees, and the service should not record them again.
        self::assertSame(6, $customOrderFeesCollection->count());
    }

    #[DataFixture('Magento/Sales/_files/order_list.php')]
    public function testDoesNotRecordInvoicedCustomFeesIfNoInvoicesExist(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var InvoicedCustomFeesRecorder $invoicedCustomFeesRecorder */
        $invoicedCustomFeesRecorder = $objectManager->create(InvoicedCustomFeesRecorder::class);
        /** @var CustomOrderFeesCollection $customOrderFeesCollection */
        $customOrderFeesCollection = $objectManager->create(CustomOrderFeesCollection::class);

        $invoicedCustomFeesRecorder->recordForExistingInvoices();

        $customOrderFeesCollection
            ->addFieldToFilter([new Zend_Db_Expr('JSON_LENGTH(custom_fees_invoiced)')], [['gt' => 0]])
            ->load();

        self::assertSame(0, $customOrderFeesCollection->count());
    }
}
