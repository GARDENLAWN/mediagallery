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
            $linkTable = $connection->getTableName('gardenlawn_mediagallery_asset_link');

            // Query to find assets that match a gallery name prefix in their path
            // and are not yet linked in the gardenlawn_mediagallery_asset_link table.
            $query = "
                INSERT INTO {$linkTable} (gallery_id, asset_id, sort_order)
                SELECT
                    gmg.id AS gallery_id,
                    mga.id AS asset_id,
                    0 AS sort_order -- Default sort order for cron-linked assets
                FROM
                    {$mediaGalleryAssetTable} AS mga
                JOIN
                    {$gardenLawnMediaGalleryTable} AS gmg ON mga.path LIKE CONCAT(gmg.name, '/%')
                LEFT JOIN
                    {$linkTable} AS gmal ON gmal.gallery_id = gmg.id AND gmal.asset_id = mga.id
                WHERE
                    gmal.gallery_id IS NULL;
            ";

            $rowCount = $connection->query($query)->rowCount();
            $connection->commit();

            $this->logger->info(sprintf('GardenLawn MediaGallery cron job finished. Inserted %d new asset links.', $rowCount));
        } catch (\Exception $e) {
            $connection->rollBack();
            $this->logger->critical('Error in GardenLawn MediaGallery asset linking cron job: ' . $e->getMessage(), ['exception' => $e]);
        }
    }
}
