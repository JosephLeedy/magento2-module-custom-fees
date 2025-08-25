import { ElementHandle } from '@playwright/test';
import { inputValuesCustomFees, UIReference, UIReferenceCustomFees } from '@config';
import MagentoAdminPage from '@poms/adminhtml/magentoAdmin.page';

class CustomFeesConfigurationPage extends MagentoAdminPage
{
    public async configureCustomFees(): Promise<void>
    {
        await this.navigateToCustomFeesConfiguration();

        await this.page
            .locator(UIReferenceCustomFees.customFeesConfigurationPage.customOrderFeesSystemCheckbox)
            .uncheck();

        for (const feeName in inputValuesCustomFees.customFees) {
            await this.configureCustomFee(
                inputValuesCustomFees.customFees[feeName].code,
                inputValuesCustomFees.customFees[feeName].title,
                inputValuesCustomFees.customFees[feeName].type,
                inputValuesCustomFees.customFees[feeName].status,
                inputValuesCustomFees.customFees[feeName].base_amount,
                inputValuesCustomFees.customFees[feeName].advanced,
            );
        }

        await this.page.getByRole('button', { name: UIReference.configurationPage.saveConfigButtonLabel }).click();
    }

    private async navigateToCustomFeesConfiguration(): Promise<void>
    {
        await this.page.waitForLoadState('networkidle');
        await this.page.getByRole('link', { name: UIReference.magentoAdminPage.navigation.storesButtonLabel }).click();
        await this.page.getByRole(
            'link',
            { name: UIReference.magentoAdminPage.subNavigation.configurationButtonLabel }
        ).click();
        await this.page.getByRole(
            'tab',
            { name: UIReferenceCustomFees.customFeesConfigurationPage.salesTabLabel }
        ).click();
        await this.page.getByRole(
            'link',
            { name: UIReferenceCustomFees.customFeesConfigurationPage.salesTabLabel, exact: true }
        ).click();

        if (
            !await this.page.locator(UIReferenceCustomFees.customFeesConfigurationPage.customOrderFeesField).isVisible()
        ) {
            await this.page.getByRole(
                'link',
                { name: UIReferenceCustomFees.customFeesConfigurationPage.customOrderFeesSectionLabel }
            ).click();
        }
    }

    private async configureCustomFee(
        code: string,
        title: string,
        type: string,
        status: string,
        amount: string,
        advanced: object
    ): Promise<void> {
        await this.page.getByRole(
            'button',
            { name: UIReferenceCustomFees.customFeesConfigurationPage.customOrderFeesFields.addCustomFeeButtonLabel }
        ).click();

        if (status === 'disabled') {
            await this.page.locator(
                UIReferenceCustomFees.customFeesConfigurationPage.customOrderFeesFields.feeStatusField
            ).uncheck();
        }

        await this.page.locator(
            UIReferenceCustomFees.customFeesConfigurationPage.customOrderFeesFields.feeCodeField
        ).fill(code);
        await this.page.locator(
            UIReferenceCustomFees.customFeesConfigurationPage.customOrderFeesFields.feeTitleField
        ).fill(title);
        await this.page.locator(
            UIReferenceCustomFees.customFeesConfigurationPage.customOrderFeesFields.feeTypeField
        ).selectOption({ label: type });
        await this.page.locator(
            UIReferenceCustomFees.customFeesConfigurationPage.customOrderFeesFields.feeAmountField
        ).fill(amount);

        // The "advanced settings" field is a hidden input, so we need to use a different approach to fill its value

        const advancedSettingsFieldHandle: ElementHandle<HTMLInputElement> | null = await this.page.$(
            UIReferenceCustomFees.customFeesConfigurationPage.customOrderFeesFields.advancedSettingsField
        );

        if (advancedSettingsFieldHandle === null) {
            return;
        }

        await this.page.evaluate(
            ([advancedSettingsField, advanced]): void => {
                // @ts-ignore
                advancedSettingsField.value = JSON.stringify(advanced);
            },
            [advancedSettingsFieldHandle, advanced]
        );
        await advancedSettingsFieldHandle.dispose();
    }
}

export default CustomFeesConfigurationPage;
