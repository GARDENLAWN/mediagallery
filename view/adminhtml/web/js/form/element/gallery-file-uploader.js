define([
    'Magento_Ui/js/form/element/file-uploader',
    'uiRegistry'
], function (fileUploader, uiRegistry) {
    'use strict';

    return fileUploader.extend({
        initialize: function () {
            this._super();
            this.initGalleryIdListener();
            return this;
        },

        initGalleryIdListener: function () {
            var self = this;
            var provider = this.provider; // The provider for the form data

            uiRegistry.get(provider, function (dataProvider) {
                // Subscribe to changes in the 'id' field of the data provider
                dataProvider.on('data.id', function (galleryId) {
                    if (galleryId) {
                        // Update the uploader's parameters with the new galleryId
                        self.uploaderConfig.params.gallery_id = galleryId;
                    } else {
                        // Handle case where galleryId might be null (e.g., for new galleries)
                        delete self.uploaderConfig.params.gallery_id;
                    }
                });
            });
        },

        /**
         * Overrides the default upload method to ensure params are updated before upload.
         * This adds an extra layer of safety to ensure the latest galleryId is used.
         */
        upload: function (file) {
            var dataProvider = uiRegistry.get(this.provider);
            if (dataProvider && dataProvider.data.id) {
                this.uploaderConfig.params.gallery_id = dataProvider.data.id;
            } else {
                delete this.uploaderConfig.params.gallery_id;
            }
            this._super(file);
        }
    });
});
