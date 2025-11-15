<?php
namespace GardenLawn\MediaGallery\Model;

use Magento\Framework\ObjectManagerInterface;

/**
 * Factory class for @see Gallery
 */
class GalleryFactory
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
     * Create new gallery model
     *
     * @param array $data
     * @return Gallery
     */
    public function create(array $data = []): Gallery
    {
        return $this->_objectManager->create(Gallery::class, $data);
    }
}
