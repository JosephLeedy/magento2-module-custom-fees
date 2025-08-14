import { test } from '@playwright/test';
import { slugs, UIReference } from '@config';
import AddProductToCartStep from '@steps/addProductToCart.step';
import ChangeCurrencyToEuroStep from '@steps/changeCurrencyToEuro.step';
import EmptyCartStep from '@steps/emptyCart.step';
import LogInAsAdministratorStep from '@steps/logInAsAdministrator.step';
import PlaceOrderStep from '@steps/placeOrder.step';
import GuestOrderPage from '@poms/frontend/guestOrder.page';
import SalesOrderGridPage from '@poms/adminhtml/salesOrderGrid.page';
import SalesOrderViewPage from '@poms/adminhtml/salesOrderView.page';

test.describe('Custom fees are displayed on guest order page', (): void => {
    test.describe.configure({ retries: 3 });

    test.use({ bypassCSP: true });

    test.beforeEach(async ({ page }): Promise<void> => {
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
                    await new ChangeCurrencyToEuroStep(page).execute();
                }

                ({ orderNumber, orderEmail, orderLastName } = await new PlaceOrderStep(page, testInfo).execute());

                await guestOrderPage.navigateToOrdersAndReturnsPage();
                await guestOrderPage.fillOrderDetails(<string>orderNumber, orderEmail, orderLastName);
                await guestOrderPage.assertOrderIsVisible(<string>orderNumber);
                await guestOrderPage.assertOrderHasCustomFees(inEuro);
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
         * @feature Custom fees on guest invoice page
         * @scenario Guest places an order, views its invoice and sees the custom fees
         * @given The guest has placed an order with custom fees that has been invoiced
         * @when They view the invoice from the Orders and Returns page
         * @then They should see the custom fees in the invoice totals
         */
        test(
            `for an invoice${testSuffix}`,
            { tag: ['@frontend', '@guest', '@cold'] },
            async ({ page, browserName }, testInfo): Promise<void> => {
                const guestOrderPage = new GuestOrderPage(page);
                let orderNumber: string|null = '';
                let orderEmail: string = '';
                let orderLastName: string = '';
                let invoiceNumber: string|null = '';

                test.skip(browserName === 'webkit', 'Skipping test for Webkit due to an issue with CSP');

                if (inEuro) {
                    await new ChangeCurrencyToEuroStep(page).execute();
                }

                ({ orderNumber, orderEmail, orderLastName } = await new PlaceOrderStep(page, testInfo).execute());

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

                await guestOrderPage.navigateToOrdersAndReturnsPage();
                await guestOrderPage.fillOrderDetails(<string>orderNumber, orderEmail, orderLastName);
                await guestOrderPage.assertOrderIsVisible(<string>orderNumber);
                await guestOrderPage.navigateToInvoicesPage();
                await guestOrderPage.assertInvoiceHasCustomFees(invoiceNumber, inEuro);
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
         * @feature Custom fees on guest credit memo page
         * @scenario Guest places an order, requests a refund, views its credit memo and sees the custom fees
         * @given The guest has placed an order with custom fees that has been invoiced and refunded
         * @when They view the credit memo from the Orders and Returns page
         * @then They should see the refunded custom fees in the credit memo totals
         */
        test(
            `for a credit memo${testSuffix}`,
            { tag: ['@frontend', '@guest', '@cold'] },
            async ({ page, browserName }, testInfo): Promise<void> => {
                const guestOrderPage = new GuestOrderPage(page);
                let orderNumber: string|null = '';
                let orderEmail: string = '';
                let orderLastName: string = '';
                let invoiceNumber: string|null = '';
                let creditMemoNumber: string|null = '';

                test.skip(browserName === 'webkit', 'Skipping test for Webkit due to an issue with CSP');

                if (inEuro) {
                    await new ChangeCurrencyToEuroStep(page).execute();
                }

                ({ orderNumber, orderEmail, orderLastName } = await new PlaceOrderStep(page, testInfo).execute());

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

                await guestOrderPage.navigateToOrdersAndReturnsPage();
                await guestOrderPage.fillOrderDetails(<string>orderNumber, orderEmail, orderLastName);
                await guestOrderPage.assertOrderIsVisible(<string>orderNumber);
                await guestOrderPage.navigateToCreditMemosPage();
                await guestOrderPage.assertCreditMemoHasCustomFees(creditMemoNumber, inEuro);
            }
        );
    });
});