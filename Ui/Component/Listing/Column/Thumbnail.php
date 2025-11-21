<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Ui\Component\Listing\Column;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

class Thumbnail extends Column
{
    private StoreManagerInterface $storeManager;

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

    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items'])) {
            $fieldName = $this->getData('name');
            $mediaUrl = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
            foreach ($dataSource['data']['items'] as & $item) {
                if (isset($item['path'])) {
                    $item[$fieldName . '_src'] = $mediaUrl . $item['path'];
                    $item[$fieldName . '_alt'] = $item['title'] ?? 'Thumbnail';
                    $item[$fieldName . '_link'] = '#'; // No link for now
                    $item[$fieldName . '_orig_src'] = $mediaUrl . $item['path'];
                }
            }
        }

        return $dataSource;
    }
}
