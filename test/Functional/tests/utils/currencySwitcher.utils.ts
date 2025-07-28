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

        await currencySwitcherContainer
            .getByText(UIReferenceCustomFees.common.currencySwitcher.usDollarLabel)
            .click();
        await currencySwitcherContainer
            .getByText(UIReferenceCustomFees.common.currencySwitcher.euroLabel)
            .click();
        await expect(currencySwitcherContainer.getByRole('button'))
            .toHaveText(UIReferenceCustomFees.common.currencySwitcher.euroLabel);
    }
}

export default CurrencySwitcher;
