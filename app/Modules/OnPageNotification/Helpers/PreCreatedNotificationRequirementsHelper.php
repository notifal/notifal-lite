<?php

namespace Notifal\Modules\OnPageNotification\Helpers;

defined('ABSPATH') || exit;

/**
 * Validates marketplace template requirements against the running Notifal install.
 *
 * @since 2.4.3
 * @author Hossein <hossein@notifal.com>
 */
class PreCreatedNotificationRequirementsHelper
{
    /**
     * Get the running Notifal plugin version.
     *
     * @since 2.4.3
     * @return string Semantic version string.
     */
    public static function getCurrentNotifalVersion(): string
    {
        // Use NOTIFAL_VERSION when defined, otherwise fall back to a safe default.
        return defined('NOTIFAL_VERSION') ? (string) NOTIFAL_VERSION : '0.0.0';
    }

    /**
     * Sanitize a version string from API or post meta.
     *
     * @since 2.4.3
     * @param string|null $version Raw version value.
     * @return string Sanitized version or empty string when invalid.
     */
    public static function sanitizeVersion(?string $version): string
    {
        // Reject null or empty values early.
        if ($version === null || $version === '') {
            return '';
        }

        // Strip unsafe characters while keeping semantic version segments.
        $sanitized = sanitize_text_field($version);
        if ($sanitized === '' || !preg_match('/^[0-9]+(?:\.[0-9]+)*(?:-[0-9A-Za-z.-]+)?$/', $sanitized)) {
            return '';
        }

        return $sanitized;
    }

    /**
     * Extract minimum Notifal version from list or single notification payloads.
     *
     * @since 2.4.3
     * @param array<string, mixed> $notification Notification payload from marketplace API.
     * @return string Minimum required version or empty string when not specified.
     */
    public static function extractMinimumNotifalVersion(array $notification): string
    {
        // Single-detail responses nest requirements under a dedicated key.
        if (isset($notification['requirements']) && is_array($notification['requirements'])) {
            $fromRequirements = self::sanitizeVersion(
                isset($notification['requirements']['min_notifal_version'])
                    ? (string) $notification['requirements']['min_notifal_version']
                    : ''
            );

            if ($fromRequirements !== '') {
                return $fromRequirements;
            }
        }

        // List responses expose min_notifal_version at the root level.
        return self::sanitizeVersion(
            isset($notification['min_notifal_version']) ? (string) $notification['min_notifal_version'] : ''
        );
    }

    /**
     * Whether the current install meets the template minimum Notifal version.
     *
     * @since 2.4.3
     * @param string|null $minimumVersion Required minimum version.
     * @return bool True when requirement is empty or current version is sufficient.
     */
    public static function meetsMinimumNotifalVersion(?string $minimumVersion): bool
    {
        // Sanitize incoming requirement before comparison.
        $minimum = self::sanitizeVersion($minimumVersion);

        // No minimum declared: allow import.
        if ($minimum === '') {
            return true;
        }

        // Compare semantic versions using WordPress-compatible version_compare.
        return version_compare(self::getCurrentNotifalVersion(), $minimum, '>=');
    }

    /**
     * Build user-facing message when Notifal must be updated before import.
     *
     * @since 2.4.3
     * @param string $minimumVersion Required minimum version.
     * @return string Empty when requirement is already met.
     */
    public static function getMinimumNotifalVersionMessage(string $minimumVersion): string
    {
        // Sanitize required version before building the message.
        $minimum = self::sanitizeVersion($minimumVersion);
        if ($minimum === '' || self::meetsMinimumNotifalVersion($minimum)) {
            return '';
        }

        // Localize message with required and current versions.
        return sprintf(
            /* translators: 1: required Notifal version, 2: currently installed Notifal version */
            __(
                'This template requires Notifal %1$s or higher. You are running %2$s. Please update Notifal from Plugins to import this template.',
                'notifal'
            ),
            $minimum,
            self::getCurrentNotifalVersion()
        );
    }

    /**
     * Evaluate whether a notification can be imported based on Notifal version.
     *
     * @since 2.4.3
     * @param array<string, mixed> $notification Marketplace notification payload.
     * @return array{meets_notifal_version: bool, min_notifal_version: string, message: string}
     */
    public static function evaluateNotifalVersionRequirement(array $notification): array
    {
        // Resolve minimum version from notification data.
        $minVersion = self::extractMinimumNotifalVersion($notification);

        // Determine if current install satisfies the requirement.
        $meets = self::meetsMinimumNotifalVersion($minVersion);

        // Build localized message only when import should be blocked.
        $message = $meets ? '' : self::getMinimumNotifalVersionMessage($minVersion);

        return [
            'meets_notifal_version' => $meets,
            'min_notifal_version'   => $minVersion,
            'message'               => $message,
        ];
    }
}
