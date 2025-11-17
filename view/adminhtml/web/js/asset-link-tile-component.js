define([
    'jquery',
    'sortablejs',
    'Magento_Ui/js/modal/confirm'
], function ($, Sortable, confirmation) {
    'use strict';

    $.widget('mage.assetLinkTileComponent', {
        options: {
            saveOrderUrl: '',
            toggleStatusUrl: '',
            deleteUrl: '',
            formKey: '',
            currentGalleryId: null
        },

        _create: function () {
            this.grid = this.element.find('#asset-link-tiles-grid');
            this.searchInput = this.element.find('#asset-link-search-input');
            this.noResultsMessage = this.element.find('#no-results-message');

            this._initSortable();
            this._bindEvents();
        },

        _bindEvents: function () {
            this.searchInput.on('keyup', this._filterTiles.bind(this));
            this.element.on('click', '.tile-actions-toggle', this._toggleActionsDropdown.bind(this));
            this.element.on('click', '.action-toggle-status', this._toggleStatus.bind(this));
            this.element.on('click', '.action-delete', this._deleteAssetLink.bind(this));
            $(document).on('click', this._closeAllDropdowns.bind(this));
        },

        _initSortable: function () {
            new Sortable(this.grid[0], {
                animation: 150,
                handle: '.drag-handle',
                onEnd: this._saveOrder.bind(this)
            });
        },

        _saveOrder: function () {
            let newOrder = [];
            this.grid.children('.gallery-tile').each(function (index) {
                newOrder.push({
                    id: $(this).data('id'),
                    sort_order: index + 1 // Use sort_order as per DB schema
                });
            });

            this._ajaxRequest(this.options.saveOrderUrl, { order: newOrder, gallery_id: this.options.currentGalleryId });
        },

        _filterTiles: function () {
            let searchTerm = this.searchInput.val().toLowerCase();
            let visibleCount = 0;

            if (searchTerm === '') {
                this.grid.children('.gallery-tile').show();
                visibleCount = this.grid.children('.gallery-tile').length;
                this.noResultsMessage.toggle(visibleCount === 0);
                return;
            }

            this.grid.children('.gallery-tile').each(function () {
                let tile = $(this);
                let title = tile.find('.tile-name').text().toLowerCase();
                let assetIdText = tile.find('.tile-asset-id').text().toLowerCase(); // e.g., "asset id: 123"
                let isVisible = title.includes(searchTerm) || assetIdText.includes(searchTerm);

                if (isVisible) {
                    tile.show();
                    visibleCount++;
                } else {
                    tile.hide();
                }
            });

            this.noResultsMessage.toggle(visibleCount === 0);
        },

        _toggleActionsDropdown: function (event) {
            event.stopPropagation();
            let dropdown = $(event.currentTarget).siblings('.tile-actions-dropdown');
            $('.tile-actions-dropdown').not(dropdown).removeClass('active');
            dropdown.toggleClass('active');
        },

        _closeAllDropdowns: function (event) {
            if (!$(event.target).closest('.tile-actions-toggle, .tile-actions-dropdown').length) {
                $('.tile-actions-dropdown').removeClass('active');
            }
        },

        _toggleStatus: function (event) {
            event.preventDefault();
            let link = $(event.currentTarget);
            let tile = link.closest('.gallery-tile');
            let assetLinkId = tile.data('id');
            let currentStatus = tile.data('enabled');
            let newStatus = currentStatus ? 0 : 1;

            this._ajaxRequest(this.options.toggleStatusUrl, { id: assetLinkId, status: newStatus, gallery_id: this.options.currentGalleryId }, function() {
                tile.data('enabled', newStatus);
                tile.attr('data-enabled', newStatus);
                link.find('span').text(newStatus ? 'Disable' : 'Enable');
            });
        },

        _deleteAssetLink: function (event) {
            event.preventDefault();
            let tile = $(event.currentTarget).closest('.gallery-tile');
            let assetLinkId = tile.data('id');
            let assetLinkTitle = tile.find('.tile-name').text();

            confirmation({
                title: 'Delete Asset Link',
                content: `Are you sure you want to delete the asset link "${assetLinkTitle}" (ID: ${assetLinkId})?`,
                actions: {
                    confirm: () => {
                        this._ajaxRequest(this.options.deleteUrl, { id: assetLinkId, gallery_id: this.options.currentGalleryId }, function() {
                            tile.remove();
                        });
                    }
                }
            });
        },

        _ajaxRequest: function (url, data, successCallback) {
            data.form_key = this.options.formKey;

            $('body').trigger('processStart');

            $.ajax({
                url: url,
                type: 'POST',
                data: data,
                dataType: 'json',
                success: (response) => {
                    if (response.error) {
                        alert(response.message);
                    } else {
                        if (successCallback) {
                            successCallback(response);
                        }
                    }
                },
                error: () => {
                    alert('An unknown error occurred.');
                }
            }).always(() => {
                $('body').trigger('processStop');
            });
        }
    });

    return $.mage.assetLinkTileComponent;
});
