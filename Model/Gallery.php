<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use GardenLawn\MediaGallery\Api\Data\GalleryInterface;

class Gallery extends AbstractModel implements GalleryInterface
{
    /**
     * @inheritDoc
     * @throws LocalizedException
     */
    protected function _construct(): void
    {
        $this->_init(ResourceModel\Gallery::class);
    }

    /**
     * @inheritDoc
     */
    public function getId(): ?int
    {
        $id = $this->getData(self::ID);
        return $id !== null ? (int)$id : null;
    }

    /**
     * @inheritDoc
     */
    public function setId($id): GalleryInterface
    {
        return $this->setData(self::ID, $id);
    }

    /**
     * @inheritDoc
     */
    public function getPath(): ?string
    {
        return $this->getData(self::PATH);
    }

    /**
     * @inheritDoc
     */
    public function setPath(string $path): GalleryInterface
    {
        return $this->setData(self::PATH, $path);
    }

    /**
     * @inheritDoc
     */
    public function getSortOrder(): int
    {
        return (int)$this->getData(self::SORT_ORDER);
    }

    /**
     * @inheritDoc
     */
    public function setSortOrder(int $sortOrder): GalleryInterface
    {
        return $this->setData(self::SORT_ORDER, $sortOrder);
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
