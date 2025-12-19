import { expect } from '@playwright/test';
import { inputValues, outcomeMarkerCustomFees, UIReference, UIReferenceCustomFees } from '@config';
import { requireEnv } from '@utils/env.utils';
import MagentoAdminPage from '@poms/adminhtml/magentoAdmin.page';

class CartPriceRulesPage extends MagentoAdminPage
{
    public async addCustomFeesCartPriceRule(): Promise<void>
    {
        if (!process.env.MAGENTO_COUPON_CODE_CUSTOM_FEES) {
            throw new Error('"MAGENTO_COUPON_CODE_CUSTOM_FEES" is not defined in your .env file.');
        }

        await this.page
            .getByRole('link', { name: UIReference.magentoAdminPage.navigation.marketingButtonLabel })
            .click();
        await this.page.waitForLoadState('networkidle');

        await expect(
            this.page.getByRole('link', { name: UIReference.magentoAdminPage.subNavigation.cartPriceRulesButtonLabel }),
        ).toBeVisible();

        await this.page
            .getByRole('link', { name: UIReference.magentoAdminPage.subNavigation.cartPriceRulesButtonLabel })
            .click();
        await this.page.waitForLoadState('networkidle');
        await this.page
            .getByRole('button', { name: UIReference.cartPriceRulesPage.addCartPriceRuleButtonLabel })
            .click();
        await this.page
            .getByLabel(UIReference.cartPriceRulesPage.ruleNameFieldLabel)
            .fill(inputValues.coupon.couponCodeRuleName);

        const websiteSelector = this.page.getByLabel(UIReference.cartPriceRulesPage.websitesSelectLabel);

        await websiteSelector.evaluate((select: HTMLSelectElement): void => {
            for (const option of select.options) {
                option.selected = true;
            }

            select.dispatchEvent(new Event('change'));
        });

        const customerGroupsSelector = this.page
            .getByLabel(UIReference.cartPriceRulesPage.customerGroupsSelectLabel, { exact: true });

        await customerGroupsSelector.evaluate((select: HTMLSelectElement): void => {
            for (const option of select.options) {
                option.selected = true;
            }

            select.dispatchEvent(new Event('change'));
        });
        await this.page
            .locator(UIReference.cartPriceRulesPage.couponTypeSelectField)
            .selectOption({ label: inputValues.coupon.couponType });
        await this.page
            .getByLabel(UIReference.cartPriceRulesPage.couponCodeFieldLabel)
            .fill(requireEnv('MAGENTO_COUPON_CODE_CUSTOM_FEES'));
        await this.page.getByText(UIReference.cartPriceRulesPage.actionsSubtitleLabel, { exact: true }).click();
        await this.page.getByLabel(UIReference.cartPriceRulesPage.discountAmountFieldLabel).fill('10');
        await this.page
            .getByLabel(UIReferenceCustomFees.adminCartPriceRulesPage.applyToCustomFeeAmountLabel)
            /* We need to "click" the DOM element for the checkbox because it is covered by a CSS toggle element that
               intercepts Playwright's normal `click()` action, causing an error regarding the checkbox being outside
               of the browser's viewport. */
            .evaluate((customFeeElement: HTMLElement): void => customFeeElement.click());
        await this.page.getByRole('button', { name: 'Save', exact: true }).click();

        await expect(
            this.page.getByText(outcomeMarkerCustomFees.adminCartPriceRulesPage.cartPriceRuleSavedNotificationMessage),
        ).toBeVisible();
    }
}

export default CartPriceRulesPage;
