<?php
namespace GardenLawn\MediaGallery\Ui\Component\Listing;

use Magento\Ui\DataProvider\AbstractDataProvider;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\RequestInterface;

class AssetDataProvider extends AbstractDataProvider
{
    private ResourceConnection $resource;
    private RequestInterface $request;

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param ResourceConnection $resource
     * @param RequestInterface $request
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        ResourceConnection $resource,
        RequestInterface $request,
        array $meta = [],
        array $data = []
    ) {
        $this->resource = $resource;
        $this->request = $request;
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    /**
     * Return data for UI listing in expected structure
     *
     * @return array
     */
    public function getData(): array
    {
        $connection = $this->resource->getConnection();
        $assetTable = $connection->getTableName('media_gallery_asset');

        $select = $connection->select()
            ->from($assetTable, ['id', 'path', 'title']);

        // Optional: if gallery_id provided, we still return all assets.
        // Keeping logic simple to avoid heavy joins that could fail on misconfig.
        // $galleryId = (int)($this->request->getParam('gallery_id') ?? 0);

        $items = $connection->fetchAll($select);

        return [
            'totalRecords' => count($items),
            'items' => array_values($items)
        ];
    }
}
