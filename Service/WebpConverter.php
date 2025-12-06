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
    public function convertAndSave($sourceFilePath, $quality = 89, OutputInterface $output = null): array|false|string
    {
        $localTempDir = $this->mediaDirectory->getAbsolutePath('webp_temp');
        if (!$this->mediaDirectory->isExist($localTempDir)) {
            $this->mediaDirectory->create($localTempDir);
        }

        $localTempPath = $localTempDir . '/' . basename($sourceFilePath);
        $localWebpPath = str_replace(['.jpg', '.png', '.jpeg'], '.webp', $localTempPath);
        $s3WebpPath = str_replace(['.jpg', '.png', '.jpeg'], '.webp', $sourceFilePath);

        $this->log($output, "  -> Downloading <comment>$sourceFilePath</comment> to temporary directory...");
        $this->mediaDirectory->copyFile($sourceFilePath, $localTempPath);

        try {
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
        } catch (Exception $e) {
            $success = false;
            $this->log($output, "  -> <error>Conversion failed: {$e->getMessage()}</error>", 'error');
            $this->logger->error('Błąd konwersji do WebP: ' . $e->getMessage());
        } finally {
            $this->log($output, "  -> Cleaning up temporary files...");
            if ($this->mediaDirectory->isExist($localTempPath)) {
                $this->mediaDirectory->delete($localTempPath);
            }
            if ($this->mediaDirectory->isExist($localWebpPath)) {
                $this->mediaDirectory->delete($localWebpPath);
            }
            $this->log($output, "  -> Cleanup complete.");
        }

        return $success ? $s3WebpPath : false;
    }

    private function log(OutputInterface $output = null, string $message, string $level = 'info'): void
    {
        if ($output && $output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
            $output->writeln($message);
        }
    }
}
