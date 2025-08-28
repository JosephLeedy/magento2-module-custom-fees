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
        await euroCurrencySwitcher.waitFor();
        await euroCurrencySwitcher.click();
        await expect(currencySwitcherContainer.getByRole('button'))
            .toHaveText(UIReferenceCustomFees.common.currencySwitcher.euroLabel);
    }
}

export default CurrencySwitcher;
