<?php

namespace Notifal\Infrastructure\WordPress\Media;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;

defined('ABSPATH') || exit;

/**
 * Class ImageSizeService
 *
 * Provides WordPress image size options for UI dropdowns.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class ImageSizeService
{
    /**
     * Get registered image sizes formatted for dropdown options.
     *
     * @return array
     * @since 2.0.0
     */
    public static function getDropdownOptions(): array
    {
        $sizes = wp_get_registered_image_subsizes();
        $options = [];

        foreach ($sizes as $key => $size) {
            $width = $size['width'];
            $height = $size['height'] ?: 'auto';
            $options[$key] = sprintf('%s - %s x %s', ucfirst($key), $width, $height);
        }

        $options['full'] = __('Full (original size)', 'notifal');

        return apply_filters(FilterHooks::IMAGE_SIZE_DROPDOWN_OPTIONS, $options);
    }

    /**
     * Get all available image size keys including 'full'.
     *
     * @return array
     * @since 2.0.0
     */
    public static function getSizeKeys(): array
    {
        $sizes = wp_get_registered_image_subsizes();
        $keys = array_keys($sizes);
        $keys[] = 'full';

        return $keys;
    }
}
