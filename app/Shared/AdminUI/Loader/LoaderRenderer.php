<?php

namespace Notifal\Shared\AdminUI\Loader;

defined('ABSPATH') || exit;

/**
 * Renders the shared Notifal animated loader overlay for admin screens.
 *
 * @since 2.4.0
 * @author Hossein <hossein@notifal.com>
 */
class LoaderRenderer
{
    /**
     * Default loader element id used by the HTML Builder screen.
     *
     * @since 2.4.0
     */
    public const HTML_BUILDER_LOADER_ID = 'notifal-html-builder-loader';

    /**
     * Render the animated Notifal loader overlay.
     *
     * @param string $elementId DOM id for the overlay element.
     * @param bool   $showBrand Whether to render the NOTIFAL brand label.
     * @return void
     * @since 2.4.0
     */
    public static function render(string $elementId = self::HTML_BUILDER_LOADER_ID, bool $showBrand = false): void
    {
        $id = sanitize_html_class($elementId);
        ?>
        <div class="notifal-loader-overlay" id="<?php echo esc_attr($id); ?>" role="status" aria-live="polite" aria-busy="true">
            <div class="notifal-logo-stage">
                <div class="notifal-loader-ring" aria-hidden="true"></div>
                <div class="notifal-loader-ring" aria-hidden="true"></div>
                <div class="notifal-loader-ring" aria-hidden="true"></div>
                <div class="notifal-loader-particle" aria-hidden="true"></div>
                <div class="notifal-loader-particle" aria-hidden="true"></div>
                <div class="notifal-loader-particle" aria-hidden="true"></div>
                <div class="notifal-loader-particle" aria-hidden="true"></div>
                <div class="notifal-loader-particle" aria-hidden="true"></div>
                <div class="notifal-loader-logo" aria-hidden="true">
                    <?php echo self::getBellSvgMarkup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG ?>
                </div>
            </div>
            <?php if ($showBrand) : ?>
                <div class="notifal-loader-brand" aria-hidden="true">
                    <span>N</span><span>O</span><span>T</span><span>I</span><span>F</span><span>A</span><span>L</span>
                </div>
            <?php endif; ?>
            <div class="notifal-loader-progress" aria-hidden="true"></div>
        </div>
        <?php
    }

    /**
     * Return the animated bell SVG used inside the loader.
     *
     * @return string SVG markup.
     * @since 2.4.0
     */
    private static function getBellSvgMarkup(): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 2000 2000" focusable="false">'
            . '<path class="notifal-loader-bell-path" d="M1879.94,1474.61v218.58c0,36.84-29.86,66.7-66.7,66.7h0c-254.75,0-461.27-206.52-461.27-461.27V658.51'
            . 'c0-169.3-137.24-306.54-306.54-306.54h-90.88c-169.3,0-306.54,137.24-306.54,306.54v640.11c0,254.75-206.52,461.27-461.27,461.27h0'
            . 'c-36.84,0-66.7-29.86-66.7-66.7v-218.58c0-36.84,29.86-66.7,66.7-66.7h0c60.36,0,109.29-48.93,109.29-109.29V647.97'
            . 'C296.04,290.11,586.15,0,944.01,0l111.97,0c357.86,0,647.97,290.11,647.97,647.97v650.65c0,60.36,48.93,109.29,109.29,109.29h0'
            . 'C1850.08,1407.91,1879.94,1437.77,1879.94,1474.61z"/>'
            . '<path class="notifal-loader-clapper-path" d="M805.65,1759.89h388.69c27.48,0,49.76,22.28,49.76,49.76v0c0,107.33-87.01,194.35-194.35,194.35h-99.53'
            . 'c-107.33,0-194.35-87.01-194.35-194.35v0C755.89,1782.17,778.17,1759.89,805.65,1759.89z"/>'
            . '</svg>';
    }
}
