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
    private const string COMMAND_DESCRIPTION = 'Converts images to WebP, creates thumbnails, and cleans up legacy files.';
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
            'Force regeneration of existing WebP files and thumbnails, and refreshes metadata for original WebP files.'
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
        $output->writeln('<info>Starting WebP conversion process...</info>');
        if ($isForce) {
            $output->writeln('<comment>Force mode is enabled. Existing WebP files will be regenerated/refreshed.</comment>');
        }

        $cleanedCount = $this->cleanupLegacyFiles($output);

        $excludedPrefixes = [
            'pub/media/tmp/',
            'var/tmp/',
            'webp_temp/',
            'pub/media/.thumbs',
        ];
        $catalogDir = 'pub/media/catalog/';
        // Now include 'webp' in the extensions to list, as they can be source files too.
        $imageExtensions = ['jpg', 'jpeg', 'png', 'svg', 'webp'];
        $mediaPrefix = 'pub/media/';

        $processedCount = 0;
        $convertedCount = 0;
        $thumbOnlyCount = 0;
        $skippedCount = 0;
        $errorCount = 0;
        $deletedForForceCount = 0;
        $refreshedOriginalWebpCount = 0; // New counter for original WebP files

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
                $isCatalogImage = str_starts_with($s3Key, $catalogDir);
                $sourceExtension = strtolower(pathinfo($mediaRelativePath, PATHINFO_EXTENSION));

                // Handle original WebP files (not derived, not thumbnails)
                if ($sourceExtension === 'webp' && !str_contains($s3Key, '.thumbs')) {
                    // Check if this WebP file is a "derived" one (e.g., from a JPG/PNG source)
                    // If it is, we'll let the JPG/PNG source handle its regeneration.
                    // If it's a standalone WebP, we treat it as an original.
                    $isDerivedWebp = false;
                    foreach (['jpg', 'jpeg', 'png'] as $originalExt) {
                        $potentialOriginalSource = preg_replace('/\.webp$/i', '.' . $originalExt, $mediaRelativePath);
                        if ($this->s3Adapter->doesObjectExist($mediaPrefix . $potentialOriginalSource)) {
                            $isDerivedWebp = true;
                            break;
                        }
                    }

                    if (!$isDerivedWebp) {
                        // This is an original WebP file (no JPG/PNG source found)
                        if ($isForce) {
                            $output->writeln("  -> Original WebP detected. Force mode: Re-uploading to refresh metadata.");
                            try {
                                // Call a new method in WebpConverter to re-upload
                                $this->webpConverter->reUploadFile($mediaRelativePath, $output);
                                $refreshedOriginalWebpCount++;
                            } catch (Exception $e) {
                                $output->writeln("  -> <error>Failed to re-upload original WebP: {$e->getMessage()}</error>");
                                $errorCount++;
                            }
                        } else {
                            $output->writeln("  -> Original WebP already exists. Skipping.");
                            $skippedCount++;
                        }
                        continue; // Move to the next object
                    }
                }

                // Existing logic for JPG/PNG/SVG sources (and derived WebP files will be handled here if their source exists)
                if ($isCatalogImage) {
                    $output->writeln("  -> Catalog image detected. Generating thumbnail only.");

                    // For catalog images, the thumbnail path for SVG should be .svg, others .webp
                    $thumbnailPath = $this->webpConverter->getThumbnailPath($mediaRelativePath);
                    if ($sourceExtension !== 'svg') {
                        $thumbnailPath = str_replace(['.jpg', '.png', '.jpeg'], '.webp', $thumbnailPath);
                    }

                    if ($thumbnailPath && (!$this->s3Adapter->doesObjectExist($thumbnailPath) || $isForce)) {
                        if ($isForce && $thumbnailPath && $this->s3Adapter->doesObjectExist($thumbnailPath)) {
                            $output->writeln("  -> <comment>Force mode: Deleting existing thumbnail...</comment>");
                            $this->s3Adapter->deleteObject($thumbnailPath);
                            $deletedForForceCount++;
                        }
                        $result = $this->webpConverter->createWebpThumbnail($mediaRelativePath, 89, $output);
                        if ($result) {
                            $thumbOnlyCount++;
                        } else {
                            $errorCount++;
                        }
                    } else {
                        $output->writeln("  -> Thumbnail already exists. Skipping.");
                        $skippedCount++;
                    }
                } else {
                    // For other images (non-catalog, non-original-webp), do the full conversion
                    $correctWebpPath = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $mediaRelativePath);

                    if ($this->s3Adapter->doesObjectExist($correctWebpPath)) {
                        if (!$isForce) {
                            $output->writeln("  -> Correct WebP version already exists. Skipping conversion.");
                            $skippedCount++;
                            continue;
                        }

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
                            $output->writeln("  -> <error>Failed to delete existing files: {$e->getMessage()}</error>");
                            $errorCount++;
                            continue;
                        }
                    }

                    $output->writeln("  -> Converting...");
                    try {
                        $result = $this->webpConverter->convertAndSave($mediaRelativePath, 89, $output, true);
                        if ($result) {
                            $convertedCount++;
                        } else {
                            $errorCount++;
                        }
                    } catch (Exception $e) {
                        $output->writeln("  -> <error>An error occurred during conversion: {$e->getMessage()}</error>");
                        $this->logger->error("WebP Conversion Error for $mediaRelativePath: " . $e->getMessage());
                        $errorCount++;
                    }
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
            $output->writeln("Original WebP files refreshed: <info>$refreshedOriginalWebpCount</info>");
            $output->writeln("Derived files deleted for regeneration: <comment>$deletedForForceCount</comment>");
        }
        $output->writeln("Full conversions (from JPG/PNG/SVG): <info>$convertedCount</info>");
        $output->writeln("Thumbnails only (for catalog): <info>$thumbOnlyCount</info>");
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
