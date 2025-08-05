import { expect, Locator, Page } from '@playwright/test';
import { slugs, UIReferenceCustomFees } from '@config';
import CustomFees from '@utils/customFees.utils';

class CustomerOrderPage
{
    public constructor(private readonly page: Page) {}

    public async navigateToOrderPage(orderIncrementId: string): Promise<void>
    {
        let orderRow: Locator;

        await this.page.goto(slugs.account.orderHistorySlug);
        await this.page.waitForLoadState();

        orderRow = this.page.getByRole('row').filter({ hasText: orderIncrementId });

        if (!(await orderRow.isVisible())) {
            throw new Error(`Order with ID "${orderIncrementId}" could not be found.`);
        }

        await orderRow
            .getByRole('link', { name: UIReferenceCustomFees.customerOrderPage.viewOrderLabel })
            .first()
            .click();

        await this.page.waitForLoadState('networkidle');

        await expect(
            this.page
                .getByRole(
                    'heading',
                    {
                        name: `${UIReferenceCustomFees.customerOrderPage.orderDetailsTitle} ${orderIncrementId}`
                    }
                )
        ).toBeVisible();
    }

    public async orderHasCustomFees(inEuro: boolean = false, exclude: string[] = []): Promise<void>
    {
        await this.hasCustomFees(
            this.page.locator(UIReferenceCustomFees.customerOrderPage.orderTotalsContainerLocator),
            inEuro,
            exclude
        );
    }

    public async orderDoesNotHaveCustomFees(inEuro: boolean = false, exclude: string[] = []): Promise<void>
    {
        await this.doesNotHaveCustomFees(
            this.page.locator(UIReferenceCustomFees.customerOrderPage.orderTotalsContainerLocator),
            inEuro,
            exclude
        );
    }

    private async hasCustomFees(
        containerLocator: Locator,
        inEuro: boolean = false,
        exclude: string[] = []
    ): Promise<void> {
        const customFees = await new CustomFees().getAll(containerLocator, inEuro, exclude);

        for (const customFee of customFees) {
            await expect(customFee).toBeVisible();
        }
    }

    private async doesNotHaveCustomFees(
        containerLocator: Locator,
        inEuro: boolean = false,
        exclude: string[] = []
    ): Promise<void> {
        const customFees = await new CustomFees().getAll(containerLocator, inEuro, exclude);

        for (const customFee of customFees) {
            await expect(customFee).not.toBeVisible();
        }
    }
}

export default CustomerOrderPage;
