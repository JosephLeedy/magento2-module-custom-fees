import { Page, TestInfo } from '@playwright/test';
import { step } from '@utils/stepDecorator.utils';
import SalesOrderGridPage from '@poms/adminhtml/salesOrderGrid.page';
import SalesOrderViewPage from '@poms/adminhtml/salesOrderView.page';

class CreateInvoiceStep
{
    public constructor(private readonly page: Page, private readonly testInfo: TestInfo) {}

    @step('Create invoice', { box: true })
    /**
     * Creates an invoice for an order.
     *
     * @param {string} orderNumber - The number of the order to create the invoice for.
     * @returns {Promise<string>} - A promise that resolves to the created invoice number.
     * @throws {Error} - Throws an error if the invoice cannot be created.
     */
    public async createInvoice(orderNumber: string): Promise<string>
    {
        const adminSalesOrderGridPage = new SalesOrderGridPage(this.page);
        const adminSalesOrderViewPage = new SalesOrderViewPage(this.page);
        let invoiceNumber: string|null = '';

        await adminSalesOrderGridPage.navigateToSalesOrderGrid();
        await adminSalesOrderGridPage.navigateToSalesOrderViewPage(orderNumber);

        invoiceNumber = await adminSalesOrderViewPage.createInvoice();

        if (invoiceNumber === null) {
            throw new Error(
                'Something went wrong while creating the invoice. Please check the logs for more information.'
            );
        }

        this.testInfo.annotations.push({
            type: 'Invoice number',
            description: invoiceNumber
        });

        return invoiceNumber;
    }
}

export default CreateInvoiceStep;
