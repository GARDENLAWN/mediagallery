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

        $localTempPath = $localTempDir . '/' . basename($sourceFilePath);
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

            if ($success && $createThumbnail) {
                $this->createThumbnail($localWebpPath, $s3WebpPath, $thumbnailWidth, $thumbnailHeight, $output, $filesToClean);
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

    private function createThumbnail($sourceLocalWebp, $s3WebpPath, $width, $height, $output, &$filesToClean): void
    {
        try {
            $this->log($output, "  -> Creating thumbnail...");
            $pathParts = explode('/', $s3WebpPath, 2);
            if (count($pathParts) < 2) {
                $this->log($output, "  -> <comment>Skipping thumbnail: image is in media root.</comment>");
                return;
            }

            $thumbnailS3Path = '.thumbs' . $pathParts[0] . '/' . $pathParts[1];
            $localThumbnailPath = $this->mediaDirectory->getAbsolutePath('webp_temp/' . basename($thumbnailS3Path));
            $filesToClean[] = $localThumbnailPath;

            $this->log($output, "  -> Opening local WebP <comment>$sourceLocalWebp</comment> for thumbnailing...");
            $thumbAdapter = $this->imageAdapterFactory->create();
            $thumbAdapter->open($sourceLocalWebp);

            $this->log($output, "  -> Resizing to <comment>{$width}x{$height}</comment>...");
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
