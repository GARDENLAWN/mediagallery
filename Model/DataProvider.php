<?php
namespace GardenLawn\MediaGallery\Model;

use GardenLawn\MediaGallery\Model\ResourceModel\Gallery\CollectionFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;

class DataProvider extends AbstractDataProvider
{
    protected $collection;
    protected $loadedData;
    protected ResourceConnection $resourceConnection;
    protected StoreManagerInterface $storeManager;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        ResourceConnection $resourceConnection,
        StoreManagerInterface $storeManager,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        $this->resourceConnection = $resourceConnection;
        $this->storeManager = $storeManager;
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    public function getData()
    {
        if (isset($this->loadedData)) {
            return $this->loadedData;
        }

        $items = $this->collection->getItems();
        $this->loadedData = [];
        foreach ($items as $item) {
            $galleryData = $item->getData();
            $galleryId = $item->getId();

            // Pobierz powiązane zasoby dla tej galerii
            $galleryData['images'] = $this->getAssociatedAssets($galleryId);

            $this->loadedData[$galleryId] = $galleryData;
        }

        return $this->loadedData;
    }

    protected function getAssociatedAssets(int $galleryId): array
    {
        $connection = $this->resourceConnection->getConnection();
        $linkTable = $connection->getTableName('gardenlawn_mediagallery_asset_link');
        $assetTable = $connection->getTableName('media_gallery_asset');

        $select = $connection->select()
            ->from(['gmal' => $linkTable], ['sort_order', 'enabled'])
            ->join(
                ['mga' => $assetTable],
                'gmal.asset_id = mga.id',
                ['id', 'path']
            )
            ->where('gmal.gallery_id = ?', $galleryId)
            ->order('gmal.sort_order ASC');

        $assets = $connection->fetchAll($select);
        $formattedAssets = [];
        foreach ($assets as $asset) {
            $formattedAssets[] = [
                'file' => $asset['path'],
                'url' => $this->getMediaUrl($asset['path']),
                'position' => (int)$asset['sort_order'],
                'is_main' => false, // Możesz dodać logikę, jeśli masz "główny" obraz
                'asset_id' => (int)$asset['id'],
                'enabled' => (bool)$asset['enabled'],
            ];
        }
        return $formattedAssets;
    }

    private function getMediaUrl($path): string
    {
        return $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . $path;
    }
}
