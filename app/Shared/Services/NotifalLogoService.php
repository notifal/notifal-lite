<?php

namespace Notifal\Shared\Services;

defined('ABSPATH') || exit;

/**
 * Notifal Logo Service
 *
 * Provides centralized access to Notifal branding elements including logos
 * that can be used across different parts of the plugin.
 *
 * @since 2.0.0
 * @package Notifal\Shared\Services
 * @author Hossein <hossein@notifal.com>
 */
class NotifalLogoService
{
    /**
     * Get the main Notifal logo SVG
     *
     * Returns the primary Notifal logo as an SVG string that can be used
     * in admin interfaces, menus, and other UI components.
     *
     * @param array $attributes Optional attributes for the SVG element
     * @return string SVG markup
     * @since 2.0.0
     */
    public static function getMainLogo(array $attributes = []): string
    {
        $defaultAttributes = [
            'xmlns' => 'http://www.w3.org/2000/svg',
            'width' => '69',
            'height' => '71',
            'viewBox' => '0 0 69 71',
            'fill' => 'none',
            'class' => 'notifal-logo-main',
        ];

        $attributes = array_merge($defaultAttributes, $attributes);

        $attrString = '';
        foreach ($attributes as $key => $value) {
            $attrString .= ' ' . esc_attr($key) . '="' . esc_attr($value) . '"';
        }

        return '<svg' . $attrString . '><mask id="path-1-outside-1_4461_18" maskUnits="userSpaceOnUse" x="0" y="0" width="69" height="71" fill="black"><rect fill="white" width="69" height="71"/><path d="M1 17C1 8.16344 8.16344 1 17 1H52C60.8366 1 68 8.16344 68 17V52C68 60.8366 60.8366 68 52 68H17C8.16344 68 1 60.8366 1 52V17Z"/></mask><path d="M1 17C1 8.16344 8.16344 1 17 1H52C60.8366 1 68 8.16344 68 17V52C68 60.8366 60.8366 68 52 68H17C8.16344 68 1 60.8366 1 52V17Z" fill="url(#paint0_linear_4461_18)"/><path d="M0 17C0 7.61116 7.61116 0 17 0H52C61.3888 0 69 7.61116 69 17H67C67 8.71573 60.2843 2 52 2H17C8.71573 2 2 8.71573 2 17H0ZM69 54C69 63.3888 61.3888 71 52 71H17C7.61116 71 0 63.3888 0 54L2 52C2 59.1797 8.71573 65 17 65H52C60.2843 65 67 59.1797 67 52L69 54ZM17 71C7.61116 71 0 63.3888 0 54V17C0 7.61116 7.61116 0 17 0V2C8.71573 2 2 8.71573 2 17V52C2 59.1797 8.71573 65 17 65V71ZM52 0C61.3888 0 69 7.61116 69 17V54C69 63.3888 61.3888 71 52 71V65C60.2843 65 67 59.1797 67 52V17C67 8.71573 60.2843 2 52 2V0Z" fill="#F8EBFF" mask="url(#path-1-outside-1_4461_18)"/><g clip-path="url(#clip0_4461_18)"><path d="M31.0752 48.4858H37.9249C38.1575 48.4906 38.3794 48.5896 38.544 48.762C38.7086 48.9345 38.803 49.167 38.8075 49.4108C38.8075 50.3627 38.4467 51.2756 37.8044 51.9486C37.1622 52.6217 36.291 52.9998 35.3827 52.9998H33.6173C32.709 52.9998 31.8379 52.6217 31.1956 51.9486C30.5533 51.2756 30.1925 50.3627 30.1925 49.4108C30.197 49.167 30.2915 48.9345 30.456 48.762C30.6206 48.5896 30.8425 48.4906 31.0752 48.4858Z" fill="#F8EBFF"/><path d="M50 43.232V47.265C50 47.5888 49.8772 47.8994 49.6587 48.1284C49.4402 48.3574 49.1439 48.486 48.8349 48.486C46.6902 48.491 44.6308 47.6067 43.1061 46.0262C41.5813 44.4457 40.7151 42.2973 40.6965 40.05V28.1545C40.6965 27.4111 40.5567 26.675 40.2853 25.9881C40.0138 25.3013 39.6159 24.6772 39.1142 24.1516C38.6126 23.6259 38.0171 23.2089 37.3617 22.9244C36.7063 22.6399 36.0038 22.4935 35.2944 22.4935H33.7056C32.2729 22.4935 30.8988 23.0899 29.8858 24.1516C28.8727 25.2132 28.3035 26.6531 28.3035 28.1545V40.05C28.3035 41.1691 28.0929 42.2772 27.6837 43.3109C27.2745 44.3446 26.6747 45.2836 25.9188 46.074C25.1628 46.8645 24.2655 47.491 23.2782 47.9175C22.2909 48.3441 21.2331 48.5624 20.1651 48.56C19.8561 48.56 19.5598 48.4314 19.3413 48.2024C19.1228 47.9734 19 47.6628 19 47.339V43.232C19 42.9064 19.1222 42.5939 19.3402 42.362C19.5582 42.1301 19.8545 41.9974 20.1651 41.9925C20.6726 42.0075 21.1651 41.8117 21.5355 41.4479C21.9058 41.0841 22.1239 40.5816 22.1424 40.05V27.9695C22.1423 24.8046 23.3384 21.7685 25.469 19.5253C27.5996 17.2822 30.4913 16.0147 33.5114 16H35.4886C38.5087 16.0147 41.4004 17.2822 43.531 19.5253C45.6616 21.7685 46.8577 24.8046 46.8576 27.9695V40.05C46.8576 40.5848 47.0604 41.0977 47.4212 41.4759C47.7821 41.854 48.2715 42.0665 48.7819 42.0665C49.0898 42.0563 49.3893 42.1724 49.6168 42.39C49.8443 42.6077 49.9818 42.9098 50 43.232Z" fill="#F8EBFF"/></g><defs><linearGradient id="paint0_linear_4461_18" x1="37.7485" y1="-8.57143" x2="37.7485" y2="76.8878" gradientUnits="userSpaceOnUse"><stop stop-color="#5A189A"/><stop offset="1" stop-color="#7B2CBF"/></linearGradient><clipPath id="clip0_4461_18"><rect width="31" height="37" fill="white" transform="translate(19 16)"/></clipPath></defs></svg>';
    }

    /**
     * Get a compact version of the logo for smaller spaces
     *
     * @param array $attributes Optional attributes for the SVG element
     * @return string SVG markup
     * @since 2.0.0
     */
    public static function getCompactLogo(array $attributes = []): string
    {
        $defaultAttributes = [
            'xmlns' => 'http://www.w3.org/2000/svg',
            'width' => '40',
            'height' => '41',
            'viewBox' => '0 0 69 71',
            'fill' => 'none',
            'class' => 'notifal-logo-compact',
        ];

        $attributes = array_merge($defaultAttributes, $attributes);

        $attrString = '';
        foreach ($attributes as $key => $value) {
            $attrString .= ' ' . esc_attr($key) . '="' . esc_attr($value) . '"';
        }

        return '<svg' . $attrString . '><mask id="path-1-outside-1_4461_18" maskUnits="userSpaceOnUse" x="0" y="0" width="69" height="71" fill="black"><rect fill="white" width="69" height="71"/><path d="M1 17C1 8.16344 8.16344 1 17 1H52C60.8366 1 68 8.16344 68 17V52C68 60.8366 60.8366 68 52 68H17C8.16344 68 1 60.8366 1 52V17Z"/></mask><path d="M1 17C1 8.16344 8.16344 1 17 1H52C60.8366 1 68 8.16344 68 17V52C68 60.8366 60.8366 68 52 68H17C8.16344 68 1 60.8366 1 52V17Z" fill="url(#paint0_linear_4461_18)"/><path d="M0 17C0 7.61116 7.61116 0 17 0H52C61.3888 0 69 7.61116 69 17H67C67 8.71573 60.2843 2 52 2H17C8.71573 2 2 8.71573 2 17H0ZM69 54C69 63.3888 61.3888 71 52 71H17C7.61116 71 0 63.3888 0 54L2 52C2 59.1797 8.71573 65 17 65H52C60.2843 65 67 59.1797 67 52L69 54ZM17 71C7.61116 71 0 63.3888 0 54V17C0 7.61116 7.61116 0 17 0V2C8.71573 2 2 8.71573 2 17V52C2 59.1797 8.71573 65 17 65V71ZM52 0C61.3888 0 69 7.61116 69 17V54C69 63.3888 61.3888 71 52 71V65C60.2843 65 67 59.1797 67 52V17C67 8.71573 60.2843 2 52 2V0Z" fill="#F8EBFF" mask="url(#path-1-outside-1_4461_18)"/><g clip-path="url(#clip0_4461_18)"><path d="M31.0752 48.4858H37.9249C38.1575 48.4906 38.3794 48.5896 38.544 48.762C38.7086 48.9345 38.803 49.167 38.8075 49.4108C38.8075 50.3627 38.4467 51.2756 37.8044 51.9486C37.1622 52.6217 36.291 52.9998 35.3827 52.9998H33.6173C32.709 52.9998 31.8379 52.6217 31.1956 51.9486C30.5533 51.2756 30.1925 50.3627 30.1925 49.4108C30.197 49.167 30.2915 48.9345 30.456 48.762C30.6206 48.5896 30.8425 48.4906 31.0752 48.4858Z" fill="#F8EBFF"/><path d="M50 43.232V47.265C50 47.5888 49.8772 47.8994 49.6587 48.1284C49.4402 48.3574 49.1439 48.486 48.8349 48.486C46.6902 48.491 44.6308 47.6067 43.1061 46.0262C41.5813 44.4457 40.7151 42.2973 40.6965 40.05V28.1545C40.6965 27.4111 40.5567 26.675 40.2853 25.9881C40.0138 25.3013 39.6159 24.6772 39.1142 24.1516C38.6126 23.6259 38.0171 23.2089 37.3617 22.9244C36.7063 22.6399 36.0038 22.4935 35.2944 22.4935H33.7056C32.2729 22.4935 30.8988 23.0899 29.8858 24.1516C28.8727 25.2132 28.3035 26.6531 28.3035 28.1545V40.05C28.3035 41.1691 28.0929 42.2772 27.6837 43.3109C27.2745 44.3446 26.6747 45.2836 25.9188 46.074C25.1628 46.8645 24.2655 47.491 23.2782 47.9175C22.2909 48.3441 21.2331 48.5624 20.1651 48.56C19.8561 48.56 19.5598 48.4314 19.3413 48.2024C19.1228 47.9734 19 47.6628 19 47.339V43.232C19 42.9064 19.1222 42.5939 19.3402 42.362C19.5582 42.1301 19.8545 41.9974 20.1651 41.9925C20.6726 42.0075 21.1651 41.8117 21.5355 41.4479C21.9058 41.0841 22.1239 40.5816 22.1424 40.05V27.9695C22.1423 24.8046 23.3384 21.7685 25.469 19.5253C27.5996 17.2822 30.4913 16.0147 33.5114 16H35.4886C38.5087 16.0147 41.4004 17.2822 43.531 19.5253C45.6616 21.7685 46.8577 24.8046 46.8576 27.9695V40.05C46.8576 40.5848 47.0604 41.0977 47.4212 41.4759C47.7821 41.854 48.2715 42.0665 48.7819 42.0665C49.0898 42.0563 49.3893 42.1724 49.6168 42.39C49.8443 42.6077 49.9818 42.9098 50 43.232Z" fill="#F8EBFF"/></g><defs><linearGradient id="paint0_linear_4461_18" x1="37.7485" y1="-8.57143" x2="37.7485" y2="76.8878" gradientUnits="userSpaceOnUse"><stop stop-color="#5A189A"/><stop offset="1" stop-color="#7B2CBF"/></linearGradient><clipPath id="clip0_4461_18"><rect width="31" height="37" fill="white" transform="translate(19 16)"/></clipPath></defs></svg>';
    }

    /**
     * Echo the main logo directly to output
     *
     * @param array $attributes Optional attributes for the SVG element
     * @return void
     * @since 2.0.0
     */
    public static function echoMainLogo(array $attributes = []): void
    {
        echo wp_kses(self::getMainLogo($attributes), [
            'svg' => [
                'xmlns' => true,
                'width' => true,
                'height' => true,
                'viewBox' => true,
                'fill' => true,
                'class' => true,
            ],
            'mask' => [
                'id' => true,
                'maskUnits' => true,
                'x' => true,
                'y' => true,
                'width' => true,
                'height' => true,
                'fill' => true,
            ],
            'rect' => [
                'fill' => true,
                'width' => true,
                'height' => true,
            ],
            'path' => [
                'd' => true,
                'fill' => true,
                'fill-rule' => true,
                'clip-rule' => true,
                'mask' => true,
            ],
            'g' => [
                'clip-path' => true,
            ],
            'defs' => [],
            'linearGradient' => [
                'id' => true,
                'x1' => true,
                'y1' => true,
                'x2' => true,
                'y2' => true,
                'gradientUnits' => true,
            ],
            'stop' => [
                'stop-color' => true,
                'offset' => true,
            ],
            'clipPath' => [
                'id' => true,
            ],
        ]);
    }

    /**
     * Echo the compact logo directly to output
     *
     * @param array $attributes Optional attributes for the SVG element
     * @return void
     * @since 2.0.0
     */
    public static function echoCompactLogo(array $attributes = []): void
    {
        echo wp_kses(self::getCompactLogo($attributes), [
            'svg' => [
                'xmlns' => true,
                'width' => true,
                'height' => true,
                'viewBox' => true,
                'fill' => true,
                'class' => true,
            ],
            'mask' => [
                'id' => true,
                'maskUnits' => true,
                'x' => true,
                'width' => true,
                'height' => true,
                'fill' => true,
            ],
            'rect' => [
                'fill' => true,
                'width' => true,
                'height' => true,
            ],
            'path' => [
                'd' => true,
                'fill' => true,
                'mask' => true,
            ],
            'g' => [
                'clip-path' => true,
            ],
            'defs' => [],
            'linearGradient' => [
                'id' => true,
                'x1' => true,
                'y1' => true,
                'x2' => true,
                'y2' => true,
                'gradientUnits' => true,
            ],
            'stop' => [
                'stop-color' => true,
                'offset' => true,
            ],
            'clipPath' => [
                'id' => true,
            ],
        ]);
    }
}
