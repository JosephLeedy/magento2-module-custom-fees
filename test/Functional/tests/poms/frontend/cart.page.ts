import { expect, Locator } from '@playwright/test';
import { outcomeMarkersCustomFees, slugs, UIReferenceCustomFees } from '@config';
import CustomFees from '@utils/customFees.utils';
import BaseCartPage from 'base-tests/poms/frontend/cart.page';

class CartPage extends BaseCartPage
{
    public async emptyCart(): Promise<void>
    {
        let removeItemButtons: Array<Locator>;
        let removeItemButton: Locator;

        if (!this.page.url().includes(slugs.cart.cartSlug)) {
            await this.page.goto(slugs.cart.cartSlug);
            await this.page.waitForLoadState('networkidle');
        }

        removeItemButtons = await this.page.locator(UIReferenceCustomFees.cartPage.removeItemButtonLocator).all();

        for (removeItemButton of removeItemButtons) {
            removeItemButton.click();
        }

        await this.page.waitForLoadState('networkidle');

        await expect(
            this.page
                .locator(UIReferenceCustomFees.cartPage.emptyCartMessageContainerLocator)
                .getByText(outcomeMarkersCustomFees.cartPage.emptyCartMessage)
        ).toBeVisible();
    }

    public async assertHasCustomFees(inEuro: boolean = false, exclude: string[] = []): Promise<void>
    {
        const cartSummaryLocator = this.page.locator(UIReferenceCustomFees.cartPage.cartSummaryLocator);
        const customFees = await new CustomFees().getAll(cartSummaryLocator, inEuro, exclude);

        for (const customFee of customFees) {
            await expect(customFee).toBeVisible();
        }
    }

    public async assertDoesNotHaveCustomFees(inEuro: boolean = false, exclude: string[] = []): Promise<void>
    {
        const cartSummaryLocator = this.page.locator(UIReferenceCustomFees.cartPage.cartSummaryLocator);
        const customFees = await new CustomFees().getAll(cartSummaryLocator, inEuro, exclude);

        for (const customFee of customFees) {
            await expect(customFee).not.toBeVisible();
        }
    }
}

export default CartPage;
