<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Plugin\Framework\Image\Adapter;

use Magento\Framework\Image\Adapter\Gd2;
use Psr\Log\LoggerInterface;
use ReflectionProperty;
use ReflectionException;

class Gd2Plugin
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Teach the GD2 adapter how to handle WebP before opening a file.
     *
     * @param Gd2 $subject
     * @param string|null $filename
     * @return array
     */
    public function beforeOpen(Gd2 $subject, string $filename = null): array
    {
        if ($filename && str_ends_with(strtolower($filename), '.webp')) {
            $this->addWebpSupport($subject);
        }

        return [$filename];
    }

    /**
     * Teach the GD2 adapter how to handle WebP before saving a file.
     *
     * @param Gd2 $subject
     * @param string|null $destination
     * @param string|null $newName
     * @return array
     */
    public function beforeSave(Gd2 $subject, string $destination = null, string $newName = null): array
    {
        if ($destination && str_ends_with(strtolower($destination), '.webp')) {
            $this->addWebpSupport($subject);

            // Set file type to WebP for saving
            try {
                $fileTypeProperty = new ReflectionProperty(Gd2::class, '_fileType');
                $fileTypeProperty->setAccessible(true);
                $fileTypeProperty->setValue($subject, IMAGETYPE_WEBP);
            } catch (ReflectionException $e) {
                $this->logger->error('Gd2Plugin ReflectionException in beforeSave: ' . $e->getMessage());
            }
        }

        return [$destination, $newName];
    }

    /**
     * After saving a JPG/PNG, also save a WebP version.
     *
     * @param Gd2 $subject
     * @param mixed $result
     * @param string|null $destination
     * @return mixed
     */
    public function afterSave(Gd2 $subject, $result, string $destination = null)
    {
        // Only trigger for JPG and PNG files
        if ($destination && preg_match('/\.(jpg|jpeg|png)$/i', $destination)) {
            // Skip if destination is a remote URL
            if (preg_match('/^https?:\/\//', $destination)) {
                $this->logger->warning('Gd2Plugin: Skipping WebP generation for remote URL: ' . $destination);
                return $result;
            }

            $webpDestination = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $destination);

            try {
                // Get the internal image resource using reflection
                $imageHandlerProperty = new ReflectionProperty(Gd2::class, '_imageHandler');
                $imageHandlerProperty->setAccessible(true);
                $imageResource = $imageHandlerProperty->getValue($subject);

                if ($imageResource) {
                    // Save the image as WebP
                    imagewebp($imageResource, $webpDestination);
                    $this->logger->info('Gd2Plugin successfully created WebP image.', ['destination' => $webpDestination]);
                }
            } catch (ReflectionException $e) {
                $this->logger->error('Gd2Plugin ReflectionException in afterSave: ' . $e->getMessage());
            } catch (\Exception $e) {
                $this->logger->error('Gd2Plugin error creating WebP in afterSave: ' . $e->getMessage());
            }
        }

        return $result;
    }

    /**
     * Use reflection to add WebP support to the GD2 adapter's internal callbacks.
     *
     * @param Gd2 $subject
     */
    private function addWebpSupport(Gd2 $subject): void
    {
        try {
            // Add WebP to the list of supported formats for opening and saving
            $callbacksProperty = new ReflectionProperty(Gd2::class, '_callbacks');
            $callbacksProperty->setAccessible(true);
            $callbacks = $callbacksProperty->getValue($subject);

            // Ensure IMAGETYPE_WEBP constant exists (PHP < 7.1 fallback, though Magento 2.4 requires newer PHP)
            if (!defined('IMAGETYPE_WEBP')) {
                define('IMAGETYPE_WEBP', 18);
            }

            if (!isset($callbacks[IMAGETYPE_WEBP])) {
                $callbacks[IMAGETYPE_WEBP] = ['output' => 'imagewebp', 'create' => 'imagecreatefromwebp'];
                $callbacksProperty->setValue($subject, $callbacks);
            }

            // Access the internal image resource to apply fixes
            $imageHandlerProperty = new ReflectionProperty(Gd2::class, '_imageHandler');
            $imageHandlerProperty->setAccessible(true);
            $imageResource = $imageHandlerProperty->getValue($subject);

            if (($imageResource && is_resource($imageResource)) || ($imageResource instanceof \GdImage)) {
                // FIX for palette-based images
                if (function_exists('imageistruecolor') && !imageistruecolor($imageResource)) {
                    imagepalettetotruecolor($imageResource);
                }
                // FIX for transparency issues (black background)
                if (function_exists('imagealphablending')) {
                    imagealphablending($imageResource, false);
                }
                if (function_exists('imagesavealpha')) {
                    imagesavealpha($imageResource, true);
                }
            }
        } catch (ReflectionException $e) {
            // Silently ignore if reflection fails.
        }
    }
}
