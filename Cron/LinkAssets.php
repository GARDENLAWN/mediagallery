<?php
namespace GardenLawn\MediaGallery\Cron;

use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

class LinkAssets
{
    protected ResourceConnection $resource;
    protected LoggerInterface $logger;

    public function __construct(
        ResourceConnection $resource,
        LoggerInterface $logger
    ) {
        $this->resource = $resource;
        $this->logger = $logger;
    }

    public function execute(): void
    {
        $this->logger->info('Starting GardenLawn MediaGallery asset linking cron job.');
        $connection = $this->resource->getConnection();
        $connection->beginTransaction();
        try {
            $mediaGalleryAssetTable = $connection->getTableName('media_gallery_asset');
            $gardenLawnMediaGalleryTable = $connection->getTableName('gardenlawn_mediagallery');

            // Using a raw but clear UPDATE ... JOIN query
            $query = "
                UPDATE {$mediaGalleryAssetTable} AS mga
                JOIN {$gardenLawnMediaGalleryTable} AS gmg
                  ON mga.path LIKE CONCAT(gmg.name, '/%')
                SET mga.mediagallery_id = gmg.id
                WHERE mga.mediagallery_id IS NULL;
            ";

            $rowCount = $connection->query($query)->rowCount();
            $connection->commit();

            $this->logger->info(sprintf('GardenLawn MediaGallery cron job finished. Updated %d assets.', $rowCount));
        } catch (\Exception $e) {
            $connection->rollBack();
            $this->logger->critical('Error in GardenLawn MediaGallery asset linking cron job: ' . $e->getMessage(), ['exception' => $e]);
        }
    }
}
