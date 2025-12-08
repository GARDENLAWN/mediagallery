<?php
namespace GardenLawn\MediaGallery\Cron;

use Psr\Log\LoggerInterface;
use GardenLawn\Core\Model\S3AssetSynchronizer;

class SyncS3
{
    protected LoggerInterface $logger;
    protected S3AssetSynchronizer $synchronizer;

    public function __construct(
        LoggerInterface $logger,
        S3AssetSynchronizer $synchronizer
    ) {
        $this->logger = $logger;
        $this->synchronizer = $synchronizer;
    }

    public function execute(): void
    {
        $this->logger->info('MediaGallery Cron: Starting S3 assets synchronization job.');

        try {
            $insertedAssets = $this->synchronizer->synchronize();

            if (empty($insertedAssets)) {
                $this->logger->info('MediaGallery Cron: Database is already in sync. No new assets to add.');
            } else {
                $this->logger->info(sprintf('MediaGallery Cron: Successfully inserted %d new asset records from S3.', count($insertedAssets)));
            }

            $this->logger->info('MediaGallery Cron: S3 synchronization job finished successfully.');

        } catch (\Exception $e) {
            $this->logger->critical('MediaGallery Cron: Error in S3 synchronization job: ' . $e->getMessage(), ['exception' => $e]);
        }
    }
}
