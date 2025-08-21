import { expect } from '@playwright/test';
import { slugsCustomFees, UIReferenceCustomFees } from '@config';
import OrderPage from '@poms/frontend/order.page';

class GuestOrderPage extends OrderPage
{
    public async navigateToOrdersAndReturnsPage(): Promise<void>
    {
        await this.page.goto(slugsCustomFees.guest.ordersAndReturnsSlug);
        await this.page.waitForLoadState('networkidle');

        await expect(
            this.page.getByRole('heading', { name: UIReferenceCustomFees.ordersAndReturnsPage.pageTitle })
        ).toBeVisible();
    }

    public async fillOrderDetails(orderNumber: string, email: string, lastName: string): Promise<void>
    {
        await this.page.getByLabel(UIReferenceCustomFees.ordersAndReturnsPage.orderIdFieldLabel).fill(orderNumber);
        await this.page.getByLabel(UIReferenceCustomFees.ordersAndReturnsPage.billingLastNameFieldLabel).fill(lastName);
        await this.page
            .getByLabel(
                UIReferenceCustomFees.ordersAndReturnsPage.emailFieldLabel,
                {
                    exact: true
                }
            ).fill(email);
        await this.page
            .getByRole('button', { name: UIReferenceCustomFees.ordersAndReturnsPage.submitButtonLabel })
            .click();
        await this.page.waitForLoadState('networkidle');
    }

    public async assertOrderIsVisible(orderNumber: string): Promise<void>
    {
        await expect(
            this.page.getByRole(
                'heading',
                {
                    name: `${UIReferenceCustomFees.orderPage.orderDetailsTitle} ${orderNumber}`
                }
            )
        ).toBeVisible();
    }
}

export default GuestOrderPage;
