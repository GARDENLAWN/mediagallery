<?php
namespace GardenLawn\MediaGallery\Ui\Component\Listing;

use GardenLawn\MediaGallery\Model\ResourceModel\Asset\CollectionFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Psr\Log\LoggerInterface; // Dodano LoggerInterface

class AssetDataProvider extends AbstractDataProvider
{
    protected RequestInterface $request;
    protected StoreManagerInterface $storeManager;
    protected $loadedData;
    protected LoggerInterface $logger; // Dodano LoggerInterface

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        RequestInterface $request,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger, // Wstrzykujemy LoggerInterface
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        $this->request = $request;
        $this->storeManager = $storeManager;
        $this->logger = $logger; // Przypisujemy LoggerInterface
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    public function getData(): array
    {
        if (isset($this->loadedData)) {
            return $this->loadedData;
        }

        try {
            $galleryId = $this->request->getParam('id');
            $this->collection->clear();

            if ($galleryId) {
                $this->collection->getSelect()->join(
                    ['link' => $this->collection->getTable('gardenlawn_mediagallery_asset_link')],
                    'main_table.id = link.asset_id',
                    ['sort_order', 'enabled']
                )->where('link.gallery_id = ?', $galleryId);
            } else {
                // Jeśli nie ma ID galerii (np. nowa galeria), zwróć pustą kolekcję
                $this->collection->getSelect()->where('1=0');
            }

            $items = $this->collection->getItems();
            $this->loadedData = [];
            foreach ($items as $item) {
                $this->loadedData[$item->getId()]['images'][] = [
                    'file' => $item->getPath(),
                    'url' => $this->getMediaUrl($item->getPath()),
                    'position' => $item->getSortOrder(),
                    'is_main' => false,
                    'enabled' => (bool)$item->getEnabled(),
                ];
            }
            return $this->loadedData;
        } catch (\Exception $e) {
            $this->logger->critical(sprintf('MediaGallery AssetDataProvider: Error fetching assets for gallery ID %s: %s', $this->request->getParam('id'), $e->getMessage()), ['exception' => $e]);
            return []; // Zwróć pustą tablicę w przypadku błędu
        }
    }

    private function getMediaUrl($path): string
    {
        return $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . $path;
    }
}
