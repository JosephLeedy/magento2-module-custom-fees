define(
    [
        'jquery',
        'Magento_Ui/js/modal/modal',
        'Magento_Ui/js/modal/confirm',
        'mage/translate',
        'mage/loader',
    ],
    function($, modal, confirm) {
        'use strict';

        const convertFormDataToNestedObject = function (formData) {
            const nestedObject = {};

            formData.forEach(({name, value}) => {
                // Parse the path: e.g., "rule[conditions][1][type]" -> ['rule', 'conditions', '1', 'type']
                const path = [];
                const regex = /([^\[\]]+)|\[(.*?)]/g;
                let match;
                let current = nestedObject;

                while ((match = regex.exec(name))) {
                    if (match[1]) {
                        path.push(match[1]);
                    } else if (match[2]) {
                        path.push(match[2]);
                    }
                }

                // Walk/create the nested path
                for (let i = 0; i < path.length - 1; i++) {
                    if (!current[path[i]]) {
                        current[path[i]] = {};
                    }

                    current = current[path[i]];
                }

                current[path[path.length - 1]] = value;
            });

            return nestedObject;
        };

        const convertFlatDataToRecursiveData = function (flatData) {
            const recursiveData = {};

            for (const [key, value] of Object.entries(flatData)) {
                for (const [id, data] of Object.entries(value)) {
                    const path = id.split('--');
                    let node = recursiveData;

                    for (let i = 0, length = path.length; i < length; i++) {
                        if (!node[key]) {
                            node[key] = [];
                        }

                        if (!node[key][path[i]]) {
                            node[key][path[i]] = {};
                        }

                        node = node[key][path[i]];
                    }

                    for (const [childKey, childValue] of Object.entries(data)) {
                        if (childKey === 'new_child') {
                            continue;
                        }

                        node[childKey] = childValue;
                    }
                }
            }

            return recursiveData;
        };

        const saveRuleData = function () {
            const formData = $(this.element).find('input, select').serializeArray();
            const $advancedInput = $('#' + $(this.element).data('input-id'));
            let nestedFormData;
            let rule;
            let conditionsJson;

            if (formData.length === 0 || $advancedInput.length === 0) {
                return;
            }

            nestedFormData = convertFormDataToNestedObject(formData);

            if (!nestedFormData.hasOwnProperty('rule')) {
                return;
            }

            rule = convertFlatDataToRecursiveData(nestedFormData.rule);

            if (!rule.hasOwnProperty('conditions') || rule.conditions.length === 0) {
                return;
            }

            conditionsJson = JSON.stringify(
                rule['conditions'][1],
                (key, value) => key === 'conditions' ? value.filter(Boolean) : value
            );

            $advancedInput.val(`{"conditions":${conditionsJson}}`);
        };

        const beforeModalClose = function () {
            $(this.element).html('');
        };

        const handelModalClose = function () {
            const modalInstance = this;

            confirm(
                {
                    title: $.mage.__('Are you sure you want to close Advanced Settings?'),
                    content: $.mage.__('Changes made to the advanced settings will not be saved.'),
                    actions: {
                        confirm: function () {
                            beforeModalClose.call(modalInstance);

                            modalInstance.closeModal();
                        },
                        cancel: function () {
                            return false;
                        }
                    }
                }
            );
        };

        const handleModalSave = function () {
            saveRuleData.call(this);
            beforeModalClose.call(this);

            this.closeModal();
        };

        const loadModalContent = function (config, modalContainer) {
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
                    text: $.mage.__('Done'),
                    class: 'primary',
                    click: handleModalSave,
                },
            ],
        };

        return function (config, element) {
            modal(modalOptions, element);

            loadModalContent(config, element);
        };
    }
);
