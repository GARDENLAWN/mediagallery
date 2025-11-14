var config = {
    // Użyj mapowania RequireJS przez 'map' i wskaż docelowe pliki .html.
    // Magento preferuje taki sposób aliasowania szablonów KO (zwłaszcza z ukośnikami w nazwie).
    map: {
        '*': {
            'GardenLawn_MediaGallery/form/element/gallery': 'GardenLawn_MediaGallery/web/template/form/element/gallery',
            'GardenLawn_MediaGallery/form/element/gallery/image': 'GardenLawn_MediaGallery/web/template/form/element/gallery/image'
        }
    }
};
