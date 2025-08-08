import { expect, Page } from '@playwright/test';
import { UIReferenceCustomFees } from '@config';

class SalesOrderGridPage
{
    public constructor(private readonly page: Page) {}

    public async navigateToSalesOrderGrid(): Promise<void>
    {
        await this.page
            .getByRole('link', { name: UIReferenceCustomFees.adminSalesOrderGridPage.navigation.salesMenuLabel })
            .click();
        await this.page.waitForLoadState();
        await this.page
            .getByRole(
                'link',
                {
                    name: UIReferenceCustomFees.adminSalesOrderGridPage.navigation.ordersMenuItemLabel,
                    exact: true
                }
            ).click();
        await this.page.waitForLoadState('networkidle');

        expect(this.page.getByRole('heading', { name: UIReferenceCustomFees.adminSalesOrderGridPage.pageTitle }))
            .toBeVisible();
    }

    public async navigateToSalesOrderViewPage(orderIncrementId: string): Promise<void>
    {
        await this.page.getByRole('cell', { name: orderIncrementId }).click();
        await this.page.waitForLoadState('networkidle');

        expect(this.page.getByRole('heading', { name: `#${orderIncrementId}` })).toBeVisible();
    }
}

export default SalesOrderGridPage;
