import { test } from '@playwright/test';
import { slugs, UIReference } from '@config';
import CurrencySwitcher from '@utils/currencySwitcher.utils';
import { requireEnv } from '@utils/env.utils';
import CartPage from '@poms/frontend/cart.page';
import CheckoutPage from '@poms/frontend/checkout.page';
import CustomerOrderPage from '@poms/frontend/customerOrder.page';
import LoginPage from '@poms/frontend/login.page';
import ProductPage from '@poms/frontend/product.page';

test.describe('Custom fees are displayed on customer order page', (): void => {
    test.describe.configure({ retries: 3 });

    test.use({ bypassCSP: true });

    test.beforeEach(async ({ page, browserName }): Promise<void> => {
        await test.step('Log in with account', async (): Promise<void> => {
            const browserEngine = browserName?.toUpperCase() || 'UNKNOWN';
            const loginPage = new LoginPage(page);
            const emailInputValue = requireEnv(`MAGENTO_EXISTING_ACCOUNT_EMAIL_${browserEngine}`);
            const passwordInputValue = requireEnv('MAGENTO_EXISTING_ACCOUNT_PASSWORD');

            await loginPage.login(emailInputValue, passwordInputValue);

            await page.goto(slugs.checkout.checkoutSlug);
            await page.waitForLoadState('networkidle');
        });

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
         * @feature Custom fees on customer order page
         * @scenario Customer places an order, views it and sees the custom fees
         * @given The customer has placed an order with custom fees
         * @when They view the order from the My Orders page of the Customer Account Dashboard
         * @then They should see the custom fees in the order totals
         */
        test(
            `for an order${testSuffix}`,
            { tag: ['@frontend', '@account', '@cold'] },
            async ({ page, browserName }, testInfo): Promise<void> => {
                const orderPage = new CustomerOrderPage(page);
                let orderNumber = '';

                test.skip(browserName === 'webkit', 'Skipping test for Webkit due to an issue with CSP');

                if (inEuro) {
                    await test.step('Change currency to Euro', async (): Promise<void> => {
                        await new CurrencySwitcher(page).switchCurrencyToEuro();
                    });
                }

                await test.step('Place order', async (): Promise<void> => {
                    orderNumber = await new CheckoutPage(page).placeMultiStepOrder();

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

                await page.goto(slugs.account.orderHistorySlug);
                await page.waitForLoadState('networkidle');

                await orderPage.navigateToOrderPage(orderNumber);
                await orderPage.orderHasCustomFees(inEuro);
            }
        );
    });
});
