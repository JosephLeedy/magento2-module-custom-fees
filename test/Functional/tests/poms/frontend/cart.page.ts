import { expect, Locator } from '@playwright/test';
import { UIReferenceCustomFees } from '@config';
import CustomFees from '@utils/customFees.utils';
import BaseCartPage from 'base-tests/poms/frontend/cart.page';

class CartPage extends BaseCartPage
{
    public async emptyCart(): Promise<void>
    {
        const removeItemButtons = await this.page.locator(UIReferenceCustomFees.cartPage.removeItemButtonLocator).all();
        let removeItemButton: Locator;

        for (removeItemButton of removeItemButtons) {
            removeItemButton.click();
        }

        await this.page.waitForLoadState('networkidle');

        await expect(
            this.page
                .locator(UIReferenceCustomFees.cartPage.emptyCartMessageContainerLocator)
                .getByText(UIReferenceCustomFees.cartPage.emptyCartMessage)
        ).toBeVisible();
    }

    public async hasCustomFees(inEuro: boolean = false, exclude: string[] = []): Promise<void>
    {
        const cartSummaryLocator = this.page.locator(UIReferenceCustomFees.cartPage.cartSummaryLocator);
        const customFees = await new CustomFees().getAll(cartSummaryLocator, inEuro, exclude);

        for (const customFee of customFees) {
            await expect(customFee).toBeVisible();
        }
    }

    public async doesNotHaveCustomFees(inEuro: boolean = false, exclude: string[] = []): Promise<void>
    {
        const cartSummaryLocator = this.page.locator(UIReferenceCustomFees.cartPage.cartSummaryLocator);
        const customFees = await new CustomFees().getAll(cartSummaryLocator, inEuro, exclude);

        for (const customFee of customFees) {
            await expect(customFee).not.toBeVisible();
        }
    }
}

export default CartPage;
