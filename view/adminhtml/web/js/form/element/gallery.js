define([
    'Magento_Ui/js/form/element/file-uploader',
    'jquery',
    'ko', // Dodano Knockout.js
    'mage/template',
    'Magento_Ui/js/modal/modal'
], function (Element, $, ko, mageTemplate) {
    'use strict';

    return Element.extend({
        defaults: {
            template: 'GardenLawn_MediaGallery/form/element/gallery',
            imageTemplate: 'GardenLawn_MediaGallery/form/element/gallery/image',
            images: ko.observableArray([]) // Obserwowalna tablica do przechowywania obrazów
        },

        initialize: function () {
            this._super();
            // Inicjalizuj obserwowalną tablicę 'images' danymi z 'value'
            // 'value' pochodzi z AssetDataProvider i zawiera już pełne dane obrazów
            // Upewnij się, że 'enabled' jest obserwowalne
            var initialImages = this.value().map(function(image) {
                image.enabled = ko.observable(image.enabled !== undefined ? image.enabled : true);
                return image;
            });
            this.images(initialImages);
            return this;
        },

        initObservable: function () {
            this._super();
            this.observe('images'); // Obserwuj zmiany w tablicy 'images'

            // Subskrybuj zmiany w 'images', aby aktualizować 'value' komponentu
            // 'value' jest tym, co zostanie wysłane do serwera podczas zapisu formularza
            this.images.subscribe(function (newImages) {
                // Przed zapisem, przekonwertuj obserwowalne 'enabled' na zwykłą wartość boolean
                var dataToSave = newImages.map(function(image) {
                    var imgCopy = Object.assign({}, image);
                    if (ko.isObservable(imgCopy.enabled)) {
                        imgCopy.enabled = imgCopy.enabled();
                    }
                    return imgCopy;
                });
                this.value(dataToSave);
            }, this);

            return this;
        },

        /**
         * Obsługuje zdarzenie po pomyślnym przesłaniu pliku.
         * 'file' to odpowiedź serwera z kontrolera Upload.php
         */
        onFileUploaded: function (file) {
            // Tworzymy nowy obiekt obrazu w formacie oczekiwanym przez nasz komponent
            var newImage = {
                file: file.file, // Ścieżka pliku (np. tmp/nazwa.jpg)
                url: file.url,   // Pełny URL do obrazu (zwrócony przez kontroler)
                position: this.images().length, // Domyślna pozycja na końcu
                is_main: false, // Domyślnie nie jest głównym obrazem
                asset_id: file.asset_id, // ID zasobu w media_gallery_asset
                enabled: ko.observable(true) // Nowy obraz jest domyślnie włączony
            };
            this.images.push(newImage); // Dodaj nowy obraz do obserwowalnej tablicy
            // this._super(file); // Możemy pominąć wywołanie _super, ponieważ sami zarządzamy 'images'
        },

        /**
         * Usuwa obraz z galerii.
         */
        removeImage: function (image) {
            this.images.remove(image);
            this.reindexImages(); // Przeindeksuj obrazy po usunięciu
        },

        /**
         * Zwraca obserwowalną tablicę obrazów.
         */
        getImages: function () {
            return this.images();
        },

        /**
         * Zwraca szablon dla pojedynczego obrazu.
         */
        getImageTemplate: function () {
            return mageTemplate(this.imageTemplate);
        },

        /**
         * Obsługuje zdarzenie po zakończeniu sortowania (drag-and-drop).
         */
        afterSort: function (event, ui) {
            this.reindexImages(); // Przeindeksuj obrazy po sortowaniu
        },

        /**
         * Aktualizuje pozycje (sort_order) wszystkich obrazów w tablicy.
         */
        reindexImages: function () {
            var newImagesArray = [];
            // Iteruj po aktualnej kolejności obrazów w obserwowalnej tablicy
            ko.utils.arrayForEach(this.images(), function (image, index) {
                image.position = index; // Ustaw nową pozycję
                newImagesArray.push(image);
            });
            this.images(newImagesArray); // Zaktualizuj obserwowalną tablicę
        }
    });
});
