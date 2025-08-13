import { expect, Locator, Page } from '@playwright/test';
import { slugsCustomFees, UIReferenceCustomFees } from '@config';
import CustomFees from '@utils/customFees.utils';

class GuestOrderPage
{
    public constructor(private readonly page: Page) {}

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

    public async assertOrderHasCustomFees(inEuro: boolean = false, exclude: string[] = []): Promise<void>
    {
        await this.assertHasCustomFees(
            this.page.locator(UIReferenceCustomFees.orderPage.orderTotalsContainerLocator),
            inEuro,
            exclude
        );
    }

    public async assertOrderDoesNotHaveCustomFees(inEuro: boolean = false, exclude: string[] = []): Promise<void>
    {
        await this.assertDoesNotHaveCustomFees(
            this.page.locator(UIReferenceCustomFees.orderPage.orderTotalsContainerLocator),
            inEuro,
            exclude
        );
    }

    private async assertHasCustomFees(
        containerLocator: Locator,
        inEuro: boolean = false,
        exclude: string[] = []
    ): Promise<void> {
        const customFees = await new CustomFees().getAll(containerLocator, inEuro, exclude);
        let customFee;

        for (customFee of customFees) {
            await expect(customFee).toBeVisible();
        }
    }

    private async assertDoesNotHaveCustomFees(
        containerLocator: Locator,
        inEuro: boolean = false,
        exclude: string[] = []
    ): Promise<void> {
        const customFees = await new CustomFees().getAll(containerLocator, inEuro, exclude);
        let customFee;

        for (customFee of customFees) {
            await expect(customFee).not.toBeVisible();
        }
    }
}

export default GuestOrderPage;
