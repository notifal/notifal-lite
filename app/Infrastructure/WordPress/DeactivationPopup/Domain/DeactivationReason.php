<?php
/**
 * Deactivation Reason Enum
 *
 * Defines all possible reasons for plugin deactivation.
 *
 * @package Notifal\Infrastructure\WordPress\DeactivationPopup\Domain
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */

namespace Notifal\Infrastructure\WordPress\DeactivationPopup\Domain;

defined('ABSPATH') || exit;

/**
 * Enum for deactivation reasons
 */
class DeactivationReason
{
    /**
     * User no longer needs the plugin
     *
     * @var string
     */
    public const NO_LONGER_NEEDED = 'no_longer_needed';

    /**
     * User found a better plugin
     *
     * @var string
     */
    public const FOUND_BETTER_PLUGIN = 'found_better_plugin';

    /**
     * User couldn't get the plugin to work
     *
     * @var string
     */
    public const COULD_NOT_GET_TO_WORK = 'could_not_get_to_work';

    /**
     * Temporary deactivation
     *
     * @var string
     */
    public const TEMPORARY_DEACTIVATION = 'temporary_deactivation';

    /**
     * User has Notifal Pro
     *
     * @var string
     */
    public const HAS_NOTIFAL_PRO = 'has_notifal_pro';

    /**
     * Other reason (with custom text)
     *
     * @var string
     */
    public const OTHER = 'other';

    /**
     * Get all available deactivation reasons
     *
     * @return array Array of reason constants
     * @since 2.0.0
     */
    public static function getAllReasons(): array
    {
        return [
            self::NO_LONGER_NEEDED,
            self::FOUND_BETTER_PLUGIN,
            self::COULD_NOT_GET_TO_WORK,
            self::TEMPORARY_DEACTIVATION,
            self::HAS_NOTIFAL_PRO,
            self::OTHER,
        ];
    }

    /**
     * Get user-friendly labels for reasons
     *
     * @return array Array with reason keys and labels
     * @since 2.0.0
     */
    public static function getReasonLabels(): array
    {
        return [
            self::NO_LONGER_NEEDED      => __('I no longer need the plugin', 'notifal'),
            self::FOUND_BETTER_PLUGIN    => __('I found a better plugin', 'notifal'),
            self::COULD_NOT_GET_TO_WORK  => __("I couldn't get the plugin to work", 'notifal'),
            self::TEMPORARY_DEACTIVATION => __("It's a temporary deactivation", 'notifal'),
            self::HAS_NOTIFAL_PRO        => __('I have Notifal Pro', 'notifal'),
            self::OTHER                  => __('Other', 'notifal'),
        ];
    }

    /**
     * Check if a reason requires additional input
     *
     * @param string $reason The reason to check
     * @return bool True if additional input is required
     * @since 2.0.0
     */
    public static function requiresAdditionalInput(string $reason): bool
    {
        return in_array($reason, [
            self::FOUND_BETTER_PLUGIN,
            self::OTHER,
        ], true);
    }

    /**
     * Check if a reason should prevent deactivation
     *
     * @param string $reason The reason to check
     * @return bool True if deactivation should be prevented
     * @since 2.0.0
     */
    public static function shouldPreventDeactivation(string $reason): bool
    {
        return $reason === self::HAS_NOTIFAL_PRO;
    }

    /**
     * Validate if a reason is valid
     *
     * @param string $reason The reason to validate
     * @return bool True if reason is valid
     * @since 2.0.0
     */
    public static function isValidReason(string $reason): bool
    {
        return in_array($reason, self::getAllReasons(), true);
    }

    /**
     * Get full reason configurations for frontend use
     *
     * @return array Array of reason configurations with labels and input requirements
     * @since 2.0.0
     */
    public static function getReasonsConfiguration(): array
    {
        return [
            [
                'value' => self::NO_LONGER_NEEDED,
                'label' => self::getReasonLabels()[self::NO_LONGER_NEEDED],
                'requiresInput' => false,
            ],
            [
                'value' => self::FOUND_BETTER_PLUGIN,
                'label' => self::getReasonLabels()[self::FOUND_BETTER_PLUGIN],
                'requiresInput' => true,
                'inputType' => 'text',
                'inputPlaceholder' => __('Please share which plugin', 'notifal'),
                'inputName' => 'plugin_found',
            ],
            [
                'value' => self::COULD_NOT_GET_TO_WORK,
                'label' => self::getReasonLabels()[self::COULD_NOT_GET_TO_WORK],
                'requiresInput' => false,
                'showHelpText' => true,
            ],
            [
                'value' => self::TEMPORARY_DEACTIVATION,
                'label' => self::getReasonLabels()[self::TEMPORARY_DEACTIVATION],
                'requiresInput' => false,
            ],
            [
                'value' => self::HAS_NOTIFAL_PRO,
                'label' => self::getReasonLabels()[self::HAS_NOTIFAL_PRO],
                'requiresInput' => false,
                'preventsDeactivation' => true,
                'preventionMessage' => __("Wait! Don't deactivate Notifal. You have to activate both Notifal and Notifal Pro in order for the plugin to work.", 'notifal'),
            ],
            [
                'value' => self::OTHER,
                'label' => self::getReasonLabels()[self::OTHER],
                'requiresInput' => true,
                'inputType' => 'textarea',
                'inputPlaceholder' => __('Please share the reason', 'notifal'),
                'inputName' => 'additional_feedback',
            ],
        ];
    }

    /**
     * Get prevention message for Pro users
     *
     * @return string Prevention message
     * @since 2.0.0
     */
    public static function getProPreventionMessage(): string
    {
        return __("Wait! Don't deactivate Notifal. You have to activate both Notifal and Notifal Pro in order for the plugin to work.", 'notifal');
    }
}
