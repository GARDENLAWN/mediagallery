<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Model\ResourceModel\Gallery;

use Magento\Framework\ObjectManagerInterface;

/**
 * Factory class for @see Collection
 */
class CollectionFactory
{
    /**
     * @var ObjectManagerInterface
     */
    protected ObjectManagerInterface $_objectManager;

    /**
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(ObjectManagerInterface $objectManager)
    {
        $this->_objectManager = $objectManager;
    }

    /**
     * Create new collection
     *
     * @param array $data
     * @return Collection
     */
    public function create(array $data = []): Collection
    {
        return $this->_objectManager->create(Collection::class, $data);
    }
}
