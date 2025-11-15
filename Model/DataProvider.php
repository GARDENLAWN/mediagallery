<?php
namespace GardenLawn\MediaGallery\Model;

use Exception;
use GardenLawn\MediaGallery\Model\ResourceModel\Gallery\CollectionFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Psr\Log\LoggerInterface;
// use Magento\Framework\App\RequestInterface; // Usunięto RequestInterface

class DataProvider extends AbstractDataProvider
{
    protected $collection;
    protected $loadedData;
    protected ResourceConnection $resourceConnection;
    protected StoreManagerInterface $storeManager;
    protected LoggerInterface $logger;
    // protected RequestInterface $request; // Usunięto RequestInterface

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        ResourceConnection $resourceConnection,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger,
        // RequestInterface $request, // Usunięto RequestInterface
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        $this->resourceConnection = $resourceConnection;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
        // $this->request = $request; // Usunięto RequestInterface
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    public function getData(): array
    {
        if (isset($this->loadedData)) {
            return $this->loadedData;
        }

        $items = $this->collection->getItems();
        $this->loadedData = [];
        $galleryIds = [];
        foreach ($items as $item) {
            $galleryIds[] = $item->getId();
        }

        $allAssociatedAssets = $this->_getGroupedAssociatedAssets($galleryIds);

        foreach ($items as $item) {
            $galleryData = $item->getData();
            $galleryId = $item->getId();

            // Przypisz powiązane zasoby z pre-pobranej i pogrupowanej tablicy
            $galleryData['images'] = $allAssociatedAssets[$galleryId] ?? [];

            $this->loadedData[$galleryId] = $galleryData;
        }

        // Obsługa przypadku, gdy tworzona jest nowa galeria (brak ID w request)
        // Wtedy $items jest puste, a $loadedData również.
        // Musimy zapewnić, że 'images' jest zawsze tablicą.
        // Zamiast RequestInterface, sprawdzamy czy kolekcja jest pusta i czy nie ma załadowanych danych.
        if (empty($items) && empty($this->loadedData)) {
            $this->loadedData[0]['images'] = [];
        }

        return $this->loadedData;
    }

    /**
     * Pobiera wszystkie powiązane zasoby dla podanych ID galerii i grupuje je.
     *
     * @param array $galleryIds
     * @return array
     * @throws NoSuchEntityException
     */
    protected function _getGroupedAssociatedAssets(array $galleryIds): array
    {
        if (empty($galleryIds)) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $linkTable = $connection->getTableName('gardenlawn_mediagallery_asset_link');
        $assetTable = $connection->getTableName('media_gallery_asset');

        $select = $connection->select()
            ->from(['gmal' => $linkTable], ['gallery_id', 'sort_order', 'enabled'])
            ->join(
                ['mga' => $assetTable],
                'gmal.asset_id = mga.id',
                ['id', 'path']
            )
            ->where('gmal.gallery_id IN (?)', $galleryIds)
            ->order('gmal.sort_order ASC');

        $assets = $connection->fetchAll($select);
        $groupedAssets = [];

        foreach ($assets as $asset) {
            $formattedAsset = [
                'file' => $asset['path'],
                'url' => $this->getMediaUrl($asset['path']),
                'position' => (int)$asset['sort_order'],
                'is_main' => false, // Domyślnie false, jeśli nie ma logiki na "główny" obraz
                'asset_id' => (int)$asset['id'],
                'enabled' => (bool)$asset['enabled'],
            ];
            $groupedAssets[$asset['gallery_id']][] = $formattedAsset;
        }

        return $groupedAssets;
    }

    /**
     * @throws NoSuchEntityException
     */
    private function getMediaUrl($path): string
    {
        return $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA) . $path;
    }
}
