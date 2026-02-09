<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Core;

use Notifal\Shared\Utils\Helper;

defined('ABSPATH') || exit;

/**
 * Class GeolocationService
 *
 * Provides geolocation data for IP addresses used in tracking events.
 *
 * @package Notifal\Modules\OnPageNotification\Application\Services\Core
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class GeolocationService
{
    /**
     * Local/private IP addresses that should return dummy data.
     */
    private const LOCAL_IPS = ['127.0.0.1', '::1', '0.0.0.0', ''];

    /**
     * Cache for geolocation data to improve performance.
     *
     * @var array
     */
    private static $geolocationCache = [];

    /**
     * Get geolocation data from IP address with caching for performance.
     *
     * @param string $ipAddress IP address to geolocate
     * @return array Geolocation data with 'country_code' and 'city' keys
     * @since 2.0.0
     */
    public function getGeolocationData(string $ipAddress): array
    {
        // Check cache first
        if (isset(self::$geolocationCache[$ipAddress])) {
            return self::$geolocationCache[$ipAddress];
        }

        // Return dummy data for local/private IPs
        if ($this->isLocalOrPrivateIp($ipAddress)) {
            $data = [
                'country_code' => 'XX',
                'city' => 'Localhost',
            ];
        } else {
            $data = $this->fetchGeolocationFromApi($ipAddress);
        }

        // Cache the result
        self::$geolocationCache[$ipAddress] = $data;

        return $data;
    }

    /**
     * Check if IP address is local or private range.
     *
     * @param string $ipAddress IP address to check
     * @return bool True if local/private IP
     * @since 2.0.0
     */
    private function isLocalOrPrivateIp(string $ipAddress): bool
    {
        // Check exact matches for common local IPs
        if (in_array($ipAddress, self::LOCAL_IPS, true)) {
            return true;
        }

        // Check for private/reserved IP ranges
        return filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    /**
     * Fetch geolocation data from external API with error handling.
     *
     * @param string $ipAddress IP address to geolocate
     * @return array Geolocation data or null values on failure
     * @since 2.0.0
     */
    private function fetchGeolocationFromApi(string $ipAddress): array
    {
        try {
            // Use a free geolocation API (ipapi.co)
            $response = wp_remote_get("https://ipapi.co/{$ipAddress}/json/", [
                'timeout' => 3, // Reduced timeout for better performance
                'redirection' => 5,
                'user-agent' => 'Notifal/' . NOTIFAL_VERSION,
            ]);

            if (is_wp_error($response)) {
                return ['country_code' => null, 'city' => null];
            }

            $statusCode = wp_remote_retrieve_response_code($response);
            if ($statusCode !== 200) {
                return ['country_code' => null, 'city' => null];
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['country_code' => null, 'city' => null];
            }

            return [
                'country_code' => isset($data['country_code']) ? Helper::sanitizeInput($data['country_code'], 'text') : null,
                'city' => isset($data['city']) ? Helper::sanitizeInput($data['city'], 'text') : null,
            ];
        } catch (\Exception $e) {
            if (WP_DEBUG) {
                Helper::log('Geolocation API error for IP ' . $ipAddress . ': ' . $e->getMessage());
            }
            return ['country_code' => null, 'city' => null];
        }
    }
}
