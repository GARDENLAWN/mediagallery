<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Plugin\Catalog\Model\View\Asset;

use Magento\Catalog\Model\View\Asset\Image;

class ImageTransformationPlugin
{
    public function afterGetImageTransformationParameters(Image $subject, array $result): array
    {
        // Add 'format' parameter with value 'webp'
        $result['format'] = 'webp';
        return $result;
    }
}
