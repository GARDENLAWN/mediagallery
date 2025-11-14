define([
    'Magento_Ui/js/form/element/file-uploader',
    'jquery',
    'ko',
    'mage/template',
    'Magento_Ui/js/modal/modal'
], function (Element, $, ko, mageTemplate) {
    'use strict';

    return Element.extend({
        defaults: {
            template: 'GardenLawn_MediaGallery/form/element/gallery',
            imageTemplate: 'GardenLawn_MediaGallery/form/element/gallery/image',
            images: ko.observableArray([])
        },

        initialize: function () {
            this._super();
            this.images(this.value());
            return this;
        },

        initObservable: function () {
            this._super();
            this.observe('images');
            return this;
        },

        onFileUploaded: function (file) {
            this._super(file);
            this.images.push(file);
        },

        removeImage: function (image) {
            this.images.remove(image);
        },

        getImages: function () {
            return this.images();
        },

        getImageTemplate: function () {
            return mageTemplate(this.imageTemplate);
        },

        afterSort: function (event, ui) {
            var newImagesArray = [];
            ui.item.parent().children().each(function (index) {
                var image = ko.dataFor(this);
                image.position = index;
                newImagesArray.push(image);
            });
            this.images(newImagesArray);
        }
    });
});
