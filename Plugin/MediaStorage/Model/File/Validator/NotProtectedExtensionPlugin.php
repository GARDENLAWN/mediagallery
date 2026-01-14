<?php
declare(strict_types=1);

namespace GardenLawn\MediaGallery\Plugin\MediaStorage\Model\File\Validator;

use Magento\MediaStorage\Model\File\Validator\NotProtectedExtension;

class NotProtectedExtensionPlugin
{
    /**
     * Whitelist modern image formats if the original validation fails.
     *
     * @param NotProtectedExtension $subject
     * @param bool $result
     * @param string $filePath
     * @return bool
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterIsValid(NotProtectedExtension $subject, bool $result, string $filePath): bool
    {
        // If the file is already considered valid, do nothing.
        if ($result) {
            return true;
        }

        $whitelistedExtensions = ['webp', 'avif', 'svg'];
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        // If the extension is in our whitelist, consider it valid.
        if (in_array($extension, $whitelistedExtensions, true)) {
            return true;
        }

        // Otherwise, respect the original validation result.
        return $result;
    }
}
