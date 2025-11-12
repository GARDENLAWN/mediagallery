<?php

namespace GardenLawn\MediaGallery\Model;

use GardenLawn\MediaGallery\Api\Data\GridInterface;
use Magento\Framework\Model\AbstractModel;

class Grid extends AbstractModel implements GridInterface
{
    /**
     * CMS page cache tag.
     */
    const string CACHE_TAG = 'gardenlawn_mediagallery';

    /**
     * @var string
     */
    protected $_cacheTag = 'gardenlawn_mediagallery';

    /**
     * Prefix of model events names.
     *
     * @var string
     */
    protected $_eventPrefix = 'gardenlawn_mediagallery';

    /**
     * Initialize resource model.
     */
    protected function _construct(): void
    {
        $this->_init('GardenLawn\MediaGallery\Model\ResourceModel\Grid');
    }

    public function getMediaGalleryId(): ?int
    {
        return $this->getData(self::id);
    }

    public function setMediaGalleryId($mediagalleryId): Grid
    {
        return $this->setData(self::id, $mediagalleryId);
    }

    public function getName(): ?string
    {
        return $this->getData(self::name);
    }

    public function setName($name): Grid
    {
        return $this->setData(self::name, $name);
    }

    public function getSortOrder(): ?int
    {
        return $this->getData(self::sortorder);
    }

    public function setSortOrder($sortorder): Grid
    {
        return $this->setData(self::sortorder, $sortorder);
    }

    public function getEnabled(): ?bool
    {
        return $this->getData(self::enabled);
    }

    public function setEnabled($enabled): Grid
    {
        return $this->setData(self::enabled, $enabled);
    }
}
