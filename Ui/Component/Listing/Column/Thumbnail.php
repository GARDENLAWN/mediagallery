<?php
namespace GardenLawn\MediaGallery\Ui\Component\Listing\Column;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Magento\Store\Model\StoreManagerInterface;

class Thumbnail extends Column
{
    protected $storeManager;

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        StoreManagerInterface $storeManager,
        array $components = [],
        array $data = []
    ) {
        $this->storeManager = $storeManager;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            $fieldName = $this->getData('name');
            foreach ($dataSource['data']['items'] as & $item) {
                $mediaUrl = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
                $item[$fieldName . '_src'] = $mediaUrl . $item['path'];
                $item[$fieldName . '_alt'] = $item['title'];
                $item[$fieldName . '_link'] = $mediaUrl . $item['path'];
                $item[$fieldName . '_orig_src'] = $mediaUrl . $item['path'];
            }
        }

        return $dataSource;
    }
}
