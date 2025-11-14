<?php
namespace GardenLawn\MediaGallery\Cron;

use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

class LinkAssets
{
    protected $resource;
    protected $logger;

    public function __construct(
        ResourceConnection $resource,
        LoggerInterface $logger
    ) {
        $this->resource = $resource;
        $this->logger = $logger;
    }

    public function execute()
    {
        $this->logger->info('Starting GardenLawn MediaGallery asset linking cron job.');
        try {
            $connection = $this->resource->getConnection();
            $mediaGalleryAssetTable = $connection->getTableName('media_gallery_asset');
            $gardenLawnMediaGalleryTable = $connection->getTableName('gardenlawn_mediagallery');

            $query = $connection->updateFromSelect(
                new \Magento\Framework\DB\Select($connection),
                ['mga' => $mediaGalleryAssetTable],
                ['mga.mediagallery_id' => 'gmg.id']
            )->join(
                ['gmg' => $gardenLawnMediaGalleryTable],
                "mga.path LIKE CONCAT(gmg.name, '/%')",
                []
            )->where(
                'mga.mediagallery_id IS NULL'
            );

            $rowCount = $connection->query($query)->rowCount();

            $this->logger->info(sprintf('GardenLawn MediaGallery cron job finished. Updated %d assets.', $rowCount));
        } catch (\Exception $e) {
            $this->logger->critical('Error in GardenLawn MediaGallery asset linking cron job: ' . $e->getMessage());
        }
    }
}
