import { test } from '@playwright/test';
import { inputValuesCustomFees, slugs, UIReference } from '@config';
import CurrencySwitcher from "@utils/currencySwitcher.utils";
import { requireEnv } from "@utils/env.utils";
import ProductPage from '@poms/frontend/product.page';
import CartPage from '@poms/frontend/cart.page';
import LoginPage from "@poms/frontend/login.page";

test.describe('Custom fees are added to cart', (): void => {
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
         * @feature Custom Fees are added to quote
         * @scenario Guest or customer adds a product to their cart
         * @given I have added a product to my cart
         * @and I am on the cart page
         * @then I should see the custom fees in my cart
         */
        test(testTitle, { tag: ['@frontend', '@cart', '@cold'] }, async ({ page, browserName }): Promise<void> => {
            const cartPage = new CartPage(page);
            const excludedFees = Object
                .keys(inputValuesCustomFees.customFees)
                .filter((key) => key.includes('conditional'));

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
                    await new CurrencySwitcher(page).switchCurrencyToEuro();
                });
            }

            await cartPage.hasCustomFees(inEuro, excludedFees);
        });
    });
});

test.describe('Conditional custom fees', (): void => {
    /**
     * @feature Conditional custom fees are added to quote
     * @scenario Guest or customer adds a product to their cart
     * @given I have added a product to my cart
     * @and I am on the cart page
     * @then I should see the matching custom fees in my cart
     */
    test(
        'are added to cart for matching product',
        { tag: ['@frontend', '@cart', '@cold'] },
        async ({ page }): Promise<void> => {
            const cartPage = new CartPage(page);
            const excludedFees = Object
                .keys(inputValuesCustomFees.customFees)
                .filter(key => !key.includes('conditional'));

            await test.step('Add product to cart', async (): Promise<void> => {
                const productPage = new ProductPage(page);

                await productPage.addSimpleProductToCart(
                    UIReference.productPage.simpleProductTitle,
                    slugs.productpage.simpleProductSlug
                );
            });

            await page.goto(slugs.cart.cartSlug);

            await cartPage.hasCustomFees(false, excludedFees);
        }
    );

    /**
     * @feature Conditional custom fees are not added to quote
     * @scenario Guest or customer adds a product to their cart
     * @given I have added a product to my cart
     * @and I am on the cart page
     * @then I should not see the non-matching custom fees in my cart
     */
    test(
        'are not added to cart for non-matching product',
        { tag: ['@frontend', '@cart', '@cold'] },
        async ({ page }): Promise<void> => {
            const cartPage = new CartPage(page);
            const excludedFees = Object
                .keys(inputValuesCustomFees.customFees)
                .filter(key => !key.includes('conditional'));

            await test.step('Add product to cart', async (): Promise<void> => {
                const productPage = new ProductPage(page);

                await productPage.addSimpleProductToCart(
                    UIReference.productPage.secondSimpleProducTitle,
                    slugs.productpage.secondSimpleProductSlug
                );
            });

            await page.goto(slugs.cart.cartSlug);

            await cartPage.doesNotHaveCustomFees(false, excludedFees);
        }
    );
});
