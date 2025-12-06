<?php
namespace GardenLawn\MediaGallery\Model;

use Magento\Framework\App\ResourceConnection;
use GardenLawn\MediaGallery\Model\ResourceModel\Gallery\CollectionFactory as GalleryCollectionFactory;
use Magento\Framework\DB\Adapter\AdapterInterface;

class AssetLinker
{
    protected ResourceConnection $resourceConnection;
    protected GalleryCollectionFactory $galleryCollectionFactory;

    public function __construct(
        ResourceConnection $resourceConnection,
        GalleryCollectionFactory $galleryCollectionFactory
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->galleryCollectionFactory = $galleryCollectionFactory;
    }

    public function createGalleriesFromPaths(): array
    {
        $connection = $this->resourceConnection->getConnection();
        $galleryTable = $connection->getTableName('gardenlawn_mediagallery');

        $activeDirectoryPaths = $this->getActiveDirectoryPaths($connection);
        $paths = $connection->fetchCol($connection->select()->from($galleryTable, ['path']));
        $existingGalleryPaths = array_flip(array_filter($paths));

        $galleriesToInsert = [];
        foreach ($activeDirectoryPaths as $path) {
            if (!isset($existingGalleryPaths[$path])) {
                $galleriesToInsert[] = ['path' => $path, 'enabled' => 1, 'sort_order' => 0];
            }
        }

        if (empty($galleriesToInsert)) {
            return [];
        }

        $connection->insertMultiple($galleryTable, $galleriesToInsert);
        return array_column($galleriesToInsert, 'path');
    }

    public function linkAssetsToGalleries(): array
    {
        $connection = $this->resourceConnection->getConnection();
        $linkTable = $connection->getTableName('gardenlawn_mediagallery_asset_link');
        $galleries = $this->galleryCollectionFactory->create();
        if ($galleries->getSize() === 0) {
            return [];
        }

        $maxSortOrders = $this->getMaxSortOrders($connection, $linkTable);
        $linksCreated = [];

        foreach ($galleries as $gallery) {
            $galleryId = $gallery->getId();
            $galleryPath = $gallery->getPath();
            if (empty($galleryPath)) continue;

            $assetsToLink = $this->findUnlinkedAssetsForGallery($connection, $galleryId, $galleryPath);
            if (!empty($assetsToLink)) {
                $currentSortOrder = ($maxSortOrders[$galleryId] ?? 0) + 1;
                $linksToInsert = [];
                foreach ($assetsToLink as $asset) {
                    if (!is_numeric($asset['id'])) continue;
                    $linksToInsert[] = [
                        'gallery_id' => $galleryId,
                        'asset_id' => (int)$asset['id'],
                        'sort_order' => $currentSortOrder++,
                        'enabled' => 1
                    ];
                }
                if (!empty($linksToInsert)) {
                    $connection->insertMultiple($linkTable, $linksToInsert);
                    $linksCreated[$galleryId] = ['path' => $galleryPath, 'count' => count($linksToInsert)];
                }
            }
        }
        return $linksCreated;
    }

    public function linkSingleAsset(int $assetId, string $assetPath): void
    {
        $connection = $this->resourceConnection->getConnection();
        $galleryTable = $connection->getTableName('gardenlawn_mediagallery');
        $linkTable = $connection->getTableName('gardenlawn_mediagallery_asset_link');

        $dir = dirname($assetPath);
        if ($dir === '.') {
            return; // No gallery for root assets
        }

        // Find or create the gallery
        $select = $connection->select()->from($galleryTable, 'id')->where('path = ?', $dir);
        $galleryId = $connection->fetchOne($select);

        if (!$galleryId) {
            $connection->insert($galleryTable, ['path' => $dir, 'enabled' => 1, 'sort_order' => 0]);
            $galleryId = $connection->lastInsertId($galleryTable);
        }

        // Check if link already exists
        $select = $connection->select()->from($linkTable, 'link_id')
            ->where('gallery_id = ?', $galleryId)
            ->where('asset_id = ?', $assetId);
        if ($connection->fetchOne($select)) {
            return; // Link already exists
        }

        // Get max sort order for the gallery
        $selectMaxSort = $connection->select()->from($linkTable, 'MAX(sort_order)')
            ->where('gallery_id = ?', $galleryId);
        $maxSortOrder = (int)$connection->fetchOne($selectMaxSort);

        // Insert the new link
        $connection->insert($linkTable, [
            'gallery_id' => $galleryId,
            'asset_id' => $assetId,
            'sort_order' => $maxSortOrder + 1,
            'enabled' => 1
        ]);
    }

    public function pruneOrphanedGalleries(bool $dryRun = false): array
    {
        $connection = $this->resourceConnection->getConnection();
        $galleryTable = $connection->getTableName('gardenlawn_mediagallery');
        $linkTable = $connection->getTableName('gardenlawn_mediagallery_asset_link');

        $activeDirectoryPaths = array_flip($this->getActiveDirectoryPaths($connection));
        $allGalleries = $connection->fetchAssoc($connection->select()->from($galleryTable, ['id', 'path']));

        $galleryIdsToDelete = [];
        $deletedGalleryPaths = [];
        foreach ($allGalleries as $gallery) {
            if (!isset($activeDirectoryPaths[$gallery['path']]) && !empty($gallery['path'])) {
                $galleryIdsToDelete[] = $gallery['id'];
                $deletedGalleryPaths[] = $gallery['path'];
            }
        }

        if (empty($galleryIdsToDelete)) {
            return [];
        }

        if (!$dryRun) {
            $connection->delete($linkTable, ['gallery_id IN (?)' => $galleryIdsToDelete]);
            $connection->delete($galleryTable, ['id IN (?)' => $galleryIdsToDelete]);
        }

        return $deletedGalleryPaths;
    }

    private function getActiveDirectoryPaths(AdapterInterface $connection): array
    {
        $mediaGalleryAssetTable = $connection->getTableName('media_gallery_asset');
        $selectPaths = $connection->select()->from($mediaGalleryAssetTable, ['path']);
        $assetPathStream = $connection->query($selectPaths);

        $directoryPaths = [];
        while ($row = $assetPathStream->fetch()) {
            if (empty($row['path'])) {
                continue;
            }
            $dir = dirname($row['path']);
            if ($dir && $dir !== '.') {
                $directoryPaths[$dir] = true;
            }
        }
        return array_keys($directoryPaths);
    }

    private function getMaxSortOrders(AdapterInterface $connection, string $linkTable): array
    {
        $selectMaxSortOrders = $connection->select()->from($linkTable, ['gallery_id', 'MAX(sort_order)'])->group('gallery_id');
        return $connection->fetchPairs($selectMaxSortOrders);
    }

    private function findUnlinkedAssetsForGallery(AdapterInterface $connection, int $galleryId, string $galleryPath): array
    {
        $linkTable = $connection->getTableName('gardenlawn_mediagallery_asset_link');
        $mediaGalleryAssetTable = $connection->getTableName('media_gallery_asset');
        $query = $connection->select()
            ->from(['mga' => $mediaGalleryAssetTable], ['id'])
            ->where('mga.path LIKE BINARY ?', $galleryPath . '/%')
            ->joinLeft(
                ['gmal' => $linkTable],
                'gmal.asset_id = mga.id AND gmal.gallery_id = ' . $galleryId,
                []
            )->where('gmal.asset_id IS NULL');
        return $connection->fetchAll($query);
    }
}
