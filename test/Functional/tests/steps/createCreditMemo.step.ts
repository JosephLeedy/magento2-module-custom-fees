import { Page, TestInfo } from '@playwright/test';
import { slugsCustomFees } from '@config';
import { step } from '@utils/stepDecorator.utils';
import SalesOrderGridPage from '@poms/adminhtml/salesOrderGrid.page';
import SalesOrderViewPage from '@poms/adminhtml/salesOrderView.page';

class CreateCreditMemoStep
{
    public constructor(private readonly page: Page, private readonly testInfo: TestInfo) {}

    @step('Create credit memo', { box: true })
    /**
     * Creates a credit memo for an order.
     *
     * If the administrator is not already on the Sales Order View page, it navigates there first. **For the navigation
     * to work, an order number must be provided. If the order number is not provided, the step will fail.**
     *
     * @param {string} [orderNumber] - The number of the order to create the credit memo for. (optional)
     * @returns {Promise<string>} - A promise that resolves to the created credit memo number.
     * @throws {Error} - Throws an error if the credit memo cannot be created.
     */
    public async createCreditMemo(orderNumber?: string): Promise<string>
    {
        const adminSalesOrderViewPage = new SalesOrderViewPage(this.page);
        let adminSalesOrderGridPage: SalesOrderGridPage;
        let creditMemoNumber: string|null = '';

        if (!this.page.url().includes(slugsCustomFees.admin.salesOrderView) && orderNumber !== undefined) {
            adminSalesOrderGridPage = new SalesOrderGridPage(this.page);

            await adminSalesOrderGridPage.navigateToSalesOrderGrid();
            await adminSalesOrderGridPage.navigateToSalesOrderViewPage(orderNumber);
        }

        creditMemoNumber = await adminSalesOrderViewPage.createCreditMemo();

        if (creditMemoNumber === null) {
            throw new Error(
                'Something went wrong while creating the credit memo. Please check the logs for more information.'
            );
        }

        this.testInfo.annotations.push({
            type: 'Credit memo number',
            description: creditMemoNumber
        });

        return creditMemoNumber;
    }
}

export default CreateCreditMemoStep;
