define([
    'jquery',
    'sortablejs',
    'Magento_Ui/js/modal/confirm'
], function ($, Sortable, confirmation) {
    'use strict';

    $.widget('mage.galleryTileComponent', {
        options: {
            saveOrderUrl: '',
            toggleStatusUrl: '',
            deleteUrl: '',
            formKey: ''
        },

        _create: function () {
            this.grid = this.element.find('#gallery-tiles-grid');
            this.searchInput = this.element.find('#gallery-search-input');
            this.noResultsMessage = this.element.find('#no-results-message');

            this._initSortable();
            this._bindEvents();
        },

        _bindEvents: function () {
            this.searchInput.on('keyup', this._filterTiles.bind(this));
            this.element.on('click', '.tile-actions-toggle', this._toggleActionsDropdown.bind(this));
            this.element.on('click', '.action-toggle-status', this._toggleStatus.bind(this));
            this.element.on('click', '.action-delete', this._deleteGallery.bind(this));
            $(document).on('click', this._closeAllDropdowns.bind(this));

            // Listen for the custom event from the tree
            $('body').on('gallery:filter:update', (e, filterValue) => {
                this.searchInput.val(filterValue).trigger('keyup');
            });
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
                    sortorder: index + 1
                });
            });

            this._ajaxRequest(this.options.saveOrderUrl, { order: newOrder });
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
                let name = tile.find('.tile-name').text().toLowerCase();

                // New, precise filtering logic
                let isVisible = (name === searchTerm) || name.startsWith(searchTerm + '/');

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
            let galleryId = tile.data('id');
            let currentStatus = tile.data('enabled');
            let newStatus = currentStatus ? 0 : 1;

            this._ajaxRequest(this.options.toggleStatusUrl, { id: galleryId, status: newStatus }, function() {
                tile.data('enabled', newStatus);
                tile.attr('data-enabled', newStatus);
                link.find('span').text(newStatus ? 'Disable' : 'Enable');
            });
        },

        _deleteGallery: function (event) {
            event.preventDefault();
            let tile = $(event.currentTarget).closest('.gallery-tile');
            let galleryId = tile.data('id');
            let galleryName = tile.find('.tile-name').text();

            confirmation({
                title: 'Delete Gallery',
                content: `Are you sure you want to delete the gallery "${galleryName}"?`,
                actions: {
                    confirm: () => {
                        this._ajaxRequest(this.options.deleteUrl, { id: galleryId }, function() {
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

    return $.mage.galleryTileComponent;
});
