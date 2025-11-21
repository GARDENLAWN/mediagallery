<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Ui\Component\DataProvider;

use Magento\Ui\DataProvider\AbstractDataProvider;
use GardenLawn\MediaGallery\Model\ResourceModel\AssetLink\CollectionFactory;
use Magento\Framework\App\RequestInterface;

class AssetLink extends AbstractDataProvider
{
    protected $collection;
    private RequestInterface $request;
    private bool $isCollectionPrepared = false;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        RequestInterface $request,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        $this->request = $request;
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    public function getData(): array
    {
        if ($this->isCollectionPrepared) {
            return $this->getCollection()->toArray();
        }

        $galleryId = $this->request->getParam('gallery_id');

        if (!$galleryId) {
            // If no gallery ID is provided, return an empty result.
            return [
                'totalRecords' => 0,
                'items' => [],
            ];
        }

        $this->getCollection()
            ->addFieldToFilter('main_table.gallery_id', $galleryId)
            ->join(
                ['mga' => $this->getCollection()->getTable('media_gallery_asset')],
                'main_table.asset_id = mga.id',
                ['path', 'title']
            );

        $this->isCollectionPrepared = true;
        return $this->getCollection()->toArray();
    }
}
