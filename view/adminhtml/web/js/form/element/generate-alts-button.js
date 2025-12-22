define([
    'Magento_Ui/js/form/components/button',
    'jquery',
    'uiRegistry',
    'Magento_Ui/js/modal/alert'
], function (Button, $, registry, alert) {
    'use strict';

    return Button.extend({
        defaults: {
            elementTmpl: 'ui/form/element/button',
            generateUrl: ''
        },

        action: function () {
            var self = this;

            // Get the gallery ID from the provider
            var galleryId = this.source.get('data.id');

            // Get the current value of the 'name' field
            // We assume the name field is in the same fieldset or we can access it via registry/source
            var galleryName = this.source.get('data.name');

            if (!galleryId) {
                alert({
                    content: 'Please save the gallery first.'
                });
                return;
            }

            if (!galleryName) {
                alert({
                    content: 'Please enter a gallery name.'
                });
                return;
            }

            $('body').trigger('processStart');

            $.ajax({
                url: this.generateUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    gallery_id: galleryId,
                    gallery_name: galleryName,
                    form_key: window.FORM_KEY
                },
                success: function (response) {
                    $('body').trigger('processStop');
                    if (response.success) {
                        alert({
                            content: response.message,
                            actions: {
                                always: function() {
                                    // Reload the page or grid to see changes
                                    // For now, let's trigger a grid reload if possible, or just notify user
                                    // Ideally we should reload the asset tiles component
                                    // location.reload();
                                }
                            }
                        });
                    } else {
                        alert({
                            content: response.message
                        });
                    }
                },
                error: function () {
                    $('body').trigger('processStop');
                    alert({
                        content: 'An error occurred.'
                    });
                }
            });
        }
    });
});
