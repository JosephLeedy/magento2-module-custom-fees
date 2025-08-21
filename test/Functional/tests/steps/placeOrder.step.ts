import { Page, TestInfo } from '@playwright/test';
import { step } from '@utils/stepDecorator.utils';
import CheckoutPage from '@poms/frontend/checkout.page';

class PlaceOrderStep
{
    public constructor(private readonly page: Page, private readonly testInfo: TestInfo) {}

    @step('Place order', { box: true })
    /**
     * Places an order from the checkout page.
     *
     * @returns {Promise<OrderDetails>} - A promise that resolves to an object containing the order details.
     * @throws {Error} - Throws an error if the order cannot be placed.
     */
    public async placeOrder(): Promise<OrderDetails>
    {
        const checkoutPage = new CheckoutPage(this.page);
        let orderNumber: string|null = '';
        let orderEmail: string = '';
        let orderLastName: string = '';

        await checkoutPage.navigateToCheckoutPage();

        ({ orderNumber, orderEmail, orderLastName } = await checkoutPage.placeMultiStepOrder());

        if (orderNumber === null) {
            throw new Error(
                'Something went wrong while placing the order. Please check the logs for more information.'
            );
        }

        this.testInfo.annotations.push({
            type: 'Order number',
            description: orderNumber
        });

        return {
            orderNumber: orderNumber,
            orderEmail: orderEmail,
            orderLastName: orderLastName,
        };
    }
}

export default PlaceOrderStep;
