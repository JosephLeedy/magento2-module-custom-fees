import { test } from '@playwright/test';
import { slugs, UIReference } from '@config';
import { requireEnv } from "@utils/env.utils";
import ProductPage from '@poms/frontend/product.page';
import CartPage from '@poms/frontend/cart.page';
import LoginPage from "@poms/frontend/login.page";

test.describe('Custom fees in guest cart', (): void => {
    test.beforeEach(async ({ page }): Promise<void> => {
        const productPage = new ProductPage(page);

        await productPage.addSimpleProductToCart(
            UIReference.productPage.simpleProductTitle,
            slugs.productpage.simpleProductSlug
        );

        await page.goto(slugs.cart.cartSlug);
    });

    [
        {
            inEuro: false,
            testSuffix: '',
        },
        {
            inEuro: true,
            testSuffix: ' in Euro',
        },
    ].forEach(({ inEuro, testSuffix })=> {
        /**
         * @feature Custom Fees are added to quote
         * @scenario Guest adds a product to their cart
         * @given I have added a product to my cart
         * @and I am on the cart page
         * @then I should see the custom fees in my cart
         */
        test(
            `Adds custom fees to cart${testSuffix}`,
            { tag: ['@frontend', '@cart', '@cold'] },
            async ({ page }): Promise<void> => {
                const cartPage = new CartPage(page);

                if (inEuro) {
                    await test.step('Change currency to Euro', async (): Promise<void> => {
                        const currencySwitcherContainer = page.locator('#switcher-currency, #currency-heading + div');

                        await currencySwitcherContainer.getByText('USD - US Dollar').click();
                        await currencySwitcherContainer.getByText('EUR - Euro').click();
                    });
                }

                await cartPage.getCustomFees();
            }
        );
    });
});

test.describe('Custom fees in customer cart', (): void => {
    /**
     * @feature Custom Fees are added to quote
     * @scenario Customer adds a product to their cart
     * @given I have added a product to my cart
     * @and I am on the cart page
     * @then I should see the custom fees in my cart
     */
    test(
        'Adds custom fees to cart',
        { tag: ['@frontend', '@cart', '@cold'] },
        async ({ page, browserName }): Promise<void> => {
            const cartPage = new CartPage(page);

            await test.step('Log in with account', async (): Promise<void> => {
                const browserEngine = browserName?.toUpperCase() || 'UNKNOWN';
                const loginPage = new LoginPage(page);
                const emailInputValue = requireEnv(`MAGENTO_EXISTING_ACCOUNT_EMAIL_${browserEngine}`);
                const passwordInputValue = requireEnv('MAGENTO_EXISTING_ACCOUNT_PASSWORD');

                await loginPage.login(emailInputValue, passwordInputValue);
            });
            await test.step('Add product to cart', async (): Promise<void> => {
                const productPage = new ProductPage(page);

                await productPage.addSimpleProductToCart(
                    UIReference.productPage.simpleProductTitle,
                    slugs.productpage.simpleProductSlug
                );
            });

            await page.goto(slugs.cart.cartSlug);

            await cartPage.getCustomFees();
        }
    );
});
