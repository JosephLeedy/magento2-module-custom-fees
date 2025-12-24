import { test } from '@playwright/test';
import { inputValuesCustomFees, slugs, UIReference } from '@config';
import { requireEnv } from '@utils/env.utils';
import AddProductToCartStep from '@steps/addProductToCart.step';
import ApplyDiscountToCartStep from '@steps/applyDiscountToCart.step';
import ChangeCurrencyToEuroStep from '@steps/changeCurrencyToEuro.step';
import EmptyCartStep from '@steps/emptyCart.step';
import LogInAsCustomerStep from '@steps/logInAsCustomer.step';
import CheckoutPage from '@poms/frontend/checkout.page';

test.describe('Custom fees display in checkout', (): void => {
    test.describe.configure({ retries: 3 });

    test.beforeEach(async ({ page }): Promise<void> => {
        await new AddProductToCartStep(page).addSimpleProductToCart(
            UIReference.productPage.simpleProductTitle,
            slugs.productpage.simpleProductSlug
        );
    });

    test.afterEach(async ({ page }): Promise<void> => {
        await new EmptyCartStep(page).emptyCart();
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
            const excludedFees = Object
                .keys(inputValuesCustomFees.customFees)
                .filter(key => key.includes('disabled'));

            if (asCustomer) {
                await new LogInAsCustomerStep(page, browserName).login();
            }

            if (inEuro) {
                await new ChangeCurrencyToEuroStep(page).changeCurrency();
            }

            await checkoutPage.navigateToCheckoutPage();
            await checkoutPage.fillShippingAddress();
            await checkoutPage.selectShippingMethod();
            await checkoutPage.proceedToReviewStep();
            await checkoutPage.assertHasCustomFees(inEuro, excludedFees);
        });
    });
});

test.describe('Custom fee discounts are applied in checkout', (): void => {
    test.describe.configure({ retries: 3 });

    test.beforeEach(async ({ page }): Promise<void> => {
        await new AddProductToCartStep(page).addSimpleProductToCart(
            UIReference.productPage.simpleProductTitle,
            slugs.productpage.simpleProductSlug
        );
    });

    test.afterEach(async ({ page }): Promise<void> => {
        await new EmptyCartStep(page).emptyCart();
    });

    [
        {
            testTitle: 'for a guest',
            asCustomer: false,
            inEuro: false,
        },
        {
            testTitle: 'for a guest, in Euro',
            asCustomer: false,
            inEuro: true,
        },
        {
            testTitle: 'for a customer',
            asCustomer: true,
            inEuro: false,
        },
        {
            testTitle: 'for a customer, in Euro',
            asCustomer: true,
            inEuro: true,
        },
    ].forEach(({ asCustomer, inEuro, testTitle }): void => {
        /**
         * @feature Custom Fee discounts are applied in checkout
         * @scenario Guest or customer sees a discount for custom fees in the checkout
         * @given A guest or customer has added a product to the cart
         * @when They enter a coupon code that applies discounts to custom fees in the cart
         * @and They check out
         * @then They should see the custom fee discounts in the order totals
         */
        test(
            testTitle,
            { tag: ['@frontend', '@checkout', '@discount', '@cold'] },
            async ({ page, browserName }): Promise<void> => {
                const checkoutPage = new CheckoutPage(page);
                const excludedFees = Object
                    .keys(inputValuesCustomFees.customFees)
                    .filter(key => key.includes('disabled'));

                if (asCustomer) {
                    await new LogInAsCustomerStep(page, browserName).login();
                }

                if (inEuro) {
                    await new ChangeCurrencyToEuroStep(page).changeCurrency();
                }

                await new ApplyDiscountToCartStep(page)
                    .applyDiscountToCart(requireEnv('MAGENTO_COUPON_CODE_CUSTOM_FEES'), !inEuro ? '$' : '€');

                await checkoutPage.navigateToCheckoutPage();
                await checkoutPage.fillShippingAddress();
                await checkoutPage.selectShippingMethod();
                await checkoutPage.proceedToReviewStep();
                await checkoutPage.assertHasCustomFeeDiscountApplied(inEuro, excludedFees);
            },
        );
    });
});
