define(
    [
        'jquery',
        'Magento_Ui/js/modal/modal',
        'mage/translate',
    ],
    function($, modal) {
        'use strict';

        const handelModalClose = function () {
            this.closeModal();
        };

        const handleModalSave = function () {
            this.closeModal();
        };

        const handleAdvancedButtonClick = function (config, modalContainer, event) {
            $(modalContainer).modal('openModal');
        };

        const bindEvents = function (config, element) {
            $(`#${config.buttonId}`).on('click', handleAdvancedButtonClick.bind(null, config, element));
        };

        const modalOptions = {
            type: 'slide',
            responsive: true,
            innerScroll: true,
            title: $.mage.__('Advanced Settings'),
            buttons: [
                {
                    text: $.mage.__('Close'),
                    class: 'modal-close',
                    click: handelModalClose,
                },
                {
                    text: $.mage.__('Save'),
                    class: 'primary',
                    click: handleModalSave,
                },
            ],
        };

        return function (config, element) {
            bindEvents(config, element);

            modal(modalOptions, element);
        };
    }
);
