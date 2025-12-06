<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Console\Command;

use GardenLawn\MediaGallery\Service\WebpConverter;
use GardenLawn\MediaGallery\Model\S3Adapter;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Psr\Log\LoggerInterface;
use Magento\Framework\Console\Cli;

class ConvertImagesToWebp extends Command
{
    private const string COMMAND_NAME = 'gardenlawn:gallery:convert-to-webp';
    private const string COMMAND_DESCRIPTION = 'Converts images in S3 media gallery to WebP format and cleans up legacy .ext.webp files.';

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
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->setAreaCode('global');
        } catch (LocalizedException) {
            // Area code is already set
        }

        $output->writeln('<info>Starting WebP conversion and cleanup process for S3 media...</info>');

        $excludedPrefixes = [
            'pub/media/catalog/',
            'pub/media/tmp/',
        ];
        $imageExtensions = ['jpg', 'jpeg', 'png'];
        $mediaPrefix = 'pub/media/';

        $processedCount = 0;
        $convertedCount = 0;
        $skippedCount = 0;
        $cleanedCount = 0;
        $errorCount = 0;

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
                $legacyWebpPath = $mediaRelativePath . '.webp';
                $correctWebpPath = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $mediaRelativePath);

                // Cleanup legacy .ext.webp files
                if ($this->s3Adapter->doesObjectExist($legacyWebpPath)) {
                    $output->writeln("  -> Found legacy WebP file: <comment>$legacyWebpPath</comment>. Deleting...");
                    try {
                        $this->s3Adapter->deleteObject($legacyWebpPath);
                        $output->writeln("  -> <info>Successfully deleted.</info>");
                        $cleanedCount++;
                    } catch (\Exception $e) {
                        $output->writeln("  -> <error>Failed to delete legacy file: {$e->getMessage()}</error>");
                        $this->logger->error("Failed to delete legacy WebP file $legacyWebpPath: " . $e->getMessage());
                    }
                }

                // Convert to correctly named .webp file if it doesn't exist
                if ($this->s3Adapter->doesObjectExist($correctWebpPath)) {
                    $output->writeln("  -> Correct WebP version already exists. Skipping conversion.");
                    $skippedCount++;
                    continue;
                }

                $output->writeln("  -> Correct WebP version not found. Converting...");

                try {
                    $result = $this->webpConverter->convertAndSave($mediaRelativePath);
                    if ($result) {
                        $output->writeln("  -> <info>Successfully converted and saved to $result</info>");
                        $convertedCount++;
                    } else {
                        $output->writeln("  -> <error>Conversion failed.</error>");
                        $errorCount++;
                    }
                } catch (\Exception $e) {
                    $output->writeln("  -> <error>An error occurred during conversion: {$e->getMessage()}</error>");
                    $this->logger->error("WebP Conversion Error for $mediaRelativePath: " . $e->getMessage());
                    $errorCount++;
                }
            }
        } catch (\Exception $e) {
            $output->writeln("<error>An unexpected error occurred: {$e->getMessage()}</error>");
            $this->logger->critical('WebP Conversion Command failed: ' . $e->getMessage());
            return Cli::RETURN_FAILURE;
        }

        $output->writeln('');
        $output->writeln('<info>--------------------</info>');
        $output->writeln('<info>Conversion Summary</info>');
        $output->writeln("<info>--------------------</info>");
        $output->writeln("Processed source images: <comment>$processedCount</comment>");
        $output->writeln("Legacy files cleaned: <info>$cleanedCount</info>");
        $output->writeln("Successfully converted: <info>$convertedCount</info>");
        $output->writeln("Skipped (already exist): <comment>$skippedCount</comment>");
        $output->writeln("Errors: <error>$errorCount</error>");

        return Cli::RETURN_SUCCESS;
    }
}
