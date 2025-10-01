import { expect, Locator, Page } from '@playwright/test';
import { UIReferenceCustomFees } from '@config';
import CustomFees from '@utils/customFees.utils';

class OrderPage
{
    public constructor(protected readonly page: Page) {}

    public async navigateToInvoicesPage(): Promise<void>
    {
        await this.page.getByRole('link', { name: UIReferenceCustomFees.orderPage.invoicesLinkLabel }).click();
        await this.page.waitForLoadState();

        await expect(this.page.getByText(UIReferenceCustomFees.orderPage.invoiceDetailsTitle)).toBeVisible();
    }

    public async navigateToCreditMemosPage(): Promise<void>
    {
        await this.page.getByRole('link', { name: UIReferenceCustomFees.orderPage.creditMemosLinkLabel }).click();
        await this.page.waitForLoadState();

        await expect(this.page.getByText(UIReferenceCustomFees.orderPage.creditMemoDetailsTitle)).toBeVisible();
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

    public async assertCreditMemoHasCustomFees(
        creditMemoIncrementId: string = '',
        inEuro: boolean = false,
        exclude: string[] = [],
        useRefundAmount: boolean = false,
    ): Promise<void> {
        await this.assertHasCustomFees(
            (await this.getCreditMemoItemsContainer(creditMemoIncrementId)),
            inEuro,
            exclude,
            useRefundAmount,
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

    public async assertCreditMemoDoesNotHaveCustomFees(
        creditMemoIncrementId: string = '',
        inEuro: boolean = false,
        exclude: string[] = [],
        useRefundAmount: boolean = false,
    ): Promise<void> {
        await this.assertDoesNotHaveCustomFees(
            (await this.getCreditMemoItemsContainer(creditMemoIncrementId)),
            inEuro,
            exclude,
            useRefundAmount,
        );
    }

    private async assertHasCustomFees(
        containerLocator: Locator,
        inEuro: boolean = false,
        exclude: string[] = [],
        useRefundAmount: boolean = false,
    ): Promise<void> {
        const customFees = await new CustomFees().getAll(containerLocator, inEuro, exclude, useRefundAmount);
        let customFee;

        for (customFee of customFees) {
            await expect(customFee).toBeVisible();
        }
    }

    private async assertDoesNotHaveCustomFees(
        containerLocator: Locator,
        inEuro: boolean = false,
        exclude: string[] = [],
        useRefundAmount: boolean = false,
    ): Promise<void> {
        const customFees = await new CustomFees().getAll(containerLocator, inEuro, exclude, useRefundAmount);
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

export default OrderPage;
