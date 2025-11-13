define([
    'Magento_Ui/js/dynamic-rows/dynamic-rows',
    'jquery',
    'uiRegistry',
    'underscore',
    'mage/url',
    'Magento_Ui/js/modal/alert'
], function (dynamicRows, $, registry, _, urlBuilder, alert) {
    'use strict';

    return dynamicRows.extend({
        defaults: {
            updateSortOrderUrl: '', // Will be passed from XML
            listens: {
                'record:sortOrder': 'onSortOrderChange'
            }
        },

        /**
         * Initializes observable properties.
         *
         * @returns {Object} Chainable.
         */
        initObservable: function () {
            this._super();
            return this;
        },

        /**
         * Callback on sortOrder change.
         * This function is triggered when the sortOrder of any record changes (e.g., after drag and drop).
         *
         * @param {Number} newSortOrder - The new sort order value.
         * @param {Object} record - The record object that was sorted.
         */
        onSortOrderChange: function () {
            var self = this,
                records = this.records(),
                assetsToUpdate = [];

            // Collect all assets with their current sortOrder
            records.forEach(function (record) {
                var assetId = record.getChild('asset_id').value();
                var sortOrder = record.getChild('sortorder').value();

                if (assetId && sortOrder !== undefined) {
                    assetsToUpdate.push({
                        asset_id: assetId,
                        sortorder: sortOrder
                    });
                }
            });

            if (!assetsToUpdate.length) {
                return;
            }

            // Send AJAX request to update sort order
            $.ajax({
                url: urlBuilder.build(this.updateSortOrderUrl),
                type: 'POST',
                data: {
                    assets: assetsToUpdate,
                    form_key: registry.get('toggle_form_key').form_key // Get form_key from global registry
                },
                dataType: 'json',
                showLoader: true,
                success: function (response) {
                    if (!response.success) {
                        alert({
                            content: response.message || $.mage.__('Failed to update sort order.')
                        });
                    }
                },
                error: function (xhr, status, error) {
                    alert({
                        content: $.mage.__('An error occurred while updating sort order: %1').replace('%1', error)
                    });
                }
            });
        }
    });
});
