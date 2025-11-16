<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Ui\Component\Listing\Columns\Column;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory as UiComponentFactory;

class AssetThumbnail extends Column
{
    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        StoreManagerInterface $storeManager,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
        $this->storeManager = $storeManager;
    }

    /**
     * Prepare data source
     *
     * Adds keys required by Magento_Ui/js/grid/columns/thumbnail:
     *  - {name}_src
     *  - {name}_alt
     *  - {name}_orig_src
     *
     * Uses 'path' column from the data set to build full media URLs.
     */
    public function prepareDataSource(array $dataSource)
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        $fieldName = $this->getData('name') ?: 'thumbnail';
        $baseMediaUrl = rtrim($this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA), '/') . '/';

        foreach ($dataSource['data']['items'] as &$item) {
            $path = $item['path'] ?? '';
            if ($path === null) {
                $path = '';
            }
            $path = ltrim((string)$path, '/');

            if ($path !== '') {
                $url = $baseMediaUrl . $path;
                $alt = basename($path);
                $item[$fieldName . '_src'] = $url;
                $item[$fieldName . '_alt'] = $alt;
                $item[$fieldName . '_orig_src'] = $url;
            } else {
                // Fallback: no image available; leave fields empty to avoid broken images
                $item[$fieldName . '_src'] = '';
                $item[$fieldName . '_alt'] = '';
                $item[$fieldName . '_orig_src'] = '';
            }
        }

        return $dataSource;
    }
}
