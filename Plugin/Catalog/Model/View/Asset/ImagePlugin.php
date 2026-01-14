<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Plugin\Catalog\Model\View\Asset;

use Magento\Catalog\Model\View\Asset\Image;
use Magento\Framework\App\State;
use Magento\Framework\App\Area;

class ImagePlugin
{
    /**
     * @var State
     */
    private $appState;

    /**
     * @param State $appState
     */
    public function __construct(State $appState)
    {
        $this->appState = $appState;
    }

    public function afterGetUrl(Image $subject, string $result): string
    {
        if ($this->appState->getAreaCode() !== Area::AREA_FRONTEND) {
            return $result;
        }

        // Check if the original URL is for a JPG, JPEG, or PNG image
        if (preg_match('/\.(jpg|jpeg|png)$/i', $result)) {
            // Replace the extension with .webp
            return preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $result);
        }
        return $result;
    }
}
