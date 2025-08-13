import { Page } from '@playwright/test';
import { step } from '@utils/stepDecorator.utils';
import CartPage from '@poms/frontend/cart.page';

class EmptyCartStep
{
    public constructor(private readonly page: Page) {}

    @step('Empty cart', { box: true })
    /**
     * Empty the cart.
     *
     * If a `checkUrl` value is provided, it checks if the current URL matches the provided value before emptying the
     * cart.
     *
     * @param {string|false} checkUrl - The URL to check for before emptying the cart. If `false`, the URL will not be
     *   checked (default).
     * @returns {Promise<void>} - A promise that resolves when the cart is empty.
     * @throws {Error} - Throws an error if the cart cannot be emptied.
     */
    public async execute(checkUrl: string|false = false): Promise<void>
    {
        if (checkUrl !== false && !this.page.url().includes(checkUrl)) {
            return;
        }

        await new CartPage(this.page).emptyCart();
    }
}

export default EmptyCartStep;
