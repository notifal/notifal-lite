<?php

namespace Notifal\Modules\Templates\Application\Services;

defined('ABSPATH') || exit;

/**
 * Default HTML Builder countdown widget markup (attribute-based settings).
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class HtmlBuilderCountdownMarkup
{
    /**
     * Default evergreen duration: 1 day, 10 hours, 30 minutes (124200 seconds).
     *
     * Matches AI prompt examples so the canvas shows filled day/hour/minute boxes.
     *
     * @since 3.0.0
     */
    public const DEFAULT_EVERGREEN_SECONDS = 124200;

    /**
     * Default insert / AI snippet for the countdown widget.
     *
     * @return string Widget HTML fragment.
     * @since 3.0.0
     */
    public static function defaultHtml(): string
    {
        // Evergreen 1d 10h 30m timer with all units visible and ready-to-use box styles.
        $rootStyle = 'display:inline-flex;align-items:center;gap:8px;font-family:inherit;';
        $boxStyle = 'display:flex;flex-direction:column;align-items:center;justify-content:center;min-width:52px;padding:8px 10px;background:#1f2430;border-radius:8px;';
        $valueStyle = 'font-size:22px;font-weight:700;line-height:1.1;color:#ffffff;';
        $labelStyle = 'margin-top:2px;font-size:10px;font-weight:600;letter-spacing:0.04em;text-transform:uppercase;color:#9ca3af;';
        $sepStyle = 'font-size:20px;font-weight:700;color:#1f2430;';

        $rootAttrs = 'data-notifal-widget="countdown" class="notifal-countdown"'
            . ' data-countdown-type="evergreen" data-countdown-seconds="' . self::DEFAULT_EVERGREEN_SECONDS . '"'
            . ' data-countdown-format="dd:hh:mm:ss"'
            . ' data-countdown-show-days="1" data-countdown-show-hours="1"'
            . ' data-countdown-show-minutes="1" data-countdown-show-seconds="1"'
            . ' data-countdown-show-labels="1" data-countdown-on-complete="stay"'
            . ' aria-label="Countdown timer" style="' . $rootStyle . '"';

        return '<div ' . $rootAttrs . '>'
            . self::box('days', '01', 'Days', $boxStyle, $valueStyle, $labelStyle)
            . self::sep($sepStyle)
            . self::box('hours', '10', 'Hours', $boxStyle, $valueStyle, $labelStyle)
            . self::sep($sepStyle)
            . self::box('minutes', '30', 'Minutes', $boxStyle, $valueStyle, $labelStyle)
            . self::sep($sepStyle)
            . self::box('seconds', '00', 'Seconds', $boxStyle, $valueStyle, $labelStyle)
            . '</div>';
    }

    /**
     * One unit box (value + label).
     *
     * @param string $unit       days|hours|minutes|seconds.
     * @param string $value      Zero-padded display value.
     * @param string $label      Visible unit label.
     * @param string $boxStyle   Box inline style.
     * @param string $valueStyle Value inline style.
     * @param string $labelStyle Label inline style.
     * @return string Box HTML.
     * @since 3.0.0
     */
    private static function box(
        string $unit,
        string $value,
        string $label,
        string $boxStyle,
        string $valueStyle,
        string $labelStyle
    ): string {
        return '<div class="notifal-countdown__box" style="' . $boxStyle . '">'
            . '<span class="notifal-countdown__value" data-countdown-unit="' . $unit . '" style="' . $valueStyle . '">' . $value . '</span>'
            . '<span class="notifal-countdown__label" style="' . $labelStyle . '">' . $label . '</span>'
            . '</div>';
    }

    /**
     * Separator between unit boxes.
     *
     * @param string $sepStyle Separator inline style.
     * @return string Separator HTML.
     * @since 3.0.0
     */
    private static function sep(string $sepStyle): string
    {
        return '<span class="notifal-countdown__sep" style="' . $sepStyle . '">:</span>';
    }
}
