define(
    [
        'Magento_Checkout/js/view/summary/abstract-total',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/totals',
        'Magento_Catalog/js/price-utils'
    ],
    function (Component, quote, totals) {
        'use strict';

        return Component.extend(
            {
                defaults: {
                    template: 'JosephLeedy_CustomFees/checkout/summary/custom_fees'
                },
                totals: totals.totals,

                /**
                 * @returns {Array}
                 */
                getCustomFees: function () {
                    const self = this;
                    const customFeeCodes = window.checkoutConfig.customFees?.codes ?? [];
                    const customFees = [];

                    customFeeCodes.forEach(
                        function (customFeeCode) {
                            const customFee = totals.getSegment(customFeeCode);

                            if (customFee === null || !customFee.hasOwnProperty('value') || customFee.value === 0) {
                                return;
                            }

                            customFee.formattedPrice = self.getFormattedPrice(customFee.value);
                            customFee.formattedPriceWithTax = self.getFormattedPrice(
                                customFee.extension_attributes.custom_fee_tax_details?.value_with_tax || customFee.value
                            );

                            customFees.push(customFee);
                        }
                    );

                    return customFees;
                },

                /**
                 * @returns {Boolean}
                 */
                isDisplayed: function () {
                    return this.isFullMode() && this.getCustomFees().length > 0;
                },

                /**
                 * @returns {Boolean}
                 */
                isDisplayedWithoutTax: function () {
                    return window.checkoutConfig.customFees.cartDisplayType === 'excluding_tax';
                },

                /**
                 * @returns {Boolean}
                 */
                isDisplayedWithTax: function () {
                    return window.checkoutConfig.customFees.cartDisplayType === 'including_tax';
                },

                /**
                 * @returns {Boolean}
                 */
                isDisplayedBoth: function () {
                    return window.checkoutConfig.customFees.cartDisplayType === 'both';
                }
            }
        );
    }
);
