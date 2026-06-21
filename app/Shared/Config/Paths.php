<?php

namespace Notifal\Shared\Config;

defined('ABSPATH') || exit;

/**
 * Class Paths
 *
 * Provides centralized path and URL management for shared assets in the Notifal plugin.
 * This class handles filesystem paths and URLs for shared resources like images, fonts,
 * and build assets (CSS/JS) to ensure consistency across all modules.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class Paths
{
    /**
     * Get base filesystem path for shared assets.
     *
     * @return string The absolute filesystem path to the shared directory
     * @since 2.0.0
     */
    public static function basePath(): string
    {
        return NOTIFAL_APP_PATH . 'Shared/';
    }

    /**
     * Get base URL for shared assets.
     *
     * @return string The absolute URL to the shared directory
     * @since 2.0.0
     */
    public static function baseUrl(): string
    {
        return NOTIFAL_APP_URL . 'Shared/';
    }

    /**
     * Get filesystem path to shared images directory.
     *
     * @return string The absolute filesystem path to the images directory
     * @since 2.0.0
     */
    public static function imagesPath(): string
    {
        return static::basePath() . 'Resources/Assets/images/';
    }

    /**
     * Get URL to shared images directory.
     *
     * @return string The absolute URL to the images directory
     * @since 2.0.0
     */
    public static function imagesUrl(): string
    {
        return static::baseUrl() . 'Resources/Assets/images/';
    }

    /**
     * Get filesystem path to shared fonts directory.
     *
     * @return string The absolute filesystem path to the fonts directory
     * @since 2.0.0
     */
    public static function fontsPath(): string
    {
        return static::basePath() . 'Resources/Assets/fonts/';
    }

    /**
     * Get URL to shared fonts directory.
     *
     * @return string The absolute URL to the fonts directory
     * @since 2.0.0
     */
    public static function fontsUrl(): string
    {
        return static::baseUrl() . 'Resources/Assets/fonts/';
    }

    /**
     * Get URL to admin CSS assets directory.
     *
     * @return string The absolute URL to the admin CSS assets directory
     * @since 2.0.0
     */
    public static function cssAdminAssetsUrl(): string
    {
        return static::baseUrl() . 'Resources/Assets/css/admin/';
    }

    /**
     * Get a build URL for the specified asset type and environment.
     *
     * This method consolidates the repetitive build URL logic into a single,
     * maintainable method while ensuring type safety and consistency.
     *
     * @param string $assetType The asset type ('css' or 'js')
     * @param string $environment The environment ('admin' or 'frontend')
     * @return string The absolute URL to the build directory
     * @since 2.0.0
     */
    private static function getBuildUrl(string $assetType, string $environment): string
    {
        // Validate parameters to prevent invalid combinations
        $validAssetTypes = ['css', 'js'];
        $validEnvironments = ['admin', 'frontend'];

        if (!in_array($assetType, $validAssetTypes, true)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid asset type "%s". Must be one of: %s', $assetType, implode(', ', $validAssetTypes))
            );
        }

        if (!in_array($environment, $validEnvironments, true)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid environment "%s". Must be one of: %s', $environment, implode(', ', $validEnvironments))
            );
        }

        return NOTIFAL_URL . "public/build/{$assetType}/{$environment}/";
    }

    /**
     * Get URL to admin CSS build directory.
     *
     * @return string The absolute URL to the admin CSS build directory
     * @since 2.0.0
     */
    public static function cssAdminBuildUrl(): string
    {
        return static::getBuildUrl('css', 'admin');
    }

    /**
     * Get URL to admin JS build directory.
     *
     * @return string The absolute URL to the admin JS build directory
     * @since 2.0.0
     */
    public static function jsAdminBuildUrl(): string
    {
        return static::getBuildUrl('js', 'admin');
    }

    /**
     * Get URL to frontend CSS build directory.
     *
     * @return string The absolute URL to the frontend CSS build directory
     * @since 2.0.0
     */
    public static function cssFrontendBuildUrl(): string
    {
        return static::getBuildUrl('css', 'frontend');
    }

    /**
     * Get URL to frontend JS build directory.
     *
     * @return string The absolute URL to the frontend JS build directory
     * @since 2.0.0
     */
    public static function jsFrontendBuildUrl(): string
    {
        return static::getBuildUrl('js', 'frontend');
    }

    /**
     * Get URL to shared static images copied into the Vite build output.
     *
     * @return string The absolute URL to the build images directory
     * @since 2.4.0
     */
    public static function buildImagesUrl(): string
    {
        return NOTIFAL_URL . 'public/build/Assets/images/';
    }
}
