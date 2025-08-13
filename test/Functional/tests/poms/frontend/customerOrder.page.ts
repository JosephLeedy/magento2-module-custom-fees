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

    public async navigateToInvoicesPage(): Promise<void>
    {
        const invoicesLink = this.page
            .locator(UIReferenceCustomFees.orderPage.orderLinksLocator)
            .getByRole('link', { name: UIReferenceCustomFees.orderPage.invoicesLinkLabel });

        await invoicesLink.click();
        await this.page.waitForLoadState();

        await expect(this.page.getByText(UIReferenceCustomFees.orderPage.invoiceDetailsTitle)).toBeVisible();
    }

    public async navigateToCreditMemosPage(): Promise<void>
    {
        const creditMemosLink = this.page
            .locator(UIReferenceCustomFees.orderPage.orderLinksLocator)
            .getByRole('link', { name: UIReferenceCustomFees.orderPage.creditMemosLinkLabel });

        await creditMemosLink.click();
        await this.page.waitForLoadState();

        await expect(this.page.getByText(UIReferenceCustomFees.orderPage.creditMemoDetailsTitle)).toBeVisible();
    }

    public async orderHasCustomFees(inEuro: boolean = false, exclude: string[] = []): Promise<void>
    {
        await this.hasCustomFees(
            this.page.locator(UIReferenceCustomFees.orderPage.orderTotalsContainerLocator),
            inEuro,
            exclude
        );
    }

    public async invoiceHasCustomFees(
        invoiceIncrementId: string = '',
        inEuro: boolean = false,
        exclude: string[] = []
    ): Promise<void> {
        await this.hasCustomFees((await this.getInvoiceItemsContainer(invoiceIncrementId)), inEuro, exclude);
    }

    public async creditMemoHasCustomFees(
        creditMemoIncrementId: string = '',
        inEuro: boolean = false,
        exclude: string[] = []
    ): Promise<void> {
        await this.hasCustomFees((await this.getCreditMemoItemsContainer(creditMemoIncrementId)), inEuro, exclude);
    }

    public async orderDoesNotHaveCustomFees(inEuro: boolean = false, exclude: string[] = []): Promise<void>
    {
        await this.doesNotHaveCustomFees(
            this.page.locator(UIReferenceCustomFees.orderPage.orderTotalsContainerLocator),
            inEuro,
            exclude
        );
    }

    public async invoiceDoesNotHaveCustomFees(
        invoiceIncrementId: string = '',
        inEuro: boolean = false,
        exclude: string[] = []
    ): Promise<void> {
        await this.doesNotHaveCustomFees(
            (await this.getInvoiceItemsContainer(invoiceIncrementId)),
            inEuro,
            exclude
        );
    }

    public async creditMemoDoesNotHaveCustomFees(
        creditMemoIncrementId: string = '',
        inEuro: boolean = false,
        exclude: string[] = []
    ): Promise<void> {
        await this.doesNotHaveCustomFees(
            (await this.getCreditMemoItemsContainer(creditMemoIncrementId)),
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

    private async getCreditMemoItemsContainer(creditMemoIncrementId: string = ''): Promise<Locator>
    {
        const creditMemoTitle: Locator = this.page
            .locator(
                UIReferenceCustomFees.orderPage.orderTitleLocator,
                {
                    hasText: `${UIReferenceCustomFees.orderPage.creditMemoDetailsTitle} ${creditMemoIncrementId}`
                }
            ).or(
                this.page.locator(
                    UIReferenceCustomFees.orderPage.orderTitleLocator,
                    {
                        hasText: `${UIReferenceCustomFees.orderPage.creditMemoDetailsTitle}${creditMemoIncrementId}`
                    }
                )
            );
        let creditMemoTotalsContainer: Locator;
        let errorMessage: string;

        if (!(await creditMemoTitle.isVisible())) {
            errorMessage = creditMemoIncrementId.length === 0
                ? 'There are no credit memos. Please create one first.'
                : `Credit memo with ID "${creditMemoIncrementId}" could not be found.`;

            throw new Error(errorMessage);
        }

        creditMemoTotalsContainer = creditMemoTitle
            .locator(UIReferenceCustomFees.orderPage.creditMemoTotalsContainerLocator);

        return creditMemoTotalsContainer;
    }
}

export default CustomerOrderPage;
