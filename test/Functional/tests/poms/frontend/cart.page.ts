import { expect } from '@playwright/test';
import { inputValuesCustomFees, UIReferenceCustomFees } from '@config';
import BaseCartPage from 'base-tests/poms/frontend/cart.page';

class CartPage extends BaseCartPage
{
    public async getCustomFees(inEuro: boolean = false): Promise<void> {
        for (const feeName in inputValuesCustomFees.customFees) {
            const cartSummary = this.page.locator(UIReferenceCustomFees.cartPage.cartSummaryLocator);
            const currencySymbol = inEuro ? '€' : '$';
            /* The regex below is naïve in that it does not account for the currency format, but it's not necessary to
               do so right now. If it becomes necessary, we'll look into improving this logic. */
            const subtotal = parseFloat(
                (
                    await cartSummary
                        .getByText(`${UIReferenceCustomFees.cartPage.subtotalTotalLabel} ${currencySymbol}`)
                        .textContent()
                    ?? '0'
                ).replace(/[^\d.]+/, '')
            );
            let label = inputValuesCustomFees.customFees[feeName].title;
            let amount = !inEuro
                ? inputValuesCustomFees.customFees[feeName].base_amount
                : inputValuesCustomFees.customFees[feeName].amount;

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

            await expect(cartSummary.getByText(label, { exact: true })).toBeVisible();
        }
    }
}

export default CartPage;
