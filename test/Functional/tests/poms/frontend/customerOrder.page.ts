import { expect, Locator } from '@playwright/test';
import { slugs, UIReferenceCustomFees } from '@config';
import OrderPage from '@poms/frontend/order.page';

class CustomerOrderPage extends OrderPage
{
    public async navigateToOrderHistoryPage(): Promise<void>
    {
        await this.page.goto(slugs.account.orderHistorySlug);
        await this.page.waitForLoadState('networkidle');
    }

    public async navigateToOrderPage(orderIncrementId: string): Promise<void>
    {
        let orderRow: Locator;

        orderRow = this.page.getByRole('row').filter({ hasText: orderIncrementId });

        if (!(await orderRow.isVisible())) {
            throw new Error(`Order with ID "${orderIncrementId}" could not be found.`);
        }

        await orderRow
            .getByRole('link', { name: UIReferenceCustomFees.orderPage.viewOrderLabel })
            .first()
            .click();

        await this.page.waitForLoadState('networkidle');

        await expect(
            this.page
                .getByRole(
                    'heading',
                    {
                        name: `${UIReferenceCustomFees.orderPage.orderDetailsTitle} ${orderIncrementId}`
                    }
                )
        ).toBeVisible();
    }
}

export default CustomerOrderPage;
