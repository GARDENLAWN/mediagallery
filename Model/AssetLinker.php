<?php
namespace GardenLawn\MediaGallery\Model;

use Magento\Framework\App\ResourceConnection;
use GardenLawn\MediaGallery\Model\ResourceModel\Gallery\CollectionFactory as GalleryCollectionFactory;
use Magento\Framework\DB\Adapter\AdapterInterface;

/**
 * Service class to handle gallery creation and asset linking logic.
 */
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

    /**
     * Creates new galleries based on asset directory paths.
     *
     * @return array An array containing a list of created gallery names.
     * @throws \Zend_Db_Statement_Exception
     */
    public function createGalleriesFromPaths(): array
    {
        $connection = $this->resourceConnection->getConnection();
        $mediaGalleryAssetTable = $connection->getTableName('media_gallery_asset');
        $galleryTable = $connection->getTableName('gardenlawn_mediagallery');

        // 1. Stream all asset paths to conserve memory
        $selectPaths = $connection->select()->from($mediaGalleryAssetTable, ['path']);
        $assetPathStream = $connection->query($selectPaths);

        // 2. Extract all unique directory paths
        $directoryPaths = [];
        while ($row = $assetPathStream->fetch()) {
            $pathParts = explode('/', dirname($row['path']));
            $currentPath = '';
            foreach ($pathParts as $part) {
                if (empty($part) || $part === '.') continue;
                $currentPath .= (empty($currentPath) ? '' : '/') . $part;
                $directoryPaths[$currentPath] = true;
            }
        }
        $uniqueDirectoryPaths = array_keys($directoryPaths);
        ksort($uniqueDirectoryPaths);

        // 3. Get existing gallery names
        $selectExisting = $connection->select()->from($galleryTable, ['name']);
        $existingGalleryNames = array_flip($connection->fetchCol($selectExisting));

        // 4. Find and prepare new galleries for insertion
        $galleriesToInsert = [];
        foreach ($uniqueDirectoryPaths as $path) {
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

    /**
     * Links assets to galleries based on path prefixes.
     *
     * @return array An associative array with gallery IDs as keys and the count of new links as values.
     */
    public function linkAssetsToGalleries(): array
    {
        $connection = $this->resourceConnection->getConnection();
        $linkTable = $connection->getTableName('gardenlawn_mediagallery_asset_link');
        $mediaGalleryAssetTable = $connection->getTableName('media_gallery_asset');

        $galleries = $this->galleryCollectionFactory->create();
        if ($galleries->getSize() === 0) {
            return [];
        }

        // Get all max sort_orders for all galleries in one query
        $maxSortOrders = $this->getMaxSortOrders($connection, $linkTable);
        $linksCreated = [];

        foreach ($galleries as $gallery) {
            $galleryId = $gallery->getId();
            $galleryName = $gallery->getName();

            if (empty($galleryName)) {
                continue;
            }

            $assetsToLink = $this->findUnlinkedAssetsForGallery($connection, $galleryId, $galleryName);

            if (!empty($assetsToLink)) {
                $maxSortOrder = $maxSortOrders[$galleryId] ?? 0;
                $currentSortOrder = $maxSortOrder + 1;
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
                    $linksCreated[$galleryId] = [
                        'name' => $galleryName,
                        'count' => count($linksToInsert)
                    ];
                }
            }
        }

        return $linksCreated;
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
            )
            ->where('gmal.asset_id IS NULL');

        return $connection->fetchAll($query);
    }
}
