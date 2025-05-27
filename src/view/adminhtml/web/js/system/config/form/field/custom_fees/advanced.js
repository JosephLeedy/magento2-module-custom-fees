define(
    [
        'jquery',
        'Magento_Ui/js/modal/modal',
        'mage/translate',
        'mage/loader',
    ],
    function($, modal) {
        'use strict';

        const beforeModalClose = function () {
            $(this.element).html('');
        };

        const handelModalClose = function () {
            beforeModalClose.call(this);

            this.closeModal();
        };

        const handleModalSave = function () {
            beforeModalClose.call(this);

            this.closeModal();
        };

        const handleAdvancedButtonClick = function (config, modalContainer, event) {
            $('body').trigger('processStart');

            $.ajax(
                {
                    url: config.formUrl,
                    type: 'POST',
                    data: {
                        form_key: config.formKey,
                        row_id: config.rowId,
                        advanced_config: $(`#${config.inputId}`).val(),
                    }
                }
            ).done(
                response => {
                    $(modalContainer).html(response);
                    $(modalContainer).modal('openModal');
                }
            ).fail(
                error => {
                    console.error(error.responseText);
                }
            ).always(
                () => {
                    $('body').trigger('processStop');
                }
            );
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
