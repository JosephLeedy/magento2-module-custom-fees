import { expect, Locator } from '@playwright/test';
import { faker } from '@faker-js/faker';
import { slugs, UIReference, UIReferenceCustomFees } from '@config';
import CustomFees from '@utils/customFees.utils';
import HyvaUtils from '@utils/hyva.utils';
import BaseCheckoutPage from 'base-tests/poms/frontend/checkout.page';

class CheckoutPage extends BaseCheckoutPage
{
    private state: string = '';

    public async waitForMagewireRequests(): Promise<void>
    {
        const isHyva = await new HyvaUtils(this.page).isHyva();

        if (!isHyva) {
            return;
        }

        await super.waitForMagewireRequests();
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
        this.state = faker.location.state();

        // Fill required shipping address fields

        if (await emailField.isVisible()) {
            await emailField.fill(faker.internet.email());
        }

        await this.page.getByLabel(UIReference.personalInformation.firstNameLabel).fill(faker.person.firstName());
        await this.page.getByLabel(UIReference.personalInformation.lastNameLabel).fill(faker.person.lastName());
        await this.page
            .getByLabel(UIReference.newAddress.streetAddressLabel)
            .first()
            .fill(faker.location.streetAddress());
        await this.page.getByLabel(UIReference.newAddress.zipCodeLabel).fill(faker.location.zipCode());
        await this.page.getByLabel(UIReference.newAddress.cityNameLabel).fill(faker.location.city());
        await this.page.getByLabel(UIReference.newAddress.phoneNumberLabel).fill(faker.phone.number());
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

    public async proceedToReviewStep(expectVisibility: boolean = true): Promise<void>
    {
        let addressRegionSelect: Locator;
        await this.page.locator(UIReferenceCustomFees.checkoutPage.proceedToReviewStepButtonLocator).click();
        await this.page.waitForLoadState('networkidle');

        addressRegionSelect = this.page.getByRole(
            'combobox',
            { name: UIReferenceCustomFees.checkoutPage.addressRegionLabel }
        );

        /* Fixes a weird edge case where sometimes clicking the "Next" button causes the value for the region dropdown
           to be reset and therefore invalid. */
        if (
            this.state !== ''
            && (await addressRegionSelect.isVisible())
            && (await addressRegionSelect.evaluate(element => element.getAttribute('aria-invalid') === 'true'))
        ) {
            await addressRegionSelect.selectOption(this.state);

            await this.waitForMagewireRequests();
            await this.proceedToReviewStep(false);
        }

        if (!expectVisibility) {
            return;
        }

        await expect(
            this.page.getByText(UIReferenceCustomFees.checkoutPage.paymentMethodSectionLabel, { exact: true })
        ).toBeVisible();
    }

    public async hasCustomFees(inEuro: boolean = false, exclude: string[] = []): Promise<void>
    {
        const orderSummaryLocator = this.page.locator(UIReferenceCustomFees.checkoutPage.orderSummaryLocator);
        const customFees = await new CustomFees().getAll(orderSummaryLocator, inEuro, exclude);

        for (const customFee of customFees) {
            await expect(customFee).toBeVisible();
        }
    }

    public async doesNotHaveCustomFees(inEuro: boolean = false, exclude: string[] = []): Promise<void>
    {
        const orderSummaryLocator = this.page.locator(UIReferenceCustomFees.checkoutPage.orderSummaryLocator);
        const customFees = await new CustomFees().getAll(orderSummaryLocator, inEuro, exclude);

        for (const customFee of customFees) {
            await expect(customFee).not.toBeVisible();
        }
    }
}

export default CheckoutPage;
