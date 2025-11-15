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
        $linkTable = $connection->getTableName('gardenlawn_mediagallery_asset_link');

        $params = $this->request->getParams();

        // Resolve gallery id from params exported by UI component
        $galleryId = null;
        if (isset($params['params']['gallery_id'])) {
            $galleryId = (int)$params['params']['gallery_id'];
        } elseif (isset($params['gallery_id'])) {
            $galleryId = (int)$params['gallery_id'];
        } else {
            $galleryId = (int)$this->request->getParam('gallery_id', 0);
        }

        // If no gallery specified (e.g. new entity), return empty set by design
        if ($galleryId <= 0) {
            return [
                'totalRecords' => 0,
                'items' => []
            ];
        }

        // Paging defaults
        $current = (int)($params['paging']['current'] ?? 1);
        $pageSize = (int)($params['paging']['pageSize'] ?? 20);
        if ($current < 1) { $current = 1; }
        if ($pageSize < 1 || $pageSize > 200) { $pageSize = 20; }

        // Sorting defaults
        $sorting = $params['sorting'] ?? [];
        $sortField = 'id';
        $sortDir = 'ASC';
        if (is_array($sorting) && !empty($sorting)) {
            $firstKey = array_key_first($sorting);
            if (in_array($firstKey, ['id', 'path', 'title'], true)) {
                $sortField = $firstKey;
                $dir = strtoupper((string)$sorting[$firstKey]);
                $sortDir = $dir === 'DESC' ? 'DESC' : 'ASC';
            }
        }

        // Build filters
        $filters = $params['filters'] ?? [];
        $bind = [];

        $buildFilter = function($select) use ($filters, &$bind, $assetTable) {
            if (!is_array($filters)) {
                return $select;
            }
            // ID filter: exact or range
            if (isset($filters['id'])) {
                $idFilter = $filters['id'];
                if (is_array($idFilter)) {
                    if (isset($idFilter['from']) && $idFilter['from'] !== '') {
                        $bind['id_from'] = (int)$idFilter['from'];
                        $select->where("{$assetTable}.id >= :id_from");
                    }
                    if (isset($idFilter['to']) && $idFilter['to'] !== '') {
                        $bind['id_to'] = (int)$idFilter['to'];
                        $select->where("{$assetTable}.id <= :id_to");
                    }
                } elseif ($idFilter !== '') {
                    $bind['id_eq'] = (int)$idFilter;
                    $select->where("{$assetTable}.id = :id_eq");
                }
            }

            // Text filters with LIKE
            foreach (['path', 'title'] as $textField) {
                if (!empty($filters[$textField]) && is_string($filters[$textField])) {
                    $param = $textField . '_like';
                    $bind[$param] = '%' . $filters[$textField] . '%';
                    $select->where("{$assetTable}.{$textField} LIKE :{$param}");
                }
            }

            return $select;
        };

        // Helper: apply gallery link filter using EXISTS to avoid heavy joins
        $applyGalleryFilter = function($select) use ($assetTable, $linkTable, &$bind, $galleryId) {
            $bind['gallery_id'] = (int)$galleryId;
            $select->where(
                "EXISTS (SELECT 1 FROM {$linkTable} AS galink WHERE galink.asset_id = {$assetTable}.id AND galink.gallery_id = :gallery_id)"
            );
            return $select;
        };

        // Total count with filters and gallery constraint
        $countSelect = $connection->select()
            ->from($assetTable, new \Zend_Db_Expr('COUNT(DISTINCT id)'));
        $countSelect = $buildFilter($countSelect);
        $countSelect = $applyGalleryFilter($countSelect);
        $totalRecords = (int)$connection->fetchOne($countSelect, $bind);

        // Main select with filters and gallery constraint
        $select = $connection->select()
            ->from($assetTable, ['id', 'path', 'title'])
            ->order(sprintf('%s %s', $sortField, $sortDir))
            ->limit($pageSize, ($current - 1) * $pageSize);
        $select = $buildFilter($select);
        $select = $applyGalleryFilter($select);

        $items = $connection->fetchAll($select, $bind);

        return [
            'totalRecords' => $totalRecords,
            'items' => array_values($items)
        ];
    }
}
