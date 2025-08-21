import { expect, Locator } from '@playwright/test';
import { faker } from '@faker-js/faker';
import { outcomeMarker, slugs, UIReference, UIReferenceCustomFees } from '@config';
import CustomFees from '@utils/customFees.utils';
import HyvaUtils from '@utils/hyva.utils';
import BaseCheckoutPage from 'base-tests/poms/frontend/checkout.page';

class CheckoutPage extends BaseCheckoutPage
{
    private email: string = '';
    private lastName: string = '';
    private state: string = '';

    public async waitForMagewireRequests(): Promise<void>
    {
        const isHyva = await new HyvaUtils(this.page).isHyva();

        if (!isHyva) {
            return;
        }

        await super.waitForMagewireRequests();
    }

    public async navigateToCheckoutPage(): Promise<void>
    {
        await this.page.goto(slugs.checkout.checkoutSlug);
        await this.page.waitForLoadState('networkidle');
    }

    public async fillShippingAddress(): Promise<void>
    {
        let hasAddressForm: boolean;
        let emailField: Locator;

        // If we're not already on the checkout page, go there
        if (!this.page.url().includes(slugs.checkout.checkoutSlug)) {
            await this.page.goto(slugs.checkout.checkoutSlug);
            await this.page.waitForLoadState('networkidle');
        }

        hasAddressForm = await this.page
            .locator(UIReferenceCustomFees.checkoutPage.shippingAddressFormLocator)
            .isVisible();

        if (!hasAddressForm) {
            return;
        }

        emailField = this.page
            .getByRole('textbox', { name: UIReference.credentials.emailCheckoutFieldLabel })
            .first();
        this.email = faker.internet.email();
        this.lastName = faker.person.lastName();
        this.state = faker.location.state();

        // Fill required shipping address fields

        if (await emailField.isVisible()) {
            await emailField.fill(this.email);
        }

        await this.page.getByLabel(UIReference.personalInformation.firstNameLabel).fill(faker.person.firstName());
        await this.page.getByLabel(UIReference.personalInformation.lastNameLabel).fill(this.lastName);
        await this.page
            .getByLabel(UIReference.newAddress.streetAddressLabel)
            .first()
            .fill(faker.location.streetAddress());
        await this.page.getByLabel(UIReference.newAddress.zipCodeLabel).fill(faker.location.zipCode());
        await this.page.getByLabel(UIReference.newAddress.cityNameLabel).fill(faker.location.city());
        await this.page.getByLabel(UIReference.newAddress.phoneNumberLabel).fill(this.generatePhoneNumber());
        await this.page.getByLabel('Country').selectOption('US');
        await this.page
            .getByRole('combobox', { name: UIReferenceCustomFees.checkoutPage.addressRegionLabel })
            .selectOption(this.state);

        // Wait for any Magewire updates
        await this.waitForMagewireRequests();
        await this.page.waitForLoadState('networkidle');
    }

    public async selectShippingMethod(): Promise<void>
    {
        // If we're not already on the checkout page, go there
        if (!this.page.url().includes(slugs.checkout.checkoutSlug)) {
            await this.page.goto(slugs.checkout.checkoutSlug);
            await this.page.waitForLoadState('networkidle');
        }

        if (await this.shippingMethodOptionFixed.isChecked()) {
            return;
        }

        await this.shippingMethodOptionFixed.check();
        await this.waitForMagewireRequests();
        await this.page.waitForLoadState('networkidle');
    }

    public async proceedToReviewStep(): Promise<void>
    {
        let proceedToReviewStepButton: Locator;
        let paymentMethodStepTitle: Locator;
        let addressRegionSelect: Locator;

        proceedToReviewStepButton = this.page
            .locator(UIReferenceCustomFees.checkoutPage.proceedToReviewStepButtonLocator);

        if (!(await proceedToReviewStepButton.isVisible())) {
            return;
        }

        await proceedToReviewStepButton.click({ force: true });
        await this.page.waitForLoadState('networkidle');

        paymentMethodStepTitle = this.page
            .getByText(UIReferenceCustomFees.checkoutPage.paymentMethodSectionLabel, { exact: true });

        if ((await paymentMethodStepTitle.isVisible())) {
            return;
        }

        addressRegionSelect = this.page.getByRole(
            'combobox',
            { name: UIReferenceCustomFees.checkoutPage.addressRegionLabel }
        );

        /* Fixes a weird edge case where sometimes clicking the "Next" button causes the value for the region dropdown
           to be reset and therefore invalid. */
        if (
            this.state !== ''
            && (await addressRegionSelect.isVisible())
            && (
                (await addressRegionSelect.evaluate(element => element.getAttribute('aria-invalid') === 'true'))
                || (await addressRegionSelect.inputValue()) !== this.state
            )
        ) {
            await addressRegionSelect.selectOption(this.state);

            await this.waitForMagewireRequests();
            await this.proceedToReviewStep();
        }
    }

    public async placeMultiStepOrder(): Promise<OrderDetails>
    {
        const orderPlacedNotification = outcomeMarker.checkout.orderPlacedNotification;
        let orderNumber: string|null = null;

        await this.fillShippingAddress();
        await this.selectShippingMethod();
        await this.proceedToReviewStep();
        await this.selectPaymentMethod('check');
        await this.placeOrderButton.click();
        await this.waitForMagewireRequests();

        await expect.soft(this.page.getByText(orderPlacedNotification)).toBeVisible();

        orderNumber = await this.page
            .locator(UIReferenceCustomFees.checkoutSuccessPage.orderNumberLocator)
            .textContent();

        await expect(this.continueShoppingButton, `${outcomeMarker.checkout.orderPlacedNumberText} ${orderNumber}`)
            .toBeVisible();

        return {
            orderNumber: orderNumber,
            orderEmail: this.email,
            orderLastName: this.lastName
        };
    }

    public async assertHasCustomFees(inEuro: boolean = false, exclude: string[] = []): Promise<void>
    {
        const orderSummaryLocator = this.page.locator(UIReferenceCustomFees.checkoutPage.orderSummaryLocator);
        const customFees = await new CustomFees().getAll(orderSummaryLocator, inEuro, exclude);

        for (const customFee of customFees) {
            await expect(customFee).toBeVisible();
        }
    }

    public async assertDoesNotHaveCustomFees(inEuro: boolean = false, exclude: string[] = []): Promise<void>
    {
        const orderSummaryLocator = this.page.locator(UIReferenceCustomFees.checkoutPage.orderSummaryLocator);
        const customFees = await new CustomFees().getAll(orderSummaryLocator, inEuro, exclude);

        for (const customFee of customFees) {
            await expect(customFee).not.toBeVisible();
        }
    }

    private generatePhoneNumber(): string
    {
        let phoneNumber = faker.phone.number();

        // Remove the extension from the generated phone number if necessary as it fails Magento's validation logic
        if (phoneNumber.indexOf('x') !== -1) {
            phoneNumber = phoneNumber.replace(/\sx(\d{1,})/, '');
        }

        // Remove any invalid characters from the generated phone number
        phoneNumber = phoneNumber.replace(/[^\d\+\-\(\)\s]/g, '');

        return phoneNumber;
    }
}

export default CheckoutPage;
