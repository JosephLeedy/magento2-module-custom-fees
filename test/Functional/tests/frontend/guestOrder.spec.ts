import { test } from '@playwright/test';
import { slugs, UIReference } from '@config';
import AddProductToCartStep from '@steps/addProductToCart.step';
import ChangeCurrencyToEuroStep from '@steps/changeCurrencyToEuro.step';
import CreateCreditMemoStep from '@steps/createCreditMemo.step';
import CreateInvoiceStep from '@steps/createInvoice.step';
import EmptyCartStep from '@steps/emptyCart.step';
import LogInAsAdministratorStep from '@steps/logInAsAdministrator.step';
import PlaceOrderStep from '@steps/placeOrder.step';
import GuestOrderPage from '@poms/frontend/guestOrder.page';

test.describe('Custom fees are displayed on guest order page', (): void => {
    test.describe.configure({ retries: 3 });

    test.beforeEach(async ({ page }): Promise<void> => {
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
         * @feature Custom fees on guest order page
         * @scenario Guest places an order, views it and sees the custom fees
         * @given The guest has placed an order with custom fees
         * @when They view the order from the Orders and Returns page
         * @then They should see the custom fees in the order totals
         */
        test(
            `for an order${testSuffix}`,
            { tag: ['@frontend', '@guest', '@cold'] },
            async ({ page }, testInfo): Promise<void> => {
                const guestOrderPage = new GuestOrderPage(page);
                let orderNumber: string|null = '';
                let orderEmail: string = '';
                let orderLastName: string = '';

                if (inEuro) {
                    await new ChangeCurrencyToEuroStep(page).changeCurrency();
                }

                ({ orderNumber, orderEmail, orderLastName } = await new PlaceOrderStep(page, testInfo).placeOrder());

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
            async ({ page }, testInfo): Promise<void> => {
                const guestOrderPage = new GuestOrderPage(page);
                let orderNumber: string|null = '';
                let orderEmail: string = '';
                let orderLastName: string = '';
                let invoiceNumber: string|null = '';

                if (inEuro) {
                    await new ChangeCurrencyToEuroStep(page).changeCurrency();
                }

                ({ orderNumber, orderEmail, orderLastName } = await new PlaceOrderStep(page, testInfo).placeOrder());

                await new LogInAsAdministratorStep(page).login();

                invoiceNumber = await new CreateInvoiceStep(page, testInfo).createInvoice(<string>orderNumber);

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
            partial: false,
        },
        {
            testSuffix: ', in Euro',
            inEuro: true,
            partial: false,
        },
        {
            testSuffix: '',
            inEuro: false,
            partial: true,
        },
        {
            testSuffix: ', in Euro',
            inEuro: true,
            partial: true,
        },
    ].forEach(({ testSuffix, inEuro, partial }): void => {
        /**
         * @feature Custom fees on guest credit memo page
         * @scenario Guest places an order, requests a refund, views its credit memo and sees the custom fees
         * @given The guest has placed an order with custom fees that has been invoiced and refunded
         * @when They view the credit memo from the Orders and Returns page
         * @then They should see the refunded custom fees in the credit memo totals
         */
        test(
            `for a${partial ? ' partial' : ''} credit memo${testSuffix}`,
            { tag: ['@frontend', '@guest', '@cold'] },
            async ({ page }, testInfo): Promise<void> => {
                const guestOrderPage = new GuestOrderPage(page);
                let orderNumber: string|null = '';
                let orderEmail: string = '';
                let orderLastName: string = '';
                let creditMemoNumber: string|null = '';

                if (inEuro) {
                    await new ChangeCurrencyToEuroStep(page).changeCurrency();
                }

                ({ orderNumber, orderEmail, orderLastName } = await new PlaceOrderStep(page, testInfo).placeOrder());

                await new LogInAsAdministratorStep(page).login();
                await new CreateInvoiceStep(page, testInfo).createInvoice(<string>orderNumber);

                creditMemoNumber = await new CreateCreditMemoStep(page, testInfo).createCreditMemo(undefined, partial);

                await guestOrderPage.navigateToOrdersAndReturnsPage();
                await guestOrderPage.fillOrderDetails(<string>orderNumber, orderEmail, orderLastName);
                await guestOrderPage.assertOrderIsVisible(<string>orderNumber);
                await guestOrderPage.navigateToCreditMemosPage();
                await guestOrderPage.assertCreditMemoHasCustomFees(creditMemoNumber, inEuro, [], partial);
            }
        );
    });
});