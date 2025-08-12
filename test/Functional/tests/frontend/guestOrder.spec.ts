import { test } from '@playwright/test';
import { slugs, UIReference } from '@config';
import CurrencySwitcher from '@utils/currencySwitcher.utils';
import CartPage from '@poms/frontend/cart.page';
import CheckoutPage from '@poms/frontend/checkout.page';
import GuestOrderPage from '@poms/frontend/guestOrder.page';
import ProductPage from '@poms/frontend/product.page';

test.describe('Custom fees are displayed on guest order page', (): void => {
    test.describe.configure({ retries: 3 });

    test.use({ bypassCSP: true });

    test.beforeEach(async ({ page }): Promise<void> => {
        await test.step('Add product to cart', async (): Promise<void> => {
            await new ProductPage(page).addSimpleProductToCart(
                UIReference.productPage.simpleProductTitle,
                slugs.productpage.simpleProductSlug
            );
        });
    });

    test.afterEach(async ({ page }): Promise<void> => {
        if (!page.url().includes(slugs.checkout.checkoutSlug)) {
            return;
        }

        // Assume the test failed if we're still in the Checkout and empty the cart to prevent future issues
        await new CartPage(page).emptyCart();
    });

    [
        {
            testSuffix: '',
            inEuro: false,
        },
        {
            testSuffix: ', in Euro',
            inEuro: true,
        },
    ].forEach(({ testSuffix, inEuro }): void => {
        /**
         * @feature Custom fees on guest order page
         * @scenario Guest places an order, views it and sees the custom fees
         * @given The guest has placed an order with custom fees
         * @when They view the order from the Orders and Returns page
         * @then They should see the custom fees in the order totals
         */
        test(
            `for an order${testSuffix}`,
            { tag: ['@frontend', '@guest', '@cold'] },
            async ({ page, browserName }, testInfo): Promise<void> => {
                const guestOrderPage = new GuestOrderPage(page);
                let orderNumber: string|null = '';
                let orderEmail: string = '';
                let orderLastName: string = '';

                test.skip(browserName === 'webkit', 'Skipping test for Webkit due to an issue with CSP');

                if (inEuro) {
                    await test.step('Change currency to Euro', async (): Promise<void> => {
                        await new CurrencySwitcher(page).switchCurrencyToEuro();
                    });
                }

                await test.step('Place order', async (): Promise<void> => {
                    ({ orderNumber, orderEmail, orderLastName } = await new CheckoutPage(page).placeMultiStepOrder());

                    if (orderNumber === null) {
                        throw new Error(
                            'Something went wrong while placing the order. Please check the logs for more information.'
                        );
                    }

                    testInfo.annotations.push({
                        type: 'Order number',
                        description: orderNumber
                    });
                });

                await guestOrderPage.navigateToOrdersAndReturnsPage();
                await guestOrderPage.fillOrderDetails(orderNumber, orderEmail, orderLastName);
                await guestOrderPage.assertOrderIsVisible(orderNumber);
                await guestOrderPage.assertOrderHasCustomFees(inEuro);
            }
        );
    });
});