import { expect, Locator, Page } from '@playwright/test';
import { UIReferenceCustomFees } from '@config';

type SkuAndQuantity = {
    sku: string,
    quantity: number,
};

class SalesOrderViewPage
{
    public constructor(private readonly page: Page) {}

    public async createInvoice(itemQuantities: [SkuAndQuantity]|[] = []): Promise<string|null>
    {
        let skuAndQuantity: SkuAndQuantity;
        let invoiceItemRow: Locator;

        await this.page
            .getByRole('button', { name: UIReferenceCustomFees.adminSalesOrderViewPage.createInvoiceButtonLabel })
            .click();
        await this.page.waitForLoadState('networkidle');

        expect(
            this.page.getByRole('heading', { name: UIReferenceCustomFees.adminSalesOrderInvoiceNewPage.pageTitle })
        ).toBeVisible();

        if (itemQuantities.length > 0) {
            for (skuAndQuantity of itemQuantities) {
                invoiceItemRow = this.page.getByRole('row').filter({ hasText: skuAndQuantity.sku });

                if (!(await invoiceItemRow.isVisible())) {
                    throw new Error(`Item with SKU "${skuAndQuantity.sku}" could not be found.`);
                }

               await invoiceItemRow
                   .locator(UIReferenceCustomFees.adminSalesOrderInvoiceNewPage.itemQuantityInputLocator)
                   .fill(skuAndQuantity.quantity.toString());
            }

            await this.page
                .getByRole(
                    'button',
                    {
                        name: UIReferenceCustomFees.adminSalesOrderInvoiceNewPage.updateItemQuanitiesButtonLabel
                    }
                ).click();
            await this.page.waitForLoadState('networkidle');
        }

        await this.page
            .getByRole('button', { name: UIReferenceCustomFees.adminSalesOrderInvoiceNewPage.submitInvoiceButtonLabel })
            .click();
        await this.page.waitForLoadState('networkidle');

        return await this.getFirstInvoiceIncrementId();
    }

    private async getFirstInvoiceIncrementId(): Promise<string|null>
    {
        let invoiceIncrementId: string|null = null;
        let firstInvoiceRowIdCell: Locator;

        await this.page
            .getByRole('link', { name: UIReferenceCustomFees.adminSalesOrderViewPage.invoicesTab.label })
            .click();
        await this.page.waitForLoadState('networkidle');

        firstInvoiceRowIdCell = this.page
            .locator(UIReferenceCustomFees.adminSalesOrderViewPage.invoicesTab.firstInvoiceLocator);

        if (!(await firstInvoiceRowIdCell.isVisible())) {
            throw new Error('No invoices found.');
        }

        invoiceIncrementId = (await firstInvoiceRowIdCell.textContent())?.trim() ?? null;

        return invoiceIncrementId;
    }
}

export default SalesOrderViewPage;
export { SkuAndQuantity };
