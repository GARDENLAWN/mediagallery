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
use Magento\Framework\App\RequestInterface; // Dodano RequestInterface

class DataProvider extends AbstractDataProvider
{
    protected $collection;
    protected $loadedData;
    protected ResourceConnection $resourceConnection;
    protected StoreManagerInterface $storeManager;
    protected LoggerInterface $logger;
    protected RequestInterface $request; // Dodano RequestInterface

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        ResourceConnection $resourceConnection,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger,
        RequestInterface $request, // Wstrzykujemy RequestInterface
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        $this->resourceConnection = $resourceConnection;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
        $this->request = $request; // Przypisujemy RequestInterface
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    public function getData(): array
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

        // Obsługa przypadku, gdy tworzona jest nowa galeria (brak ID w request)
        // Wtedy $items jest puste, a $loadedData również.
        // Musimy zapewnić, że 'images' jest zawsze tablicą.
        $requestedId = $this->request->getParam($this->getRequestFieldName());
        if (empty($this->loadedData) && $requestedId === null) {
            // Dla nowej galerii, użyjemy tymczasowego klucza '0' lub 'new'
            // Magento UI Component często oczekuje klucza numerycznego dla nowo tworzonych encji
            $this->loadedData[0]['images'] = [];
        }


        return $this->loadedData;
    }

    protected function getAssociatedAssets(int $galleryId): array
    {
        try {
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
                    'is_main' => false,
                    'asset_id' => (int)$asset['id'],
                    'enabled' => (bool)$asset['enabled'],
                ];
            }
            return $formattedAssets;
        } catch (Exception $e) {
            $this->logger->critical(sprintf('MediaGallery DataProvider: Error fetching associated assets for gallery ID %d: %s', $galleryId, $e->getMessage()), ['exception' => $e]);
            return []; // Zwróć pustą tablicę w przypadku błędu
        }
    }

    /**
     * @throws NoSuchEntityException
     */
    private function getMediaUrl($path): string
    {
        return $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA) . $path;
    }
}
