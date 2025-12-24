import { expect, Locator } from '@playwright/test';
import { outcomeMarker, outcomeMarkersCustomFees, slugs, UIReference, UIReferenceCustomFees } from '@config';
import CustomFees from '@utils/customFees.utils';
import { requireEnv } from '@utils/env.utils';
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

    public async applyDiscountCode(code: string, currencySymbol: string = '$'): Promise<void>
    {
        const showDiscountButton: Locator = this.showDiscountButton
            .or(this.page.getByRole('heading', { name: UIReference.cart.showDiscountFormButtonLabel }));
        const closeMessageButton: Locator = this.page.getByLabel(UIReference.general.closeMessageLabel);

        if (await this.page.getByPlaceholder(UIReference.cart.discountInputFieldLabel).isHidden()) {
            // Discount field is not open.
            await showDiscountButton.click();
        }

        await this.page.getByPlaceholder(UIReference.cart.discountInputFieldLabel).fill(code);
        await this.page
            .getByRole('button', { name: UIReference.cart.applyDiscountButtonLabel, exact: true })
            .click();
        await this.page.waitForLoadState();

        await expect
            .soft(
                this.page.getByText(`${outcomeMarker.cart.discountAppliedNotification} "${code}"`),
                `Notification that discount code '${code}' has been applied`,
            ).toBeVisible();
        await expect(
            this.page.getByText(`- ${currencySymbol}`).or(this.page.getByText(`-${currencySymbol}`)),
            `'- ${currencySymbol}' should be visible on the page`,
        ).toBeVisible();

        if (await closeMessageButton.isVisible()) {
            // Close message to prevent difficulties with other tests.
            await closeMessageButton.click();
        }
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

    public async assertHasCustomFeeDiscountApplied(inEuro: boolean = false, exclude: string[] = []): Promise<void>
    {
        const cartSummaryLocator = this.page.locator(UIReferenceCustomFees.cartPage.cartSummaryLocator);
        const couponCode: string = requireEnv('MAGENTO_COUPON_CODE_CUSTOM_FEES');
        const currencySymbol = inEuro ? '€' : '$';
        const subtotal = parseFloat(
            (await cartSummaryLocator.getByText(`Subtotal ${currencySymbol}`).textContent() ?? '0')
                .replace(/[^\d.]+/, ''),
        );
        const totalCustomFeeAmount = await new CustomFees().calculateTotal(cartSummaryLocator, inEuro, exclude);
        const discountAmount: string = ((subtotal + totalCustomFeeAmount) * 0.1).toFixed(2);

        await expect(
            cartSummaryLocator
                .getByText(`Discount (${couponCode}) - ${currencySymbol}${discountAmount}`)
                .or(cartSummaryLocator.getByText(`Discount (${couponCode}) -${currencySymbol}${discountAmount}`)),
        ).toBeVisible();
    }
}

export default CartPage;
