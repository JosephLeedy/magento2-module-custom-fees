import { expect, test } from '@playwright/test';
import { slugs, UIReference, UIReferenceCustomFees } from '@config';
import { requireEnv } from "@utils/env.utils";
import ProductPage from '@poms/frontend/product.page';
import CartPage from '@poms/frontend/cart.page';
import LoginPage from "@poms/frontend/login.page";

test.describe('Custom fees in cart', (): void => {
    test.describe.configure({ retries: 3 });

    test.beforeEach(async ({ page }): Promise<void> => {
        const productPage = new ProductPage(page);

        await productPage.addSimpleProductToCart(
            UIReference.productPage.simpleProductTitle,
            slugs.productpage.simpleProductSlug
        );

        await page.goto(slugs.cart.cartSlug);
    });

    test.afterEach(async ({ page }): Promise<void> => {
        const cartPage = new CartPage(page);

        await cartPage.emptyCart();
    });

    [
        {
            asCustomer: false,
            inEuro: false,
            testSuffix: 'as a guest',
        },
        {
            asCustomer: false,
            inEuro: true,
            testSuffix: 'as a guest, in Euro',
        },
        {
            asCustomer: true,
            inEuro: false,
            testSuffix: 'as a customer',
        },
        {
            asCustomer: true,
            inEuro: true,
            testSuffix: 'as a customer, in Euro',
        },
    ].forEach(({ asCustomer, inEuro, testSuffix }): void => {
        /**
         * @feature Custom Fees are added to quote
         * @scenario Guest or customer adds a product to their cart
         * @given I have added a product to my cart
         * @and I am on the cart page
         * @then I should see the custom fees in my cart
         */
        test(
            `Adds custom fees to cart ${testSuffix}`,
            { tag: ['@frontend', '@cart', '@cold'] },
            async ({ page, browserName }): Promise<void> => {
                const cartPage = new CartPage(page);

                if (asCustomer) {
                    await test.step('Log in with account', async (): Promise<void> => {
                        const browserEngine = browserName?.toUpperCase() || 'UNKNOWN';
                        const loginPage = new LoginPage(page);
                        const emailInputValue = requireEnv(`MAGENTO_EXISTING_ACCOUNT_EMAIL_${browserEngine}`);
                        const passwordInputValue = requireEnv('MAGENTO_EXISTING_ACCOUNT_PASSWORD');

                        await loginPage.login(emailInputValue, passwordInputValue);

                        await page.goto(slugs.cart.cartSlug);
                    });
                }

                if (inEuro) {
                    await test.step('Change currency to Euro', async (): Promise<void> => {
                        const currencySwitcherContainer = page.locator(
                            UIReferenceCustomFees.common.currencySwitcher.containerLocator,
                        );

                        await currencySwitcherContainer
                            .getByText(UIReferenceCustomFees.common.currencySwitcher.usDollarLabel)
                            .click();
                        await currencySwitcherContainer
                            .getByText(UIReferenceCustomFees.common.currencySwitcher.euroLabel)
                            .click();
                        await expect(currencySwitcherContainer.getByRole('button'))
                            .toHaveText(UIReferenceCustomFees.common.currencySwitcher.euroLabel);
                    });
                }

                await cartPage.hasCustomFees(inEuro);
            }
        );
    });
});
