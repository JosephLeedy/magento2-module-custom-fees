import { expect, Page } from '@playwright/test';
import { UIReferenceCustomFees } from '@config';

class CurrencySwitcher
{
    public constructor(private readonly page: Page) {}

    public async switchCurrencyToEuro(): Promise<void>
    {
        const currencySwitcherContainer = this.page.locator(
            UIReferenceCustomFees.common.currencySwitcher.containerLocator,
        );
        const euroCurrencySwitcher = currencySwitcherContainer
            .getByText(UIReferenceCustomFees.common.currencySwitcher.euroLabel);

        await currencySwitcherContainer
            .getByText(UIReferenceCustomFees.common.currencySwitcher.usDollarLabel)
            .click();

        if (await euroCurrencySwitcher.isVisible()) {
            await euroCurrencySwitcher.click();
        } else {
            // Handle the edge case where the Euro currency switcher does not become visible immediately
            await euroCurrencySwitcher.evaluate((element: HTMLElement) => element.click());
        }

        await expect(currencySwitcherContainer.getByRole('button'))
            .toHaveText(UIReferenceCustomFees.common.currencySwitcher.euroLabel);
    }
}

export default CurrencySwitcher;
