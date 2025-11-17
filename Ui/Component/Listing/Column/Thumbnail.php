<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Ui\Component\Listing\Column;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Magento\Framework\UrlInterface;
use GardenLawn\MediaGallery\Model\ResourceModel\AssetLink\CollectionFactory as AssetLinkCollectionFactory;
use Magento\Store\Model\StoreManagerInterface;

class Thumbnail extends Column
{
    /**
     * @var UrlInterface
     */
    protected UrlInterface $urlBuilder;

    /**
     * @var AssetLinkCollectionFactory
     */
    protected AssetLinkCollectionFactory $assetLinkCollectionFactory;

    /**
     * @var StoreManagerInterface
     */
    protected StoreManagerInterface $storeManager;

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $urlBuilder
     * @param AssetLinkCollectionFactory $assetLinkCollectionFactory
     * @param StoreManagerInterface $storeManager
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        UrlInterface $urlBuilder,
        AssetLinkCollectionFactory $assetLinkCollectionFactory,
        StoreManagerInterface $storeManager,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
        $this->urlBuilder = $urlBuilder;
        $this->assetLinkCollectionFactory = $assetLinkCollectionFactory;
        $this->storeManager = $storeManager;
    }

    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     * @return array
     * @throws NoSuchEntityException
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items'])) {
            $mediaUrl = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
            foreach ($dataSource['data']['items'] as & $item) {
                $galleryId = $item['id'];
                $assetLinkCollection = $this->assetLinkCollectionFactory->create();
                $assetLinkCollection->addFieldToFilter('gallery_id', $galleryId)
                    ->setOrder('sort_order', 'ASC')
                    ->setPageSize(1);

                $firstAsset = $assetLinkCollection->getFirstItem();
                $path = $firstAsset->getData('path');

                $fieldName = $this->getData('name');
                if ($path) {
                    $item[$fieldName . '_src'] = $mediaUrl . $path;
                    $item[$fieldName . '_alt'] = $item['name'];
                    $item[$fieldName . '_link'] = $this->urlBuilder->getUrl(
                        'gardenlawn_mediagallery/index/edit',
                        ['id' => $galleryId]
                    );
                    $item[$fieldName . '_orig_src'] = $mediaUrl . $path;
                } else {
                    // Provide a placeholder image if no asset is found
                    $item[$fieldName . '_src'] = $this->getViewFileUrl('Magento_Catalog/images/product/placeholder/thumbnail.jpg');
                    $item[$fieldName . '_alt'] = 'No image';
                }
            }
        }

        return $dataSource;
    }
}
