import { test } from '@playwright/test';
import { inputValuesCustomFees, slugs, UIReference } from '@config';
import AddProductToCartStep from '@steps/addProductToCart.step';
import ChangeCurrencyToEuroStep from '@steps/changeCurrencyToEuro.step';
import CreateCreditMemoStep from '@steps/createCreditMemo.step';
import CreateInvoiceStep from '@steps/createInvoice.step';
import EmptyCartStep from '@steps/emptyCart.step';
import LogInAsAdministratorStep from '@steps/logInAsAdministrator.step';
import LogInAsCustomerStep from '@steps/logInAsCustomer.step';
import PlaceOrderStep from '@steps/placeOrder.step';
import CustomerOrderPage from '@poms/frontend/customerOrder.page';

test.describe('Custom fees are displayed on customer order page', (): void => {
    test.describe.configure({ retries: 3 });

    test.beforeEach(async ({ page, browserName }): Promise<void> => {
        await new LogInAsCustomerStep(page, browserName).login(slugs.checkout.checkoutSlug);

        await new AddProductToCartStep(page).addSimpleProductToCart(
            UIReference.productPage.simpleProductTitle,
            slugs.productpage.simpleProductSlug
        );
    });

    test.afterEach(async ({ page }): Promise<void> => {
        // Assume the test failed if we're still in the Checkout and empty the cart to prevent future issues
        await new EmptyCartStep(page).emptyCart(slugs.checkout.checkoutSlug);
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
            async ({ page }, testInfo): Promise<void> => {
                const orderPage = new CustomerOrderPage(page);
                const excludedFees = Object
                    .keys(inputValuesCustomFees.customFees)
                    .filter(key => key.includes('disabled'));
                let orderNumber: string|null = '';

                if (inEuro) {
                    await new ChangeCurrencyToEuroStep(page).changeCurrency();
                }

                ({ orderNumber } = await new PlaceOrderStep(page, testInfo).placeOrder());

                await orderPage.navigateToOrderHistoryPage();
                await orderPage.navigateToOrderPage(<string>orderNumber);
                await orderPage.assertOrderHasCustomFees(inEuro, excludedFees);
            }
        );
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
         * @feature Custom fees on customer invoice page
         * @scenario Customer places an order, views its invoice and sees the custom fees
         * @given The customer has placed an order with custom fees that has been invoiced
         * @when They view the invoice from the My Orders page of the Customer Account Dashboard
         * @then They should see the custom fees in the invoice totals
         */
        test(
            `for an invoice${testSuffix}`,
            { tag: ['@frontend', '@account', '@cold'] },
            async ({ page }, testInfo): Promise<void> => {
                const orderPage = new CustomerOrderPage(page);
                const excludedFees = Object
                    .keys(inputValuesCustomFees.customFees)
                    .filter(key => key.includes('disabled'));
                let orderNumber: string|null = '';
                let invoiceNumber: string|null = '';

                if (inEuro) {
                    await new ChangeCurrencyToEuroStep(page).changeCurrency();
                }

                ({ orderNumber} = await new PlaceOrderStep(page, testInfo).placeOrder());

                await new LogInAsAdministratorStep(page).login();

                invoiceNumber = await new CreateInvoiceStep(page, testInfo).createInvoice(<string>orderNumber);

                await orderPage.navigateToOrderHistoryPage();
                await orderPage.navigateToOrderPage(<string>orderNumber);
                await orderPage.navigateToInvoicesPage();
                await orderPage.assertInvoiceHasCustomFees(invoiceNumber, inEuro, excludedFees);
            }
        );
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
         * @feature Custom fees on customer credit memo page
         * @scenario Customer places an order, requests a refund, views its credit memo and sees the custom fees
         * @given The customer has placed an order with custom fees that has been invoiced and refunded
         * @when They view the credit memo from the My Orders page of the Customer Account Dashboard
         * @then They should see the refunded custom fees in the credit memo totals
         */
        test(
            `for a credit memo${testSuffix}`,
            { tag: ['@frontend', '@account', '@cold'] },
            async ({ page }, testInfo): Promise<void> => {
                const orderPage = new CustomerOrderPage(page);
                const excludedFees = Object
                    .keys(inputValuesCustomFees.customFees)
                    .filter(key => key.includes('disabled'));
                let orderNumber: string|null = '';
                let creditMemoNumber: string|null = '';

                if (inEuro) {
                    await new ChangeCurrencyToEuroStep(page).changeCurrency();
                }

                ({ orderNumber } = await new PlaceOrderStep(page, testInfo).placeOrder());

                await new LogInAsAdministratorStep(page).login();
                await new CreateInvoiceStep(page, testInfo).createInvoice(<string>orderNumber);

                creditMemoNumber = await new CreateCreditMemoStep(page, testInfo).createCreditMemo();

                await orderPage.navigateToOrderHistoryPage();
                await orderPage.navigateToOrderPage(<string>orderNumber);
                await orderPage.navigateToCreditMemosPage();
                await orderPage.assertCreditMemoHasCustomFees(creditMemoNumber, inEuro, excludedFees);
            }
        );
    });
});
