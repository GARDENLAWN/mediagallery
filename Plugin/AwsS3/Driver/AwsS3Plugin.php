<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Plugin\AwsS3\Driver;

use League\Flysystem\Config;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemException as FlysystemFilesystemException;
use League\Flysystem\UnableToRetrieveMetadata;
use Magento\AwsS3\Driver\AwsS3 as CoreAwsS3;
use Magento\Framework\Filesystem\DriverInterface;
use Psr\Log\LoggerInterface;
use ReflectionException;

/**
 * Rozszerzenie oryginalnego sterownika AwsS3 w celu dodania nagłówka CacheControl
 * oraz automatycznego generowania i wysyłania wersji WebP obrazków.
 */
class AwsS3Plugin extends CoreAwsS3
{
    private const string CACHE_CONTROL_VALUE = 'max-age=31536000, public';

    /**
     * Zwraca bazową konfigurację S3 z dodanym nagłówkiem CacheControl, jeśli jest to obrazek.
     *
     * @param string|null $path Opcjonalna ścieżka do sprawdzenia rozszerzenia.
     * @param bool $isImageContent Czy zawartość jest obrazkiem (wymagane dla filePutContents).
     * @return array
     */
    private function getExtendedConfig(?string $path = null, bool $isImageContent = false): array
    {
        $reflectionClass = new \ReflectionClass(CoreAwsS3::class);
        $config = $reflectionClass->getConstant('CONFIG');

        if ($isImageContent || ($path && preg_match('/\.(jpg|jpeg|png|gif|webp|avif|svg)$/i', $path))) {
            $config['CacheControl'] = self::CACHE_CONTROL_VALUE;
        }

        return $config;
    }

    /**
     * Pobiera prywatną właściwość z klasy bazowej za pomocą refleksji.
     *
     * @param string $propertyName
     * @return mixed
     * @throws ReflectionException
     */
    private function getPrivateProperty(string $propertyName): mixed
    {
        $reflectionClass = new \ReflectionClass(CoreAwsS3::class);
        $property = $reflectionClass->getProperty($propertyName);
        $property->setAccessible(true);
        return $property->getValue($this);
    }

    /**
     * Wywołuje prywatną metodę z klasy bazowej za pomocą refleksji.
     *
     * @param string $methodName
     * @param array $args
     * @return mixed
     * @throws ReflectionException
     */
    private function callPrivateMethod(string $methodName, array $args): mixed
    {
        $reflectionClass = new \ReflectionClass(CoreAwsS3::class);
        $method = $reflectionClass->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($this, $args);
    }

    /**
     * @inheritDoc
     * Nadpisanie metody filePutContents w celu dodania CacheControl oraz generowania wersji WebP.
     */
    public function filePutContents($path, $content, $mode = null): bool|int
    {
        $path = $this->callPrivateMethod('normalizeRelativePath', [$path, true]);

        // Optimization: Check extension first to avoid expensive getimagesizefromstring on non-image files
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg'];
        $isImageExtension = in_array($extension, $imageExtensions);

        $isImageContent = false;
        $imageSize = false;

        if ($isImageExtension) {
            // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
            $isImageContent = (false !== ($imageSize = @getimagesizefromstring($content)));
        }

        $config = $this->getExtendedConfig(null, $isImageContent);

        if ($isImageContent) {
            $config['Metadata'] = ['image-width' => $imageSize[0], 'image-height' => $imageSize[1]];
        }

        try {
            /** @var FilesystemAdapter $adapter */
            $adapter = $this->getPrivateProperty('adapter');
            /** @var LoggerInterface $logger */
            $logger = $this->getPrivateProperty('logger');

            // 1. Wyślij oryginalny plik
            $adapter->write($path, $content, new Config($config));

            // 2. Jeśli to obrazek JPG/PNG, stwórz i wyślij wersję WebP
            // Zmiana: Nie generuj WebP dla obrazów katalogu (media/catalog/...), chyba że to cache.
            $isCatalog = str_contains($path, 'catalog/');
            $isCache = str_contains($path, '/cache/');

            if ($isImageContent && preg_match('/\.(jpg|jpeg|png)$/i', $path) && (!$isCatalog || $isCache)) {
                $imageResource = @imagecreatefromstring($content);
                if ($imageResource) {
                    // Fix for "Palette image not supported by webp"
                    if (!imageistruecolor($imageResource)) {
                        imagepalettetotruecolor($imageResource);
                    }

                    if ($imageSize[2] === IMAGETYPE_PNG) {
                        imagealphablending($imageResource, false);
                        imagesavealpha($imageResource, true);
                    }
                    ob_start();
                    imagewebp($imageResource);
                    $webpContent = ob_get_clean();
                    imagedestroy($imageResource);

                    if ($webpContent) {
                        $webpPath = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $path);
                        $adapter->write($webpPath, $webpContent, new Config($config));
                        $logger->info('AwsS3Plugin: Successfully created and uploaded WebP version.', ['path' => $webpPath]);
                    }
                } else {
                    $logger->warning('AwsS3Plugin: Could not create image resource from string.', ['path' => $path]);
                }
            }
            return true;
        } catch (FlysystemFilesystemException|UnableToRetrieveMetadata|\Exception $e) {
            /** @var LoggerInterface $logger */
            $logger = $this->getPrivateProperty('logger');
            $logger->error('AwsS3Plugin filePutContents error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * @inheritDoc
     * Nadpisanie metody copy w celu dodania CacheControl oraz kopiowania wersji WebP.
     */
    public function copy($source, $destination, ?DriverInterface $targetDriver = null): bool
    {
        $sourcePath = $this->callPrivateMethod('normalizeRelativePath', [$source, true]);
        $destinationPath = $this->callPrivateMethod('normalizeRelativePath', [$destination, true]);
        $config = $this->getExtendedConfig($sourcePath);

        try {
            /** @var FilesystemAdapter $adapter */
            $adapter = $this->getPrivateProperty('adapter');
            $adapter->copy($sourcePath, $destinationPath, new Config($config));

            if (preg_match('/\.(jpg|jpeg|png)$/i', $sourcePath)) {
                $sourceWebpPath = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $sourcePath);
                $destinationWebpPath = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $destinationPath);
                if ($adapter->fileExists($sourceWebpPath)) {
                    $adapter->copy($sourceWebpPath, $destinationWebpPath, new Config($config));
                }
            }
        } catch (FlysystemFilesystemException $e) {
            /** @var LoggerInterface $logger */
            $logger = $this->getPrivateProperty('logger');
            $logger->error($e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * @inheritDoc
     * Nadpisanie metody rename w celu dodania CacheControl oraz przenoszenia wersji WebP.
     */
    public function rename($oldPath, $newPath, ?DriverInterface $targetDriver = null): bool
    {
        if ($oldPath === $newPath) {
            return true;
        }
        $oldPathRelative = $this->callPrivateMethod('normalizeRelativePath', [$oldPath, true]);
        $newPathRelative = $this->callPrivateMethod('normalizeRelativePath', [$newPath, true]);
        $config = $this->getExtendedConfig($oldPathRelative);

        try {
            /** @var FilesystemAdapter $adapter */
            $adapter = $this->getPrivateProperty('adapter');
            $adapter->move($oldPathRelative, $newPathRelative, new Config($config));

            if (preg_match('/\.(jpg|jpeg|png)$/i', $oldPathRelative)) {
                $oldWebpPath = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $oldPathRelative);
                $newWebpPath = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $newPathRelative);
                if ($adapter->fileExists($oldWebpPath)) {
                    $adapter->move($oldWebpPath, $newWebpPath, new Config($config));
                }
            }
        } catch (FlysystemFilesystemException $e) {
            /** @var LoggerInterface $logger */
            $logger = $this->getPrivateProperty('logger');
            $logger->error($e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * @inheritDoc
     * Nadpisanie metody fileClose w celu dodania CacheControl przy zapisie strumienia.
     */
    public function fileClose($resource): bool
    {
        if (!is_resource($resource)) {
            return false;
        }
        $meta = stream_get_meta_data($resource);
        $streams = $this->getPrivateProperty('streams');

        foreach ($streams as $path => $stream) {
            if (stream_get_meta_data($stream)['uri'] === $meta['uri']) {
                if (isset($meta['seekable']) && $meta['seekable']) {
                    $this->fileSeek($resource, 0);
                }
                $config = $this->getExtendedConfig($path);
                /** @var FilesystemAdapter $adapter */
                $adapter = $this->getPrivateProperty('adapter');
                $adapter->writeStream($path, $resource, new Config($config));

                $reflectionClass = new \ReflectionClass(CoreAwsS3::class);
                $streamsProperty = $reflectionClass->getProperty('streams');
                $streamsProperty->setAccessible(true);
                unset($streams[$path]);
                $streamsProperty->setValue($this, $streams);

                // phpcs:ignore Magento2.Functions.DiscouragedFunction.DiscouragedWithAlternative
                return fclose($stream);
            }
        }
        return false;
    }

    /**
     * @inheritDoc
     * Nadpisanie metody fileOpen w celu poprawnego odczytu plików WebP.
     */
    public function fileOpen($path, $mode)
    {
        // Jeśli plik to WebP, upewnij się, że jest poprawnie otwierany
        // Oryginalna metoda fileOpen w AwsS3.php obsługuje odczyt (r) poprzez pobranie zawartości do tmpfile
        // Jeśli mamy problem z odczytem WebP, może to wynikać z tego, że adapter S3 nie widzi pliku lub ma problem z metadanymi

        return parent::fileOpen($path, $mode);
    }
}
