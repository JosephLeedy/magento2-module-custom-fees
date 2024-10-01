define(
    [
        'JosephLeedy_CustomFees/js/view/checkout/summary/custom_fees'
    ],
    function (Component) {
        'use strict';

        return Component.extend(
            {
                /**
                 * @override
                 */
                isFullMode: function () {
                    return true;
                }
            }
        );
    }
);
