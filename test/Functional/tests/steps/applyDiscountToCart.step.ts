import { Page } from '@playwright/test';
import { slugs } from '@config';
import { step } from '@utils/stepDecorator.utils';
import CartPage from '@poms/frontend/cart.page';

class ApplyDiscountToCartStep
{
    public constructor(private readonly page: Page) {}

    @step('Apply discount to cart', { box: true })
    /**
     * Applys a discount to the cart.
     *
     * @param {string} discountCode - The coupon code for the discount to be applied to the cart.
     * @returns {Promise<void>} - A promise that resolves when the discount is applied to the cart.
     * @throws {Error} - Throws an error if the discount cannot be applied to the cart.
     */
    public async applyDiscountToCart(discountCode: string, currencySymbol: string): Promise<void>
    {
        const cartPage = new CartPage(this.page);

        if (!this.page.url().includes(slugs.cart.cartSlug)) {
            await this.page.goto(slugs.cart.cartSlug);
            await this.page.waitForLoadState('networkidle');
        }

        await cartPage.applyDiscountCode(discountCode, currencySymbol);
    }
}

export default ApplyDiscountToCartStep;
