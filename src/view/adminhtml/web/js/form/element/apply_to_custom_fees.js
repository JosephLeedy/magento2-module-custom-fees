define(
    [
        'Magento_Ui/js/form/element/single-checkbox-toggle-notice',
    ],
    function (Checkbox) {
        'use strict';

        return Checkbox.extend(
            {
                defaults: {
                    imports: {
                        chooseNoticeByAction: '${ $.parentName }.simple_action:value'
                    },
                    noticePerSimpleAction: {},
                    selectedSimpleAction: '',
                },

                /**
                 * Set notice according to simple action value.
                 *
                 * @param {String} action
                 */
                chooseNoticeByAction: function (action) {
                    this.selectedSimpleAction = action;
                    this.chooseNotice();
                },

                /**
                 * @inheritdoc
                 */
                chooseNotice: function () {
                    const isChecked = Boolean(this.checked());

                    if (!isChecked || !this.noticePerSimpleAction.hasOwnProperty(this.selectedSimpleAction)) {
                        this._super();

                        return;
                    }

                    this.notice = this.noticePerSimpleAction[this.selectedSimpleAction];
                }
            }
        );
    }
);