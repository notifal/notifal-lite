<?php

namespace Notifal\Modules\OnPageNotification\Application\Support;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Shared minimum bounds for OnPage Notification duration fields.
 *
 * Upper limits are intentionally omitted for second-based timings so each site
 * can choose any delay (e.g. 16 minutes, 1 hour). Only invalid lows such as
 * negative values are blocked during save and in admin inputs.
 *
 * @since 2.3.10
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Modules\OnPageNotification\Application\Support
 */
class OnPageNotificationSettingsLimits
{
    /** @since 2.3.10 Minimum delay before showing (seconds). */
    public const MIN_DELAY_SECONDS = 0;

    /** @since 2.3.10 Minimum idle time before showing (seconds). */
    public const MIN_IDLE_SECONDS = 1;

    /** @since 2.3.10 Minimum custom trigger follow-up delay (seconds). */
    public const MIN_CUSTOM_TRIGGER_DELAY_SECONDS = 0.0;

    /** @since 2.3.10 Minimum auto-hide duration (seconds). */
    public const MIN_AUTO_HIDE_SECONDS = 1;

    /** @since 2.3.10 Minimum retrigger delay (seconds). */
    public const MIN_RETRIGGER_DELAY_SECONDS = 1;

    /** @since 2.3.10 Minimum close delay after form submit or action button (seconds). */
    public const MIN_BEHAVIOR_CLOSE_DELAY_SECONDS = 0;
}
