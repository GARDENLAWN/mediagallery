<?php
namespace GardenLawn\MediaGallery\Cron;

use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;
use GardenLawn\MediaGallery\Model\ResourceModel\Gallery\CollectionFactory as GalleryCollectionFactory; // Dodano

class LinkAssets
{
    protected ResourceConnection $resource;
    protected LoggerInterface $logger;
    protected GalleryCollectionFactory $galleryCollectionFactory; // Dodano

    public function __construct(
        ResourceConnection $resource,
        LoggerInterface $logger,
        GalleryCollectionFactory $galleryCollectionFactory // Wstrzykujemy
    ) {
        $this->resource = $resource;
        $this->logger = $logger;
        $this->galleryCollectionFactory = $galleryCollectionFactory; // Przypisujemy
    }

    public function execute(): void
    {
        $this->logger->info('MediaGallery Cron: Starting asset linking cron job.');
        $connection = $this->resource->getConnection();
        $connection->beginTransaction();
        try {
            $linkTable = $connection->getTableName('gardenlawn_mediagallery_asset_link');
            $mediaGalleryAssetTable = $connection->getTableName('media_gallery_asset');
            $gardenLawnMediaGalleryTable = $connection->getTableName('gardenlawn_mediagallery');

            $galleries = $this->galleryCollectionFactory->create();
            $totalLinksInserted = 0;

            foreach ($galleries as $gallery) {
                $galleryId = $gallery->getId();
                $galleryName = $gallery->getName();

                if (empty($galleryName)) {
                    $this->logger->warning(sprintf('MediaGallery Cron: Skipping gallery ID %d because its name is empty.', $galleryId));
                    continue;
                }

                // Find the maximum sort_order for the current gallery
                $maxSortOrder = (int)$connection->fetchOne(
                    $connection->select()
                        ->from($linkTable, new \Zend_Db_Expr('MAX(sort_order)'))
                        ->where('gallery_id = ?', $galleryId)
                );
                $currentSortOrder = $maxSortOrder + 1;

                // Find assets that match the gallery name prefix and are not yet linked
                $query = $connection->select()
                    ->from(['mga' => $mediaGalleryAssetTable], ['id', 'path'])
                    ->where('mga.path LIKE ?', $galleryName . '/%')
                    ->joinLeft(
                        ['gmal' => $linkTable],
                        'gmal.asset_id = mga.id AND gmal.gallery_id = ' . $galleryId,
                        []
                    )
                    ->where('gmal.asset_id IS NULL');

                $assetsToLink = $connection->fetchAll($query);

                if (!empty($assetsToLink)) {
                    $linksToInsert = [];
                    foreach ($assetsToLink as $asset) {
                        if (!is_numeric($asset['id'])) {
                            $this->logger->warning(sprintf('MediaGallery Cron: Skipping asset with invalid ID "%s" (path: %s) for gallery ID %d.', $asset['id'], $asset['path'], $galleryId));
                            continue;
                        }
                        $linksToInsert[] = [
                            'gallery_id' => $galleryId,
                            'asset_id' => (int)$asset['id'],
                            'sort_order' => $currentSortOrder++,
                            'enabled' => 1
                        ];
                    }

                    if (!empty($linksToInsert)) {
                        $connection->insertMultiple($linkTable, $linksToInsert);
                        $insertedCount = count($linksToInsert);
                        $totalLinksInserted += $insertedCount;
                        $this->logger->info(sprintf('MediaGallery Cron: Linked %d assets to gallery "%s" (ID: %d).', $insertedCount, $galleryName, $galleryId));
                    } else {
                        $this->logger->info(sprintf('MediaGallery Cron: No valid assets to link for gallery "%s" (ID: %d) after validation.', $galleryName, $galleryId));
                    }
                } else {
                    $this->logger->info(sprintf('MediaGallery Cron: No new assets to link for gallery "%s" (ID: %d).', $galleryName, $galleryId));
                }
            }

            $connection->commit();
            $this->logger->info(sprintf('MediaGallery Cron: Asset linking cron job finished. Total new links inserted: %d', $totalLinksInserted));
        } catch (\Exception $e) {
            $connection->rollBack();
            $this->logger->critical('MediaGallery Cron: Error in asset linking cron job: ' . $e->getMessage(), ['exception' => $e]);
        }
    }
}
