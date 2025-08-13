import { test } from '@playwright/test';
import { slugs, UIReference } from '@config';
import AddProductToCartStep from '@steps/addProductToCart.step';
import ChangeCurrencyToEuroStep from '@steps/changeCurrencyToEuro.step';
import EmptyCartStep from '@steps/emptyCart.step';
import LogInAsAdministratorStep from '@steps/logInAsAdministratorStep';
import LogInAsCustomerStep from '@steps/logInAsCustomerStep';
import SalesOrderGridPage from '@poms/adminhtml/salesOrderGrid.page';
import SalesOrderViewPage from '@poms/adminhtml/salesOrderView.page';
import CheckoutPage from '@poms/frontend/checkout.page';
import CustomerOrderPage from '@poms/frontend/customerOrder.page';

test.describe('Custom fees are displayed on customer order page', (): void => {
    test.describe.configure({ retries: 3 });

    test.use({ bypassCSP: true });

    test.beforeEach(async ({ page, browserName }): Promise<void> => {
        await new LogInAsCustomerStep(page, browserName).execute(slugs.checkout.checkoutSlug);

        await new AddProductToCartStep(page).addSimpleProductToCart(
            UIReference.productPage.simpleProductTitle,
            slugs.productpage.simpleProductSlug
        );
    });

    test.afterEach(async ({ page }): Promise<void> => {
        // Assume the test failed if we're still in the Checkout and empty the cart to prevent future issues
        await new EmptyCartStep(page).execute(slugs.checkout.checkoutSlug);
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
                    await new ChangeCurrencyToEuroStep(page).execute();
                }

                await test.step('Place order', async (): Promise<void> => {
                   ({ orderNumber } = await new CheckoutPage(page).placeMultiStepOrder());

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

                await orderPage.navigateToOrderHistoryPage();
                await orderPage.navigateToOrderPage(orderNumber);
                await orderPage.assertOrderHasCustomFees(inEuro);
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
            async ({ page, browserName }, testInfo): Promise<void> => {
                const orderPage = new CustomerOrderPage(page);
                let orderNumber: string|null = '';
                let invoiceNumber: string|null = '';

                test.skip(browserName === 'webkit', 'Skipping test for Webkit due to an issue with CSP');

                if (inEuro) {
                    await new ChangeCurrencyToEuroStep(page).execute();
                }

                await test.step('Place order', async (): Promise<void> => {
                    ({ orderNumber } = await new CheckoutPage(page).placeMultiStepOrder());

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

                await new LogInAsAdministratorStep(page).execute();

                await test.step('Create invoice', async (): Promise<void> => {
                    const adminSalesOrderGridPage = new SalesOrderGridPage(page);
                    const adminSalesOrderViewPage = new SalesOrderViewPage(page);

                    await adminSalesOrderGridPage.navigateToSalesOrderGrid();
                    await adminSalesOrderGridPage.navigateToSalesOrderViewPage(<string>orderNumber);

                    invoiceNumber = await adminSalesOrderViewPage.createInvoice();

                    if (invoiceNumber === null) {
                        throw new Error(
                            'Something went wrong while creating the invoice. Please check the logs for more '
                            + 'information.'
                        );
                    }

                    testInfo.annotations.push({
                        type: 'Invoice number',
                        description: invoiceNumber
                    });
                });

                await orderPage.navigateToOrderHistoryPage();
                await orderPage.navigateToOrderPage(orderNumber);
                await orderPage.navigateToInvoicesPage();
                await orderPage.assertInvoiceHasCustomFees(invoiceNumber, inEuro);
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
            async ({ page, browserName }, testInfo): Promise<void> => {
                const orderPage = new CustomerOrderPage(page);
                let orderNumber: string|null = '';
                let invoiceNumber: string|null = '';
                let creditMemoNumber: string|null = '';

                test.skip(browserName === 'webkit', 'Skipping test for Webkit due to an issue with CSP');

                if (inEuro) {
                    await new ChangeCurrencyToEuroStep(page).execute();
                }

                await test.step('Place order', async (): Promise<void> => {
                    ({ orderNumber } = await new CheckoutPage(page).placeMultiStepOrder());

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

                await new LogInAsAdministratorStep(page).execute();

                await test.step('Create invoice', async (): Promise<void> => {
                    const adminSalesOrderGridPage = new SalesOrderGridPage(page);
                    const adminSalesOrderViewPage = new SalesOrderViewPage(page);

                    await adminSalesOrderGridPage.navigateToSalesOrderGrid();
                    await adminSalesOrderGridPage.navigateToSalesOrderViewPage(<string>orderNumber);

                    invoiceNumber = await adminSalesOrderViewPage.createInvoice();

                    if (invoiceNumber === null) {
                        throw new Error(
                            'Something went wrong while creating the invoice. Please check the logs for more '
                            + 'information.'
                        );
                    }

                    testInfo.annotations.push({
                        type: 'Invoice number',
                        description: invoiceNumber
                    });
                });

                await test.step('Create credit memo', async (): Promise<void> => {
                    const adminSalesOrderViewPage = new SalesOrderViewPage(page);

                    creditMemoNumber = await adminSalesOrderViewPage.createCreditMemo();

                    if (creditMemoNumber === null) {
                        throw new Error(
                            'Something went wrong while creating the credit memo. Please check the logs for more '
                            + 'information.'
                        );
                    }

                    testInfo.annotations.push({
                        type: 'Credit memo number',
                        description: creditMemoNumber
                    });
                });

                await orderPage.navigateToOrderHistoryPage();
                await orderPage.navigateToOrderPage(orderNumber);
                await orderPage.navigateToCreditMemosPage();
                await orderPage.assertCreditMemoHasCustomFees(creditMemoNumber, inEuro);
            }
        );
    });
});
