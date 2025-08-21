import { Page } from '@playwright/test';
import { step } from '@utils/stepDecorator.utils';
import CurrencySwitcher from '@utils/currencySwitcher.utils';

class ChangeCurrencyToEuroStep
{
    public constructor(private readonly page: Page) {}

    @step('Change currency to Euro', { box: true })
    /**
     * Switches the store's currency to Euro.
     *
     * @returns {Promise<void>} - A promise that resolves when the currency is switched.
     * @throws {Error} - Throws an error if the currency cannot be switched.
     */
    public async changeCurrency(): Promise<void>
    {
        await new CurrencySwitcher(this.page).switchCurrencyToEuro();
        await this.page.waitForLoadState('networkidle');
    }
}

export default ChangeCurrencyToEuroStep;
