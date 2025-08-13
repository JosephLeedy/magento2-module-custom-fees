import { test } from '@playwright/test';
import { slugs, UIReference } from '@config';
import CurrencySwitcher from '@utils/currencySwitcher.utils';
import LoginAsCustomerStep from '@steps/loginAsCustomer.step';
import CartPage from '@poms/frontend/cart.page';
import CheckoutPage from '@poms/frontend/checkout.page';
import ProductPage from '@poms/frontend/product.page';

test.describe('Custom fees display in checkout', (): void => {
    test.describe.configure({ retries: 3 });

    test.use({ bypassCSP: true });

    test.beforeEach(async ({ page }): Promise<void> => {
        const productPage = new ProductPage(page);

        await productPage.addSimpleProductToCart(
            UIReference.productPage.simpleProductTitle,
            slugs.productpage.simpleProductSlug
        );

        await page.goto(slugs.checkout.checkoutSlug);
        await page.waitForLoadState('networkidle');
    });

    test.afterEach(async ({ page }): Promise<void> => {
        const cartPage = new CartPage(page);

        await page.goto(slugs.cart.cartSlug);

        await cartPage.emptyCart();
    });

    [
        {
            asCustomer: false,
            inEuro: false,
            testTitle: 'for a guest',
        },
        {
            asCustomer: false,
            inEuro: true,
            testTitle: 'for a guest, in Euro',
        },
        {
            asCustomer: true,
            inEuro: false,
            testTitle: 'for a customer',
        },
        {
            asCustomer: true,
            inEuro: true,
            testTitle: 'for a customer, in Euro',
        },
    ].forEach(({ asCustomer, inEuro, testTitle }): void => {
        /**
         * @feature Custom Fees are displayed in checkout
         * @scenario Guest or customer sees custom fees in the checkout
         * @given A guest or customer has added a product to the cart
         * @when They check out
         * @then They should see the custom fees in the order totals
         */
        test(testTitle, { tag: ['@frontend', '@checkout', '@cold'] }, async ({ page, browserName }): Promise<void> => {
            const checkoutPage = new CheckoutPage(page);

            test.skip(browserName === 'webkit', 'Skipping test for Webkit due to an issue with CSP');

            if (asCustomer) {
                await new LoginAsCustomerStep(page, browserName).execute(slugs.checkout.checkoutSlug);
            }

            if (inEuro) {
                await test.step('Change currency to Euro', async (): Promise<void> => {
                    await new CurrencySwitcher(page).switchCurrencyToEuro();

                    await page.waitForLoadState('networkidle');
                });
            }

            await checkoutPage.fillShippingAddress();
            await checkoutPage.selectShippingMethod();
            await checkoutPage.proceedToReviewStep();
            await checkoutPage.hasCustomFees(inEuro);
        });
    });
});
