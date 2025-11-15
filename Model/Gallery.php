<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Model;

use Magento\Framework\Model\AbstractModel;
use GardenLawn\MediaGallery\Api\Data\GalleryInterface;

class Gallery extends AbstractModel implements GalleryInterface
{
    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(\GardenLawn\MediaGallery\Model\ResourceModel\Gallery::class);
    }

    /**
     * @inheritDoc
     */
    public function getId()
    {
        return $this->getData(self::ID);
    }

    /**
     * @inheritDoc
     */
    public function setId($id)
    {
        return $this->setData(self::ID, $id);
    }

    /**
     * @inheritDoc
     */
    public function getName(): ?string
    {
        return $this->getData(self::NAME);
    }

    /**
     * @inheritDoc
     */
    public function setName(string $name): GalleryInterface
    {
        return $this->setData(self::NAME, $name);
    }

    /**
     * @inheritDoc
     */
    public function getSortOrder(): int
    {
        return (int)$this->getData(self::SORTORDER);
    }

    /**
     * @inheritDoc
     */
    public function setSortOrder(int $sortOrder): GalleryInterface
    {
        return $this->setData(self::SORTORDER, $sortOrder);
    }

    /**
     * @inheritDoc
     */
    public function isEnabled(): bool
    {
        return (bool)$this->getData(self::ENABLED);
    }

    /**
     * @inheritDoc
     */
    public function setEnabled(bool $enabled): GalleryInterface
    {
        return $this->setData(self::ENABLED, $enabled);
    }
}
