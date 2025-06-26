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

        const saveRuleData = function (ruleData) {
            let rule;
            let conditionsJson;

            rule = convertFlatDataToRecursiveData(ruleData);

            if (!rule.hasOwnProperty('conditions') || rule.conditions.length === 0) {
                return;
            }

            conditionsJson = JSON.stringify(
                rule['conditions'][1],
                (key, value) => key === 'conditions' ? value.filter(Boolean) : value
            );

            return `"conditions":${conditionsJson}`;
        };

        const saveFormData = function () {
            const formData = $(this.element).find('input, select').serializeArray();
            const $advancedInput = $('#' + $(this.element).data('input-id'));
            let nestedFormData;
            let advancedData = [];

            if (formData.length === 0 || $advancedInput.length === 0) {
                return;
            }

            nestedFormData = convertFormDataToNestedObject(formData);

            if (nestedFormData.hasOwnProperty('rule')) {
                advancedData.push(saveRuleData(nestedFormData.rule));

                delete nestedFormData.rule;
            }

            for (const key in nestedFormData) {
                advancedData.push(`"${key}":${JSON.stringify(nestedFormData[key])}`);
            }

            $advancedInput.val(`{${advancedData.join(',')}}`);
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
            saveFormData.call(this);
            beforeModalClose.call(this);

            this.closeModal();
        };

        const loadModalContent = function (config, modalContainer) {
            const $advancedConfigInput = $(`#${config.inputId}`);
            const feeType = $advancedConfigInput.parents('tr:first').find('select[name$="[type]"]').val() ?? 'fixed';

            $('body').trigger('processStart');

            $.ajax(
                {
                    url: config.formUrl,
                    type: 'POST',
                    data: {
                        form_key: config.formKey,
                        row_id: config.rowId,
                        fee_type: feeType,
                        advanced_config: $advancedConfigInput.val(),
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
            modalCloseBtnHandler: handelModalClose,
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
            const $feeNameInput = $(`#${config.rowId}_title`);
            let feeName = '';

            if ($feeNameInput.length > 0) {
                feeName = $feeNameInput.val();
            }

            if (feeName.trim() !== '') {
                modalOptions.title = $.mage.__('Advanced Settings for %1').replace('%1', feeName);
            } else {
                modalOptions.title = $.mage.__('Advanced Settings');
            }

            modal(modalOptions, element);

            loadModalContent(config, element);
        };
    }
);
