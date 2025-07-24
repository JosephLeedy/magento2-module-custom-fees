import { expect, Locator } from '@playwright/test';
import { inputValuesCustomFees, UIReferenceCustomFees } from '@config';
import BaseCheckoutPage from 'base-tests/poms/frontend/checkout.page';

class CheckoutPage extends BaseCheckoutPage
{
    public async hasCustomFees(inEuro: boolean = false, exclude: string[] = []): Promise<void> {
        const customFees = await this.getCustomFees(inEuro, exclude);

        for (const customFee of customFees) {
            await expect(customFee).toBeVisible();
        }
    }

    public async doesNotHaveCustomFees(inEuro: boolean = false, exclude: string[] = []): Promise<void> {
        const customFees = await this.getCustomFees(inEuro, exclude);

        for (const customFee of customFees) {
            await expect(customFee).not.toBeVisible();
        }
    }

    private async getCustomFees(inEuro: boolean = false, exclude: string[] = []): Promise<Locator[]> {
        const customFees = [];
        const orderSummary = this.page.locator(UIReferenceCustomFees.checkoutPage.orderSummaryLocator);
        const currencySymbol = inEuro ? '€' : '$';
        /* The regex below is naïve in that it does not account for the currency format, but it's not necessary to do
           so right now. If it becomes necessary, we'll look into improving this logic. */
        const subtotal = parseFloat(
            (
                await orderSummary
                    .filter(
                        {
                            has: this.page.locator(UIReferenceCustomFees.checkoutPage.subtotalTotalLocator),
                        }
                    ).textContent()
                ?? '0'
            ).replace(/[^\d.]+/, '')
        );

        for (const feeName in inputValuesCustomFees.customFees) {
            let label = inputValuesCustomFees.customFees[feeName].title;
            let amount = !inEuro
                ? inputValuesCustomFees.customFees[feeName].base_amount
                : inputValuesCustomFees.customFees[feeName].amount;

            if (exclude.includes(feeName)) {
                continue;
            }

            if (
                inputValuesCustomFees.customFees[feeName].hasOwnProperty('advanced')
                && inputValuesCustomFees.customFees[feeName].advanced.hasOwnProperty('show_percentage')
                && inputValuesCustomFees.customFees[feeName].advanced.show_percentage
            ) {
                label += ` (${inputValuesCustomFees.customFees[feeName].amount}%)`;
            }

            if (inputValuesCustomFees.customFees[feeName].type.toLowerCase() === 'percent') {
                amount = ((amount * subtotal) / 100).toFixed(2);
            }

            label += ` ${currencySymbol}${amount}`;

            customFees.push(orderSummary.getByText(label, { exact: true }));
        }

        return customFees;
    }
}