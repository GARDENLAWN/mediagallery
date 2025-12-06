<?php
namespace GardenLawn\MediaGallery\Service;

use Exception;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem;
use Magento\Framework\Image\Adapter\AdapterInterface;
use Magento\Framework\Image\AdapterFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;

class WebpConverter
{
    protected Filesystem $fileSystem;
    protected Filesystem\Directory\WriteInterface $mediaDirectory;
    protected AdapterFactory $imageAdapterFactory;
    protected LoggerInterface $logger;

    /**
     * @throws FileSystemException
     */
    public function __construct(
        Filesystem $fileSystem,
        AdapterFactory $imageAdapterFactory,
        LoggerInterface $logger
    ) {
        $this->fileSystem = $fileSystem;
        $this->mediaDirectory = $fileSystem->getDirectoryWrite(DirectoryList::MEDIA);
        $this->imageAdapterFactory = $imageAdapterFactory;
        $this->logger = $logger;
    }

    /**
     * @throws FileSystemException
     */
    public function convertAndSave(
        $sourceFilePath,
        $quality = 89,
        OutputInterface $output = null,
        bool $createThumbnail = false,
        int $thumbnailWidth = 240,
        int $thumbnailHeight = 240
    ): array|false|string {
        $localTempDir = $this->mediaDirectory->getAbsolutePath('webp_temp');
        if (!$this->mediaDirectory->isExist($localTempDir)) {
            $this->mediaDirectory->create($localTempDir);
        }

        $uniqueTempName = str_replace('/', '_', $sourceFilePath);
        $localTempPath = $localTempDir . '/' . $uniqueTempName;
        $localWebpPath = str_replace(['.jpg', '.png', '.jpeg'], '.webp', $localTempPath);
        $s3WebpPath = str_replace(['.jpg', '.png', '.jpeg'], '.webp', $sourceFilePath);

        $filesToClean = [$localTempPath, $localWebpPath];
        $success = false;

        try {
            $this->log($output, "  -> Downloading <comment>$sourceFilePath</comment> to temporary directory...");
            $this->mediaDirectory->copyFile($sourceFilePath, $localTempPath);

            $this->log($output, "  -> Opening image with adapter...");
            $imageAdapter = $this->imageAdapterFactory->create();
            $imageAdapter->open($localTempPath);

            if (method_exists($imageAdapter, 'setQuality')) {
                $this->log($output, "  -> Setting quality to <comment>$quality</comment>...");
                $imageAdapter->setQuality($quality);
            }

            $this->log($output, "  -> Saving as WebP locally to <comment>$localWebpPath</comment>...");
            $imageAdapter->save($localWebpPath);

            $this->log($output, "  -> Uploading converted file to S3 at <comment>$s3WebpPath</comment>...");
            $this->mediaDirectory->copyFile($localWebpPath, $s3WebpPath);
            $success = true;

            if ($createThumbnail) {
                $this->createThumbnail($localTempPath, $sourceFilePath, $thumbnailWidth, $thumbnailHeight, $quality, $output, $filesToClean);
            }

        } catch (Exception $e) {
            $this->log($output, "  -> <error>Conversion failed: {$e->getMessage()}</error>");
            $this->logger->error('Błąd konwersji do WebP: ' . $e->getMessage());
        } finally {
            $this->cleanupTempFiles($filesToClean, $output);
        }

        return $success ? $s3WebpPath : false;
    }

    /**
     * @throws FileSystemException
     */
    public function createWebpThumbnail(
        $sourceFilePath,
        $quality = 89,
        OutputInterface $output = null,
        int $thumbnailWidth = 240,
        int $thumbnailHeight = 240
    ): bool {
        $localTempDir = $this->mediaDirectory->getAbsolutePath('webp_temp');
        if (!$this->mediaDirectory->isExist($localTempDir)) {
            $this->mediaDirectory->create($localTempDir);
        }

        $uniqueTempName = str_replace('/', '_', $sourceFilePath);
        $localTempPath = $localTempDir . '/' . $uniqueTempName;

        $filesToClean = [$localTempPath];
        $success = false;

        try {
            $this->log($output, "  -> Downloading original <comment>$sourceFilePath</comment> for thumbnailing...");
            $this->mediaDirectory->copyFile($sourceFilePath, $localTempPath);
            $success = $this->createThumbnail($localTempPath, $sourceFilePath, $thumbnailWidth, $thumbnailHeight, $quality, $output, $filesToClean);
        } catch (Exception $e) {
            $this->log($output, "  -> <error>Thumbnail-only creation failed: {$e->getMessage()}</error>");
            $this->logger->error("Thumbnail-only creation failed for {$sourceFilePath}: " . $e->getMessage());
        } finally {
            $this->cleanupTempFiles($filesToClean, $output);
        }

        return $success;
    }

    public function getThumbnailPath(string $sourcePath): ?string
    {
        $pathParts = explode('/', $sourcePath, 2);
        if (count($pathParts) < 2) {
            return null;
        }
        return '.thumbs' . $pathParts[0] . '/' . $pathParts[1];
    }

    private function createThumbnail($sourceLocalOriginal, $sourceRelativePath, $width, $height, $quality, $output, &$filesToClean): bool
    {
        try {
            $this->log($output, "  -> Creating thumbnail from original source...");

            $sourceExtension = strtolower(pathinfo($sourceLocalOriginal, PATHINFO_EXTENSION));
            $copyOnlyExtensions = ['webp', 'svg'];

            $thumbnailS3Path = $this->getThumbnailPath($sourceRelativePath);
            if (!$thumbnailS3Path) {
                $this->log($output, "  -> <comment>Skipping thumbnail: image is in media root.</comment>");
                return false;
            }

            // For JPG/PNG sources, the thumbnail should be WebP. For SVG, it stays SVG.
            if (!in_array($sourceExtension, ['svg'])) {
                $thumbnailS3Path = str_replace(['.jpg', '.png', '.jpeg'], '.webp', $thumbnailS3Path);
            }

            if (in_array($sourceExtension, $copyOnlyExtensions)) {
                $this->log($output, "  -> Source is '{$sourceExtension}', copying directly without resizing...");
                // FIX: Use copyFile for S3 compatibility
                $this->mediaDirectory->copyFile($sourceLocalOriginal, $thumbnailS3Path);
                $this->log($output, "  -> <info>Thumbnail copied successfully to {$thumbnailS3Path}.</info>");
            } else {
                $uniqueThumbName = str_replace('/', '_', $thumbnailS3Path);
                $localThumbnailPath = $this->mediaDirectory->getAbsolutePath('webp_temp/' . $uniqueThumbName);
                $filesToClean[] = $localThumbnailPath;

                $this->log($output, "  -> Opening original local file <comment>$sourceLocalOriginal</comment> for thumbnailing...");
                /** @var AdapterInterface $thumbAdapter */
                $thumbAdapter = $this->imageAdapterFactory->create();
                $thumbAdapter->open($sourceLocalOriginal);

                if (method_exists($thumbAdapter, 'setQuality')) {
                    $this->log($output, "  -> Setting thumbnail quality to <comment>$quality</comment>...");
                    $thumbAdapter->setQuality($quality);
                }

                $this->log($output, "  -> Setting keep aspect ratio to true...");
                $thumbAdapter->keepAspectRatio(true);

                $this->log($output, "  -> Resizing to fit within <comment>{$width}x{$height}</comment>...");
                $thumbAdapter->resize($width, $height);

                $this->log($output, "  -> Saving thumbnail locally to <comment>$localThumbnailPath</comment>...");
                $thumbAdapter->save($localThumbnailPath);

                $this->log($output, "  -> Uploading thumbnail to S3 at <comment>$thumbnailS3Path</comment>...");
                // FIX: Use copyFile for S3 compatibility
                $this->mediaDirectory->copyFile($localThumbnailPath, $thumbnailS3Path);
                $this->log($output, "  -> <info>Thumbnail created successfully.</info>");
            }
            return true;
        } catch (Exception $e) {
            $this->log($output, "  -> <error>Thumbnail creation failed: {$e->getMessage()}</error>");
            $this->logger->error("Thumbnail creation failed for {$sourceRelativePath}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * @throws FileSystemException
     */
    private function cleanupTempFiles(array $filesToClean, ?OutputInterface $output): void
    {
        $this->log($output, "  -> Cleaning up temporary files...");
        foreach ($filesToClean as $file) {
            if ($this->mediaDirectory->isExist($file)) {
                $this->mediaDirectory->delete($file);
            }
        }
        $this->log($output, "  -> Cleanup complete.");
    }

    private function log(OutputInterface $output = null, string $message): void
    {
        if ($output && $output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
            $output->writeln($message);
        }
    }
}
