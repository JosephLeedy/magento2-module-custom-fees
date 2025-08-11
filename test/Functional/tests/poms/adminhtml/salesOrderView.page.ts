import { expect, Locator, Page } from '@playwright/test';
import { outcomeMarkersCustomFees, UIReference, UIReferenceCustomFees } from '@config';

type SkuAndQuantity = {
    sku: string,
    quantity: number,
};

class SalesOrderViewPage
{
    public constructor(private readonly page: Page) {}

    public async createInvoice(itemQuantities: SkuAndQuantity[] = []): Promise<string|null>
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

        expect(
            this.page.locator(
                UIReference.general.successMessageLocator,
                {
                    hasText: outcomeMarkersCustomFees.adminSalesOrderViewPage.invoiceCreatedNotificationMessage
                }
            )
        ).toBeVisible();

        return await this.getFirstInvoiceIncrementId();
    }

    public async createCreditMemo(itemQuantities: SkuAndQuantity[] = []): Promise<string|null>
    {
        let skuAndQuantity: SkuAndQuantity;
        let creditMemoItemRow: Locator;

        await this.page
            .getByRole('button', { name: UIReferenceCustomFees.adminSalesOrderViewPage.createCreditMemoButtonLabel })
            .click();
        await this.page.waitForLoadState('networkidle');

        expect(
            this.page.getByRole('heading', { name: UIReferenceCustomFees.adminSalesOrderCreditMemoNewPage.pageTitle })
        ).toBeVisible();

        if (itemQuantities.length > 0) {
            for (skuAndQuantity of itemQuantities) {
                creditMemoItemRow = this.page.getByRole('row').filter({ hasText: skuAndQuantity.sku });

                if (!(await creditMemoItemRow.isVisible())) {
                    throw new Error(`Item with SKU "${skuAndQuantity.sku}" could not be found.`);
                }

               await creditMemoItemRow
                   .locator(UIReferenceCustomFees.adminSalesOrderCreditMemoNewPage.itemQuantityInputLocator)
                   .fill(skuAndQuantity.quantity.toString());
            }

            await this.page
                .getByRole(
                    'button',
                    {
                        name: UIReferenceCustomFees.adminSalesOrderCreditMemoNewPage.updateItemQuanitiesButtonLabel
                    }
                ).click();
            await this.page.waitForLoadState('networkidle');
        }

        await this.page
            .getByRole(
                'button',
                {
                    name: UIReferenceCustomFees.adminSalesOrderCreditMemoNewPage.submitCreditMemoButtonLabel
                }
            ).click();
        await this.page.waitForLoadState('networkidle');

        expect(
            this.page.locator(
                UIReference.general.successMessageLocator,
                {
                    hasText: outcomeMarkersCustomFees.adminSalesOrderViewPage.creditMemoCreatedNotificationMessage
                }
            )
        ).toBeVisible();

        return await this.getFirstCreditMemoIncrementId();
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

    private async getFirstCreditMemoIncrementId(): Promise<string|null>
    {
        let creditMemoIncrementId: string|null = null;
        let firstCreditMemoRowIdCell: Locator;

        await this.page
            .getByRole('link', { name: UIReferenceCustomFees.adminSalesOrderViewPage.creditMemosTab.label })
            .click();
        await this.page.waitForLoadState('networkidle');

        firstCreditMemoRowIdCell = this.page
            .locator(UIReferenceCustomFees.adminSalesOrderViewPage.creditMemosTab.firstCreditMemoLocator);

        if (!(await firstCreditMemoRowIdCell.isVisible())) {
            throw new Error('No credit memos found.');
        }

        creditMemoIncrementId = (await firstCreditMemoRowIdCell.textContent())?.trim() ?? null;

        return creditMemoIncrementId;
    }
}

export default SalesOrderViewPage;
export { SkuAndQuantity };
