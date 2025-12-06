<?php

namespace GardenLawn\MediaGallery\Service;

use Exception;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem;
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
        Filesystem      $fileSystem,
        AdapterFactory  $imageAdapterFactory,
        LoggerInterface $logger
    )
    {
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
    ): array|false|string
    {
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

            $imageAdapter->keepAspectRatio(true);
            $imageAdapter->keepTransparency(true);

            $this->log($output, "  -> Saving as WebP locally to <comment>$localWebpPath</comment>...");
            $imageAdapter->save($localWebpPath);

            $this->log($output, "  -> Uploading converted file to S3 at <comment>$s3WebpPath</comment>...");
            $this->mediaDirectory->copyFile($localWebpPath, $s3WebpPath);
            $success = true;

            if ($createThumbnail) {
                // FIX: Create thumbnail from the original source file, not the intermediate WebP.
                $this->createThumbnail($localTempPath, $s3WebpPath, $thumbnailWidth, $thumbnailHeight, $quality, $output, $filesToClean);
            }

        } catch (Exception $e) {
            $this->log($output, "  -> <error>Conversion failed: {$e->getMessage()}</error>");
            $this->logger->error('Błąd konwersji do WebP: ' . $e->getMessage());
        } finally {
            $this->log($output, "  -> Cleaning up temporary files...");
            foreach ($filesToClean as $file) {
                if ($this->mediaDirectory->isExist($file)) {
                    $this->mediaDirectory->delete($file);
                }
            }
            $this->log($output, "  -> Cleanup complete.");
        }

        return $success ? $s3WebpPath : false;
    }

    public function getThumbnailPath(string $webpPath): ?string
    {
        $pathParts = explode('/', $webpPath, 2);
        if (count($pathParts) < 2) {
            return null; // Image is in media root, no thumb
        }
        return '.thumbs' . $pathParts[0] . '/' . $pathParts[1];
    }

    private function createThumbnail($sourceLocalOriginal, $s3WebpPath, $width, $height, $quality, $output, &$filesToClean): void
    {
        try {
            $this->log($output, "  -> Creating thumbnail from original source...");
            $thumbnailS3Path = $this->getThumbnailPath($s3WebpPath);
            if (!$thumbnailS3Path) {
                $this->log($output, "  -> <comment>Skipping thumbnail: image is in media root.</comment>");
                return;
            }

            $uniqueThumbName = str_replace('/', '_', $thumbnailS3Path);
            $localThumbnailPath = $this->mediaDirectory->getAbsolutePath('webp_temp/' . $uniqueThumbName);
            $filesToClean[] = $localThumbnailPath;

            $this->log($output, "  -> Opening original local file <comment>$sourceLocalOriginal</comment> for thumbnailing...");
            $thumbAdapter = $this->imageAdapterFactory->create();
            $thumbAdapter->open($sourceLocalOriginal);

            if (method_exists($thumbAdapter, 'setQuality')) {
                $this->log($output, "  -> Setting thumbnail quality to <comment>$quality</comment>...");
                $thumbAdapter->setQuality($quality);
            }

            $thumbAdapter->keepAspectRatio(true);
            $thumbAdapter->keepTransparency(true);

            $this->log($output, "  -> Resizing to fit within <comment>{$width}x{$height}</comment>...");
            $thumbAdapter->resize($width, $height);

            $this->log($output, "  -> Saving thumbnail locally to <comment>$localThumbnailPath</comment>...");
            $thumbAdapter->save($localThumbnailPath);

            $this->log($output, "  -> Uploading thumbnail to S3 at <comment>$thumbnailS3Path</comment>...");
            $this->mediaDirectory->copyFile($localThumbnailPath, $thumbnailS3Path);
            $this->log($output, "  -> <info>Thumbnail created successfully.</info>");

        } catch (Exception $e) {
            $this->log($output, "  -> <error>Thumbnail creation failed: {$e->getMessage()}</error>");
            $this->logger->error("Thumbnail creation failed for {$s3WebpPath}: " . $e->getMessage());
        }
    }

    private function log(OutputInterface $output = null, string $message): void
    {
        if ($output && $output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
            $output->writeln($message);
        }
    }
}
