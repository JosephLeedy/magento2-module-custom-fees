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

    public async navigateToInvoicesPage(): Promise<void>
    {
        await this.page.getByRole('link', { name: UIReferenceCustomFees.orderPage.invoicesLinkLabel }).click();
        await this.page.waitForLoadState();

        await expect(this.page.getByText(UIReferenceCustomFees.orderPage.invoiceDetailsTitle)).toBeVisible();
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

    public async assertInvoiceHasCustomFees(
        invoiceIncrementId: string = '',
        inEuro: boolean = false,
        exclude: string[] = []
    ): Promise<void> {
        await this.assertHasCustomFees((await this.getInvoiceItemsContainer(invoiceIncrementId)), inEuro, exclude);
    }

    public async assertOrderDoesNotHaveCustomFees(inEuro: boolean = false, exclude: string[] = []): Promise<void>
    {
        await this.assertDoesNotHaveCustomFees(
            this.page.locator(UIReferenceCustomFees.orderPage.orderTotalsContainerLocator),
            inEuro,
            exclude
        );
    }

    public async assertInvoiceDoesNotHaveCustomFees(
        invoiceIncrementId: string = '',
        inEuro: boolean = false,
        exclude: string[] = []
    ): Promise<void> {
        await this.assertDoesNotHaveCustomFees(
            (await this.getInvoiceItemsContainer(invoiceIncrementId)),
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

    private async getInvoiceItemsContainer(invoiceIncrementId: string = ''): Promise<Locator>
    {
        const invoiceTitle: Locator = this.page
            .locator(
                UIReferenceCustomFees.orderPage.orderTitleLocator,
                {
                    hasText: `${UIReferenceCustomFees.orderPage.invoiceDetailsTitle}${invoiceIncrementId}`
                }
            );
        let invoiceTotalsContainer: Locator;
        let errorMessage: string;

        if (!(await invoiceTitle.isVisible())) {
            errorMessage = invoiceIncrementId.length === 0
                ? 'There are no invoices. Please create one first.'
                : `Invoice with ID "${invoiceIncrementId}" could not be found.`;

            throw new Error(errorMessage);
        }

        invoiceTotalsContainer = invoiceTitle
            .locator(UIReferenceCustomFees.orderPage.invoiceTotalsContainerLocator);

        return invoiceTotalsContainer;
    }
}

export default GuestOrderPage;
