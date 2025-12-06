<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Console\Command;

use Exception;
use GardenLawn\MediaGallery\Service\WebpConverter;
use GardenLawn\MediaGallery\Model\S3Adapter;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Psr\Log\LoggerInterface;
use Magento\Framework\Console\Cli;

class ConvertImagesToWebp extends Command
{
    private const string COMMAND_NAME = 'gardenlawn:gallery:convert-to-webp';
    private const string COMMAND_DESCRIPTION = 'Converts images in S3 media gallery to WebP format, creates thumbnails, and cleans up legacy files.';
    private const string OPTION_FORCE = 'force';

    private State $appState;
    private S3Adapter $s3Adapter;
    private WebpConverter $webpConverter;
    private LoggerInterface $logger;

    public function __construct(
        State $appState,
        S3Adapter $s3Adapter,
        WebpConverter $webpConverter,
        LoggerInterface $logger,
        string $name = null
    ) {
        $this->appState = $appState;
        $this->s3Adapter = $s3Adapter;
        $this->webpConverter = $webpConverter;
        $this->logger = $logger;
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName(self::COMMAND_NAME);
        $this->setDescription(self::COMMAND_DESCRIPTION);
        $this->addOption(
            self::OPTION_FORCE,
            '-f',
            InputOption::VALUE_NONE,
            'Force regeneration of existing WebP files and thumbnails.'
        );
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->setAreaCode('global');
        } catch (LocalizedException) {
            // Area code is already set
        }

        $isForce = $input->getOption(self::OPTION_FORCE);
        $output->writeln('<info>Starting WebP conversion, thumbnail generation, and cleanup process for S3 media...</info>');
        if ($isForce) {
            $output->writeln('<comment>Force mode is enabled. Existing WebP files will be regenerated.</comment>');
        }

        $cleanedCount = $this->cleanupLegacyFiles($output);

        $excludedPrefixes = [
            'pub/media/catalog/',
            'pub/media/tmp/',
            'pub/media/.thumbs', // Ignore thumbnail directories
        ];
        $imageExtensions = ['jpg', 'jpeg', 'png'];
        $mediaPrefix = 'pub/media/';

        $processedCount = 0;
        $convertedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;
        $deletedForForceCount = 0;

        try {
            $objects = $this->s3Adapter->listObjects('', $imageExtensions);

            foreach ($objects as $s3Key) {
                $isExcluded = false;
                foreach ($excludedPrefixes as $excludedPrefix) {
                    if (str_starts_with($s3Key, $excludedPrefix)) {
                        $isExcluded = true;
                        break;
                    }
                }

                if ($isExcluded) {
                    continue;
                }

                $processedCount++;
                $output->writeln("Processing: <comment>$s3Key</comment>");

                $mediaRelativePath = substr($s3Key, strlen($mediaPrefix));
                $correctWebpPath = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $mediaRelativePath);

                if ($this->s3Adapter->doesObjectExist($correctWebpPath)) {
                    if (!$isForce) {
                        $output->writeln("  -> Correct WebP version already exists. Skipping conversion.");
                        $skippedCount++;
                        continue;
                    }

                    // Force mode: delete existing files before conversion
                    $output->writeln("  -> <comment>Force mode: Deleting existing WebP file...</comment>");
                    try {
                        $this->s3Adapter->deleteObject($correctWebpPath);
                        $deletedForForceCount++;
                        $thumbnailPath = $this->webpConverter->getThumbnailPath($correctWebpPath);
                        if ($thumbnailPath && $this->s3Adapter->doesObjectExist($thumbnailPath)) {
                            $output->writeln("  -> <comment>Force mode: Deleting existing thumbnail...</comment>");
                            $this->s3Adapter->deleteObject($thumbnailPath);
                        }
                    } catch (Exception $e) {
                        $output->writeln("  -> <error>Failed to delete existing files for force regeneration: {$e->getMessage()}</error>");
                        $this->logger->error("Force delete failed for {$correctWebpPath}: " . $e->getMessage());
                        $errorCount++;
                        continue; // Skip to next file on delete error
                    }
                }

                $output->writeln("  -> Converting...");

                try {
                    $result = $this->webpConverter->convertAndSave($mediaRelativePath, 89, $output, true);
                    if ($result) {
                        $output->writeln("  -> <info>Successfully converted and saved to $result</info>");
                        $convertedCount++;
                    } else {
                        $output->writeln("  -> <error>Conversion failed.</error>");
                        $errorCount++;
                    }
                } catch (Exception $e) {
                    $output->writeln("  -> <error>An error occurred during conversion: {$e->getMessage()}</error>");
                    $this->logger->error("WebP Conversion Error for $mediaRelativePath: " . $e->getMessage());
                    $errorCount++;
                }
            }
        } catch (Exception $e) {
            $output->writeln("<error>An unexpected error occurred: {$e->getMessage()}</error>");
            $this->logger->critical('WebP Conversion Command failed: ' . $e->getMessage());
            return Cli::RETURN_FAILURE;
        }

        $this->cleanupTmpFolder($output);

        $output->writeln('');
        $output->writeln('<info>--------------------</info>');
        $output->writeln('<info>Conversion Summary</info>');
        $output->writeln("<info>--------------------</info>");
        $output->writeln("Processed source images: <comment>$processedCount</comment>");
        $output->writeln("Legacy files cleaned: <info>$cleanedCount</info>");
        if ($isForce) {
            $output->writeln("Files deleted for regeneration: <comment>$deletedForForceCount</comment>");
        }
        $output->writeln("Successfully converted: <info>$convertedCount</info>");
        $output->writeln("Skipped (already exist): <comment>$skippedCount</comment>");
        $output->writeln("Errors: <error>$errorCount</error>");

        return Cli::RETURN_SUCCESS;
    }

    private function cleanupLegacyFiles(OutputInterface $output): int
    {
        $output->writeln('<info>Searching for and cleaning up legacy .webp files (e.g., .jpg.webp, .webp.webp)...</info>');
        $cleanedCount = 0;
        $mediaPrefix = 'pub/media/';

        try {
            $webpFiles = $this->s3Adapter->listObjects('', ['webp']);
            foreach ($webpFiles as $s3Key) {
                $mediaRelativePath = substr($s3Key, strlen($mediaPrefix));
                $filename = basename($mediaRelativePath);

                if (preg_match('/\.(jpg|jpeg|png|webp)\.webp$/i', $filename)) {
                    $output->writeln("  -> Found legacy WebP file: <comment>$mediaRelativePath</comment>. Deleting...");
                    try {
                        $this->s3Adapter->deleteObject($mediaRelativePath);
                        $output->writeln("  -> <info>Successfully deleted.</info>");
                        $cleanedCount++;
                    } catch (Exception $e) {
                        $output->writeln("  -> <error>Failed to delete legacy file: {$e->getMessage()}</error>");
                        $this->logger->error("Failed to delete legacy WebP file $mediaRelativePath: " . $e->getMessage());
                    }
                }
            }
        } catch (Exception $e) {
            $output->writeln("<error>An error occurred during legacy file cleanup: {$e->getMessage()}</error>");
            $this->logger->error("Legacy file cleanup failed: " . $e->getMessage());
        }

        $output->writeln("<info>Finished cleanup. Found and deleted $cleanedCount legacy files.</info>");
        return $cleanedCount;
    }

    private function cleanupTmpFolder(OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('<info>Cleaning up tmp folder in S3...</info>');
        try {
            $this->s3Adapter->deleteFolder('tmp');
            $output->writeln('  -> <info>tmp folder and its contents deleted.</info>');
            $this->s3Adapter->createFolder('tmp');
            $output->writeln('  -> <info>Empty tmp folder recreated.</info>');
        } catch (Exception $e) {
            $output->writeln("  -> <error>Could not clean up tmp folder: {$e->getMessage()}</error>");
            $this->logger->error("Could not clean up tmp folder: " . $e->getMessage());
        }
    }
}
