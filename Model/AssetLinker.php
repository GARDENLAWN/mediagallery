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
        $existingGalleryNames = array_flip($connection->fetchCol($connection->select()->from($galleryTable, ['name'])));

        $galleriesToInsert = [];
        foreach ($activeDirectoryPaths as $path) {
            if (!isset($existingGalleryNames[$path])) {
                $galleriesToInsert[] = ['name' => $path, 'enabled' => 1, 'sort_order' => 0];
            }
        }

        if (empty($galleriesToInsert)) {
            return [];
        }

        $connection->insertMultiple($galleryTable, $galleriesToInsert);
        return array_column($galleriesToInsert, 'name');
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
            $galleryName = $gallery->getName();
            if (empty($galleryName)) continue;

            $assetsToLink = $this->findUnlinkedAssetsForGallery($connection, $galleryId, $galleryName);
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
                    $linksCreated[$galleryId] = ['name' => $galleryName, 'count' => count($linksToInsert)];
                }
            }
        }
        return $linksCreated;
    }

    public function pruneOrphanedGalleries(bool $dryRun = false): array
    {
        $connection = $this->resourceConnection->getConnection();
        $galleryTable = $connection->getTableName('gardenlawn_mediagallery');
        $linkTable = $connection->getTableName('gardenlawn_mediagallery_asset_link');

        $activeDirectoryPaths = array_flip($this->getActiveDirectoryPaths($connection));
        $allGalleries = $connection->fetchAssoc($connection->select()->from($galleryTable, ['id', 'name']));

        $galleryIdsToDelete = [];
        $deletedGalleryNames = [];
        foreach ($allGalleries as $gallery) {
            if (!isset($activeDirectoryPaths[$gallery['name']])) {
                $galleryIdsToDelete[] = $gallery['id'];
                $deletedGalleryNames[] = $gallery['name'];
            }
        }

        if (empty($galleryIdsToDelete)) {
            return [];
        }

        if (!$dryRun) {
            $connection->delete($linkTable, ['gallery_id IN (?)' => $galleryIdsToDelete]);
            $connection->delete($galleryTable, ['id IN (?)' => $galleryIdsToDelete]);
        }

        return $deletedGalleryNames;
    }

    private function getActiveDirectoryPaths(AdapterInterface $connection): array
    {
        $mediaGalleryAssetTable = $connection->getTableName('media_gallery_asset');
        $selectPaths = $connection->select()->from($mediaGalleryAssetTable, ['path']);
        $assetPathStream = $connection->query($selectPaths);

        $directoryPaths = [];
        while ($row = $assetPathStream->fetch()) {
            $pathParts = explode('/', dirname($row['path']));
            $currentPath = '';
            foreach ($pathParts as $part) {
                // CORRECTED: Use strict comparison to avoid '0' being treated as empty.
                if ($part === '' || $part === '.') {
                    continue;
                }
                $currentPath .= (empty($currentPath) ? '' : '/') . $part;
                $directoryPaths[$currentPath] = true;
            }
        }
        return array_keys($directoryPaths);
    }

    private function getMaxSortOrders(AdapterInterface $connection, string $linkTable): array
    {
        $selectMaxSortOrders = $connection->select()->from($linkTable, ['gallery_id', 'MAX(sort_order)'])->group('gallery_id');
        return $connection->fetchPairs($selectMaxSortOrders);
    }

    private function findUnlinkedAssetsForGallery(AdapterInterface $connection, int $galleryId, string $galleryName): array
    {
        $linkTable = $connection->getTableName('gardenlawn_mediagallery_asset_link');
        $mediaGalleryAssetTable = $connection->getTableName('media_gallery_asset');
        $query = $connection->select()
            ->from(['mga' => $mediaGalleryAssetTable], ['id'])
            ->where('mga.path LIKE ?', $galleryName . '/%')
            ->joinLeft(
                ['gmal' => $linkTable],
                'gmal.asset_id = mga.id AND gmal.gallery_id = ' . $galleryId,
                []
            )->where('gmal.asset_id IS NULL');
        return $connection->fetchAll($query);
    }
}
