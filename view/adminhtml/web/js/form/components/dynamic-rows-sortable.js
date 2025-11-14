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
         * Stores the original order before a drag-and-drop operation.
         * Used to revert changes if AJAX update fails.
         */
        _originalOrder: [],

        /**
         * Initializes observable properties.
         *
         * @returns {Object} Chainable.
         */
        initObservable: function () {
            this._super();

            // Store original order when component initializes or data changes
            this.observe('records');
            this.on('records', this._storeOriginalOrder.bind(this));

            return this;
        },

        /**
         * Stores the current order of records.
         * @private
         */
        _storeOriginalOrder: function () {
            this._originalOrder = this.records().map(function (record) {
                return {
                    asset_id: record.getChild('asset_id').value(),
                    sortorder: record.getChild('sortorder').value()
                };
            });
        },

        /**
         * Reverts the order of records to the last successfully saved state.
         * @private
         */
        _revertOrder: function () {
            var self = this;
            // Re-apply original sort orders to records
            this.records().forEach(function (record) {
                var originalData = _.find(self._originalOrder, {asset_id: record.getChild('asset_id').value()});
                if (originalData) {
                    record.getChild('sortorder').value(originalData.sortorder);
                }
            });
            // Trigger re-sorting of UI elements based on updated sortorder values
            this.sort(this.sorting);
            alert({
                content: $.mage.__('Sort order could not be saved. Display order has been reverted.')
            });
        },

        /**
         * Callback on sortOrder change.
         * This function is triggered when the sortOrder of any record changes (e.g., after drag and drop).
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
                    if (response.success) {
                        self._storeOriginalOrder(); // Update original order on successful save
                    } else {
                        self._revertOrder(); // Revert on failure
                        alert({
                            content: response.message || $.mage.__('Failed to update sort order.')
                        });
                    }
                },
                error: function (xhr, status, error) {
                    self._revertOrder(); // Revert on AJAX error
                    alert({
                        content: $.mage.__('An error occurred while updating sort order: %1').replace('%1', error)
                    });
                }
            });
        }
    });
});
