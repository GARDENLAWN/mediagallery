<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Plugin\Cms\Model\Wysiwyg\Images;

use Magento\Cms\Model\Wysiwyg\Images\Storage;
use Magento\Framework\Filesystem;
use Psr\Log\LoggerInterface;
use GardenLawn\MediaGallery\Service\WebpConverter; // Inject WebpConverter

class StorageResizeFilePlugin
{
    private Filesystem\Directory\WriteInterface $mediaDirectory;
    private LoggerInterface $logger;
    private WebpConverter $webpConverter; // New dependency

    public function __construct(
        Filesystem $filesystem,
        LoggerInterface $logger,
        WebpConverter $webpConverter // Inject WebpConverter
    ) {
        $this->mediaDirectory = $filesystem->getDirectoryWrite(\Magento\Framework\App\Filesystem\DirectoryList::MEDIA);
        $this->logger = $logger;
        $this->webpConverter = $webpConverter; // Assign new dependency
    }

    /**
     * Around plugin for Magento\Cms\Model\Wysiwyg\Images\Storage::resizeFile
     *
     * Prevents resizing of WebP and SVG files, instead copies them directly.
     *
     * @param Storage $subject
     * @param \Closure $proceed
     * @param string $source Absolute path to the uploaded file in media directory
     * @param bool $keepRatio Keep aspect ratio or not
     * @return bool|string
     */
    public function aroundResizeFile(Storage $subject, \Closure $proceed, $source, $keepRatio = true)
    {
        try {
            $sourceExtension = strtolower(pathinfo($source, PATHINFO_EXTENSION));
            $copyOnlyExtensions = ['webp', 'svg'];

            if (in_array($sourceExtension, $copyOnlyExtensions, true)) {
                $this->logger->info('[StorageResizeFilePlugin] Detected ' . $sourceExtension . ' file. Copying directly instead of resizing.');

                // Get the storage root (e.g., /var/www/html/magento/pub/media)
                $storageRoot = $subject->getCmsWysiwygImages()->getStorageRoot();
                // Get the path of the uploaded file relative to the storage root (e.g., wysiwyg/banners/dealers/baner4_5.webp)
                $uploadedFileRelativePath = substr($source, strlen($storageRoot));
                $uploadedFileRelativePath = ltrim($uploadedFileRelativePath, '/'); // Ensure no leading slash

                // Get the target thumbnail path relative to the media directory (e.g., .thumbs/wysiwyg/banners/dealers/baner4_5.webp)
                $thumbnailRelativePath = $this->webpConverter->getThumbnailPath($uploadedFileRelativePath);

                if (!$thumbnailRelativePath) {
                    $this->logger->warning('[StorageResizeFilePlugin] Could not determine thumbnail path for: ' . $source);
                    return $proceed($source, $keepRatio); // Fallback to original method
                }

                // For SVG, the thumbnail should retain .svg extension. For WebP, it retains .webp.
                // The getThumbnailPath already handles the .thumbs prefix.
                // We need to ensure the extension is correct for the thumbnail.
                // The getThumbnailPath from WebpConverter already returns the correct extension for the thumbnail based on the source.
                // So, if source is 'image.svg', thumbnailRelativePath will be '.thumbs/image.svg'.
                // If source is 'image.webp', thumbnailRelativePath will be '.thumbs/image.webp'.

                // Ensure the thumbnail directory exists (relative path)
                $targetThumbnailDirRelative = dirname($thumbnailRelativePath);
                if (!$this->mediaDirectory->isExist($targetThumbnailDirRelative)) {
                    $this->mediaDirectory->create($targetThumbnailDirRelative);
                }

                // Copy the original file to the thumbnail location
                // Both paths are relative to the media directory.
                $this->mediaDirectory->copyFile(
                    $uploadedFileRelativePath, // Source is the uploaded file in media directory (relative)
                    $thumbnailRelativePath // Target is the thumbnail path in media directory (relative)
                );

                // Return the absolute path of the created thumbnail
                return $storageRoot . '/' . $thumbnailRelativePath;
            }
        } catch (\Exception $e) {
            $this->logger->error(
                '[StorageResizeFilePlugin] Error during custom resizeFile logic: ' . $e->getMessage(),
                ['exception' => $e]
            );
            // Fallback to original method if our custom logic fails
            return $proceed($source, $keepRatio);
        }

        // For other image types (jpg, png), proceed with original resizeFile method
        return $proceed($source, $keepRatio);
    }

    /**
     * Helper to get relative path to storage root, similar to Storage::_getRelativePathToRoot
     * This method is no longer needed as we are constructing paths differently.
     * Keeping it for now, but it will be removed.
     *
     * @param Storage $subject
     * @param string $path
     * @return string
     */
    private function getRelativePathToRoot(Storage $subject, string $path): string
    {
        // Accessing protected method via reflection
        try {
            $reflection = new \ReflectionClass($subject);
            $method = $reflection->getMethod('_getRelativePathToRoot');
            $method->setAccessible(true);
            return $method->invoke($subject, $path);
        } catch (\ReflectionException $e) {
            $this->logger->error('Reflection failed for _getRelativePathToRoot: ' . $e->getMessage());
            // Fallback if reflection fails (should not happen in production)
            $storageRoot = $subject->getCmsWysiwygImages()->getStorageRoot();
            return substr($path, strlen($storageRoot));
        }
    }
}
