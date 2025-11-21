<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Block\Adminhtml\Gallery;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use GardenLawn\MediaGallery\Model\ResourceModel\Gallery\CollectionFactory;
use Magento\Framework\Serialize\Serializer\Json;

class Tree extends Template
{
    protected CollectionFactory $galleryCollectionFactory;
    private Json $jsonSerializer;

    public function __construct(
        Context $context,
        CollectionFactory $galleryCollectionFactory,
        Json $jsonSerializer,
        array $data = []
    ) {
        $this->galleryCollectionFactory = $galleryCollectionFactory;
        $this->jsonSerializer = $jsonSerializer;
        parent::__construct($context, $data);
    }

    /**
     * Get the gallery tree structure as a JSON string for jsTree.
     *
     * @return string
     */
    public function getTreeJson(): string
    {
        $collection = $this->galleryCollectionFactory->create();
        $paths = $collection->getColumnValues('name');
        // CORRECTED: Use natural, case-insensitive sorting for a user-friendly tree view.
        sort($paths, SORT_NATURAL | SORT_FLAG_CASE);

        $nodes = [];
        $nodes['root'] = ['id' => 'root', 'parent' => '#', 'text' => __('All Galleries'), 'state' => ['opened' => true]];

        foreach ($paths as $path) {
            $parts = explode('/', $path);
            $currentPath = '';
            $parent = 'root';

            foreach ($parts as $part) {
                $oldPath = $currentPath;
                $currentPath .= (empty($currentPath) ? '' : '/') . $part;

                if (!isset($nodes[$currentPath])) {
                    $nodes[$currentPath] = [
                        'id' => $currentPath,
                        'parent' => empty($oldPath) ? 'root' : $oldPath,
                        'text' => $part
                    ];
                }
            }
        }

        return $this->jsonSerializer->serialize(array_values($nodes));
    }
}
