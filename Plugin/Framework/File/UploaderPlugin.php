<?php
namespace GardenLawn\MediaGallery\Plugin\Framework\File;

use Magento\Framework\File\Uploader;

class UploaderPlugin
{
    /**
     * Add allowed extensions before setting them.
     *
     * @param Uploader $subject
     * @param array $extensions
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeSetAllowedExtensions(Uploader $subject, array $extensions = []): array
    {
        $newExtensions = ['webp', 'avif', 'svg'];
        return [array_merge($extensions, $newExtensions)];
    }

    /**
     * Add allowed mime types before checking them.
     *
     * @param Uploader $subject
     * @param array $mimeTypes
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeCheckMimeType(Uploader $subject, array $mimeTypes = []): array
    {
        $newMimeTypes = [
            'image/webp',
            'image/avif',
            'image/svg+xml'
        ];
        return [array_merge($mimeTypes, $newMimeTypes)];
    }
}
