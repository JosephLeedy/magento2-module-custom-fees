import { Locator } from '@playwright/test';
import { inputValuesCustomFees, UIReferenceCustomFees } from '@config';

class CustomFees
{
    public async getAll(
        containerLocator: Locator,
        inEuro: boolean = false,
        exclude: string[] = [],
        useRefundAmount: boolean = false,
        withAmount: boolean = true,
    ): Promise<Locator[]> {
        const customFees = [];
        const currencySymbol = inEuro ? '€' : '$';
        const subtotal = parseFloat(
            /* The regex below is naïve in that it does not account for the currency format, but it's not necessary to
               do so right now. If it becomes necessary, we'll look into improving this logic. */
            (await containerLocator.getByText(`Subtotal ${currencySymbol}`).textContent() ?? '0').replace(/[^\d.]+/, '')
        );
        let feeName;

        for (feeName in inputValuesCustomFees.customFees) {
            let label = inputValuesCustomFees.customFees[feeName].title;
            let amount;

            if (!inEuro) {
                amount = !useRefundAmount
                    ? inputValuesCustomFees.customFees[feeName].base_amount
                    : inputValuesCustomFees.customFees[feeName].base_refund_amount;
            } else {
                amount = !useRefundAmount
                    ? inputValuesCustomFees.customFees[feeName].amount
                    : inputValuesCustomFees.customFees[feeName].refund_amount;
            }

            if (exclude.includes(feeName)) {
                continue;
            }

            if (
                inputValuesCustomFees.customFees[feeName].hasOwnProperty('advanced')
                && inputValuesCustomFees.customFees[feeName].advanced.hasOwnProperty('show_percentage')
                && inputValuesCustomFees.customFees[feeName].advanced.show_percentage
            ) {
                label += ` (${inputValuesCustomFees.customFees[feeName].amount}%)`;
            }

            if (inputValuesCustomFees.customFees[feeName].type.toLowerCase() === 'percent') {
                amount = ((amount * subtotal) / 100).toFixed(2);
            }

            if (withAmount) {
                label += ` ${currencySymbol}${amount}`;
            }

            customFees.push(containerLocator.getByText(label));
        }

        return customFees;
    }

    public async calculateTotal(
        containerLocator: Locator,
        inEuro: boolean = false,
        exclude: string[] = [],
        useRefundAmount: boolean = false,
        customFeeAmountLocator: string = '',
    ): Promise<number> {
        const withAmount: boolean = customFeeAmountLocator === '';
        const customFees: Locator[] = await this.getAll(containerLocator, inEuro, exclude, useRefundAmount, withAmount);
        const currencySymbol: string = inEuro ? '€' : '$';

        return await customFees.reduce(
            async (asyncTotal: Promise<number>, customFee: Locator): Promise<number> => {
                const total: number = await asyncTotal;
                const matches: RegExpMatchArray[] = [
                    ...(
                        await (customFeeAmountLocator === '' ? customFee : customFee.locator(customFeeAmountLocator))
                            .textContent() ?? '$0.00'
                    ).matchAll(new RegExp(`\\${currencySymbol}([\\d.]+)`, 'g')),
                ];
                let customFeeAmount: number = parseFloat(matches[0]?.[1] ?? '0.00');

                if (isNaN(customFeeAmount)) {
                    customFeeAmount = 0.00;
                }

                return total + customFeeAmount;
            },
            Promise.resolve<number>(0.00),
        );
    }
}

export default CustomFees;
