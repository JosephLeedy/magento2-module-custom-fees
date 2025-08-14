import { test } from '@playwright/test';
import { inputValuesCustomFees, slugs, UIReference } from '@config';
import AddProductToCartStep from '@steps/addProductToCart.step';
import ChangeCurrencyToEuroStep from '@steps/changeCurrencyToEuro.step';
import EmptyCartStep from '@steps/emptyCart.step';
import LogInAsCustomerStep from '@steps/logInAsCustomer.step';
import CartPage from '@poms/frontend/cart.page';

test.describe('Custom fees are added to cart', (): void => {
    test.describe.configure({ retries: 3 });

    test.beforeEach(async ({ page }): Promise<void> => {
        await new AddProductToCartStep(page).addSimpleProductToCart(
            UIReference.productPage.simpleProductTitle,
            slugs.productpage.simpleProductSlug
        );
    });

    test.afterEach(async ({ page }): Promise<void> => {
        await new EmptyCartStep(page).execute();
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
         * @scenario Guest or customer sees custom fees in their cart
         * @given A guest or customer has added a product to the cart
         * @when They visit the cart page
         * @then They should see the custom fees in the cart totals
         */
        test(testTitle, { tag: ['@frontend', '@cart', '@cold'] }, async ({ page, browserName }): Promise<void> => {
            const cartPage = new CartPage(page);
            const excludedFees = Object
                .keys(inputValuesCustomFees.customFees)
                .filter((key) => key.includes('conditional'));

            if (asCustomer) {
                await new LogInAsCustomerStep(page, browserName).execute(slugs.cart.cartSlug);
            }

            if (inEuro) {
                await new ChangeCurrencyToEuroStep(page).execute();
            }

            await cartPage.hasCustomFees(inEuro, excludedFees);
        });
    });
});

test.describe('Conditional custom fees', (): void => {
    /**
     * @feature Matching conditional custom fees are added to quote
     * @scenario Guest or customer sees matching conditional custom fees in their cart
     * @given A guest or customer has added a product to the cart matching specific conditions
     * @when They visit the cart page
     * @then They should see the custom fees in the cart totals that match the conditions
     */
    test(
        'are added to cart for matching product',
        { tag: ['@frontend', '@cart', '@cold'] },
        async ({ page }): Promise<void> => {
            const cartPage = new CartPage(page);
            const excludedFees = Object
                .keys(inputValuesCustomFees.customFees)
                .filter(key => !key.includes('conditional'));

            await new AddProductToCartStep(page).addSimpleProductToCart(
                UIReference.productPage.simpleProductTitle,
                slugs.productpage.simpleProductSlug
            );

            await cartPage.hasCustomFees(false, excludedFees);
        }
    );

    /**
     * @feature Non-matching conditional custom fees are not added to quote
     * @scenario Guest or customer does not see non-matching conditional custom fees in their cart
     * @given A guest or customer has added a product to the cart not matching specific conditions
     * @when They visit the cart page
     * @then They should not see the custom fees in the cart totals that do not match the conditions
     */
    test(
        'are not added to cart for non-matching product',
        { tag: ['@frontend', '@cart', '@cold'] },
        async ({ page }): Promise<void> => {
            const cartPage = new CartPage(page);
            const excludedFees = Object
                .keys(inputValuesCustomFees.customFees)
                .filter(key => !key.includes('conditional'));

            await new AddProductToCartStep(page).addSimpleProductToCart(
                UIReference.productPage.simpleProductTitle,
                slugs.productpage.simpleProductSlug
            );

            await cartPage.doesNotHaveCustomFees(false, excludedFees);
        }
    );
});
