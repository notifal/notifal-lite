<?php

namespace Notifal\Shared\Services;

use Notifal\Shared\Config\Paths;

defined('ABSPATH') || exit;

/**
 * Class IconService
 *
 * Handles SVG icon rendering and management for the Notifal plugin.
 * Provides centralized icon handling with caching and optimization.
 *
 * @since 2.0.0
 * @package Notifal\Shared\Services
 * @author Hossein <hossein@notifal.com>
 */
class IconService
{
    /**
     * Cache for loaded SVG content to avoid repeated file reads.
     *
     * @var array<string, string>
     * @since 2.0.0
     */
    private static array $svgCache = [];

    /**
     * Get the URL to the Notifal icon.
     *
     * @return string
     * @since 2.0.0
     */
    public static function getIconUrl(): string
    {
        return Paths::imagesUrl() . 'notifal-icon.svg';
    }

    /**
     * Get the filesystem path to the Notifal icon.
     *
     * @return string
     * @since 2.0.0
     */
    public static function getIconPath(): string
    {
        return Paths::imagesPath() . 'notifal-icon.svg';
    }

    /**
     * Render the Notifal icon as inline SVG.
     *
     * @param array $attributes Optional attributes for the SVG element.
     * @return string
     * @since 2.0.0
     */
    public static function renderIcon(array $attributes = []): string
    {
        $svgContent = self::getSvgContent('notifal-icon.svg');
        
        if (empty($svgContent)) {
            return '';
        }

        // Merge default attributes with provided ones
        $defaultAttributes = [
            'class' => 'notifal-icon',
            'aria-hidden' => 'true',
            'role' => 'img',
        ];
        
        $attributes = array_merge($defaultAttributes, $attributes);
        
        // Build attributes string
        $attrString = '';
        foreach ($attributes as $key => $value) {
            $attrString .= ' ' . esc_attr($key) . '="' . esc_attr($value) . '"';
        }

        // Return SVG with attributes
        return sprintf('<svg%s>%s</svg>', $attrString, $svgContent);
    }

    /**
     * Get SVG content from file with caching.
     *
     * @param string $filename The SVG filename.
     * @return string
     * @since 2.0.0
     */
    public static function getSvgContent(string $filename): string
    {
        // Check cache first
        if (isset(self::$svgCache[$filename])) {
            return self::$svgCache[$filename];
        }

        $filePath = Paths::imagesPath() . $filename;
        
        if (!file_exists($filePath)) {
            return '';
        }

        // Read and cache SVG content
        $content = file_get_contents($filePath);
        
        if ($content === false) {
            return '';
        }

        // Extract content between <svg> tags (remove outer svg element)
        $content = preg_replace('/<svg[^>]*>(.*)<\/svg>/s', '$1', $content);
        
        self::$svgCache[$filename] = $content;
        
        return $content;
    }

    /**
     * Get base64 encoded SVG for use in CSS or data URIs.
     *
     * @param array $attributes Optional attributes for the SVG element.
     * @return string
     * @since 2.0.0
     */
    public static function getBase64Icon(array $attributes = []): string
    {
        $svgContent = self::getSvgContent('notifal-icon.svg');
        
        if (empty($svgContent)) {
            return '';
        }

        // Build complete SVG with attributes
        $defaultAttributes = [
            'xmlns' => 'http://www.w3.org/2000/svg',
            'viewBox' => '0 0 24 24',
        ];
        
        $attributes = array_merge($defaultAttributes, $attributes);
        
        $attrString = '';
        foreach ($attributes as $key => $value) {
            $attrString .= ' ' . esc_attr($key) . '="' . esc_attr($value) . '"';
        }

        $fullSvg = sprintf('<svg%s>%s</svg>', $attrString, $svgContent);
        
        return 'data:image/svg+xml;base64,' . base64_encode($fullSvg);
    }

    /**
     * Clear the SVG cache.
     *
     * @return void
     * @since 2.0.0
     */
    public static function clearCache(): void
    {
        self::$svgCache = [];
    }
} 
