<?php

namespace Notifal\Modules\Templates\Application\Services;

use Notifal\Domain\Products\DTO\ProductDTO;
use Notifal\Infrastructure\WordPress\Media\ImageSizeService;

defined('ABSPATH') || exit;

/**
 * Class ImagePreviewService
 *
 * Handles image data retrieval and formatting for API responses.
 * Provides standardized image data structure for editor previews.
 *
 * @package Notifal\Modules\Templates\Application\Services
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class ImagePreviewService
{
    /**
     * Get image data for a thumbnail ID.
     *
     * @param int $thumbnailId The attachment ID
     * @return array Image data with sizes and metadata
     * @since 2.0.0
     */
    public static function getImageData(int $thumbnailId): array
    {
        if (!$thumbnailId) {
            return [];
        }

        $sizes = ImageSizeService::getSizeKeys();
        $imageData = [];

        foreach ($sizes as $size) {
            $imageInfo = wp_get_attachment_image_src($thumbnailId, $size);
            if ($imageInfo) {
                $imageData[$size] = [
                    'url'    => $imageInfo[0],
                    'width'  => $imageInfo[1],
                    'height' => $imageInfo[2]
                ];
            }
        }

        return $imageData;
    }

    /**
     * Get alt text for an image.
     *
     * @param int $thumbnailId The attachment ID
     * @param string $fallbackText Fallback text if no alt text found
     * @return string Alt text
     * @since 2.0.0
     */
    public static function getAltText(int $thumbnailId, string $fallbackText = ''): string
    {
        if ($thumbnailId) {
            $altText = get_post_meta($thumbnailId, '_wp_attachment_image_alt', true);
            if (!empty($altText)) {
                return $altText;
            }
        }

        return $fallbackText;
    }

    /**
     * Create standardized success response.
     *
     * @param array $data Response data
     * @return array Standardized success response
     * @since 2.0.0
     */
    public static function createSuccessResponse(array $data): array
    {
        return [
            'success' => true,
            'data'    => $data
        ];
    }

    /**
     * Create standardized error response.
     *
     * @param string $message Error message
     * @param array $additionalData Additional data to include
     * @return array Standardized error response
     * @since 2.0.0
     */
    public static function createErrorResponse(string $message, array $additionalData = []): array
    {
        return array_merge([
            'success' => false,
            'message' => $message,
            'data'    => null
        ], $additionalData);
    }
}