<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Block\Adminhtml\AssetLink\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class DeleteButton extends GenericButton implements ButtonProviderInterface
{
    /**
     * Get button data
     *
     * @return array
     */
    public function getButtonData(): array
    {
        $data = [];
        if ($this->getAssetLinkId()) {
            $galleryId = $this->registry->registry('gardenlawn_mediagallery_asset_link')->getGalleryId();
            $data = [
                'label' => __('Delete Asset Link'),
                'class' => 'delete',
                'on_click' => 'deleteConfirm(\'' . __(
                    'Are you sure you want to do this?'
                ) . '\', \'' . $this->getUrl(
                    'gardenlawn_mediagallery_assetlink/assetlink/delete',
                    ['id' => $this->getAssetLinkId(), 'gallery_id' => $galleryId]
                ) . '\')',
                'sort_order' => 20,
            ];
        }
        return $data;
    }
}
