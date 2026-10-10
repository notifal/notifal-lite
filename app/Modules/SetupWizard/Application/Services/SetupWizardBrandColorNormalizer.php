<?php

namespace Notifal\Modules\SetupWizard\Application\Services;

defined('ABSPATH') || exit;

/**
 * Normalizes CSS color tokens and filters non-brand neutrals for Setup Wizard.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class SetupWizardBrandColorNormalizer
{
    /**
     * Default Notifal purple excluded from site detection noise.
     *
     * @since 3.0.0
     * @var string
     */
    public const DEFAULT_COLOR = '#7e2bd2';

    /**
     * Common CMS/browser default blues that are rarely real brand accents.
     *
     * @since 3.0.0
     * @var string[]
     */
    private const INCIDENTAL_BLUES = [
        '#336699',
        '#003366',
        '#003399',
        '#0000ee',
        '#0000ff',
        '#0066cc',
        '#0073aa',
        '#2271b1',
        '#135e96',
        '#0693e3',
        '#8ed1fc',
    ];

    /**
     * Normalize a CSS color token (hex, rgb/rgba, hsl/hsla) to lowercase hex.
     *
     * @since 3.0.0
     * @param string $value Raw CSS color value.
     * @return string Normalized hex or empty string.
     */
    public function normalize(string $value): string
    {
        // Trim wrappers and trailing !important from CSS declarations.
        $value = trim(preg_replace('/\s*!important\s*$/i', '', trim($value)) ?? '');
        if ('' === $value) {
            return '';
        }

        // Prefer an explicit hex token when present in the value.
        if (preg_match('/#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})\b/', $value, $hexMatch)) {
            return $this->sanitizeHex((string) $hexMatch[0]);
        }

        // Convert rgb()/rgba() channel values into a hex string.
        if (preg_match('/rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})(?:\s*,\s*([0-9.]+))?\s*\)/i', $value, $rgbMatch)) {
            $alpha = isset($rgbMatch[4]) ? (float) $rgbMatch[4] : 1.0;
            // Ignore nearly transparent overlays that are not brand fills.
            if ($alpha < 0.45) {
                return '';
            }
            $red = max(0, min(255, (int) $rgbMatch[1]));
            $green = max(0, min(255, (int) $rgbMatch[2]));
            $blue = max(0, min(255, (int) $rgbMatch[3]));

            return sprintf('#%02x%02x%02x', $red, $green, $blue);
        }

        // Convert hsl()/hsla() into hex for theme CSS that stores accents that way.
        if (preg_match('/hsla?\(\s*([0-9.]+)\s*,\s*([0-9.]+)%\s*,\s*([0-9.]+)%(?:\s*,\s*([0-9.]+))?\s*\)/i', $value, $hslMatch)) {
            $alpha = isset($hslMatch[4]) ? (float) $hslMatch[4] : 1.0;
            if ($alpha < 0.45) {
                return '';
            }

            return $this->hslToHex((float) $hslMatch[1], (float) $hslMatch[2], (float) $hslMatch[3]);
        }

        return '';
    }

    /**
     * Normalize one hex color string to lowercase 6-digit form.
     *
     * @since 3.0.0
     * @param string $color Raw color value.
     * @return string Normalized hex or empty string.
     */
    public function sanitizeHex(string $color): string
    {
        // Accept short and long hex formats only.
        $color = trim($color);
        if (!preg_match('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $color)) {
            return '';
        }

        // Expand 3-digit hex so palette comparisons stay consistent.
        if (4 === strlen($color)) {
            $color = sprintf(
                '#%s%s%s%s%s%s',
                $color[1],
                $color[1],
                $color[2],
                $color[2],
                $color[3],
                $color[3]
            );
        }

        return strtolower($color);
    }

    /**
     * Whether a color is usable as a brand accent candidate.
     *
     * @since 3.0.0
     * @param string $color Normalized or raw color.
     * @return bool True when the color can enter the candidate set.
     */
    public function isBrandCandidate(string $color): bool
    {
        // Normalize before neutrality and default-color checks.
        $hex = $this->sanitizeHex($color);
        if ('' === $hex) {
            $hex = $this->normalize($color);
        }

        // Reject empty, product-default purple, and near-neutral tones.
        return '' !== $hex
            && self::DEFAULT_COLOR !== $hex
            && !$this->isNeutral($hex);
    }

    /**
     * Whether a color matches a known incidental default blue.
     *
     * @since 3.0.0
     * @param string $color Normalized hex color.
     * @return bool True when the color should be deprioritized.
     */
    public function isIncidentalBlue(string $color): bool
    {
        // Compare against the curated incidental-blue denylist.
        return in_array($this->sanitizeHex($color), self::INCIDENTAL_BLUES, true);
    }

    /**
     * Whether a color is too close to white, black, or gray to be a brand accent.
     *
     * @since 3.0.0
     * @param string $color Normalized hex color.
     * @return bool True when the color should be ignored.
     */
    public function isNeutral(string $color): bool
    {
        // Expand short hex before channel comparison when needed.
        $color = $this->sanitizeHex($color);
        if (!preg_match('/^#([A-Fa-f0-9]{2})([A-Fa-f0-9]{2})([A-Fa-f0-9]{2})$/', $color, $channels)) {
            return true;
        }

        $red = hexdec($channels[1]);
        $green = hexdec($channels[2]);
        $blue = hexdec($channels[3]);
        $max = max($red, $green, $blue);
        $min = min($red, $green, $blue);
        $saturation = $max - $min;

        // Treat very light, very dark, and low-saturation grays as neutral noise.
        return $max < 40 || $min > 220 || $saturation < 18;
    }

    /**
     * Relative saturation channel delta (0-255) for scoring accents.
     *
     * @since 3.0.0
     * @param string $color Normalized hex color.
     * @return int Saturation delta.
     */
    public function saturationDelta(string $color): int
    {
        // Parse RGB channels for max-min saturation.
        $color = $this->sanitizeHex($color);
        if (!preg_match('/^#([A-Fa-f0-9]{2})([A-Fa-f0-9]{2})([A-Fa-f0-9]{2})$/', $color, $channels)) {
            return 0;
        }

        $red = hexdec($channels[1]);
        $green = hexdec($channels[2]);
        $blue = hexdec($channels[3]);

        return (int) (max($red, $green, $blue) - min($red, $green, $blue));
    }

    /**
     * Average channel lightness (0-255) for mid-tone accent preference.
     *
     * @since 3.0.0
     * @param string $color Normalized hex color.
     * @return int Average RGB lightness.
     */
    public function lightness(string $color): int
    {
        // Parse RGB channels for mean lightness.
        $color = $this->sanitizeHex($color);
        if (!preg_match('/^#([A-Fa-f0-9]{2})([A-Fa-f0-9]{2})([A-Fa-f0-9]{2})$/', $color, $channels)) {
            return 0;
        }

        return (int) round(
            (hexdec($channels[1]) + hexdec($channels[2]) + hexdec($channels[3])) / 3
        );
    }

    /**
     * Convert HSL percentages into a normalized hex color.
     *
     * @since 3.0.0
     * @param float $hue Hue degrees (0-360).
     * @param float $saturation Saturation percent (0-100).
     * @param float $lightness Lightness percent (0-100).
     * @return string Normalized hex or empty string.
     */
    private function hslToHex(float $hue, float $saturation, float $lightness): string
    {
        // Clamp HSL inputs into valid CSS ranges.
        $hue = fmod(($hue % 360) + 360, 360) / 360;
        $saturation = max(0.0, min(100.0, $saturation)) / 100;
        $lightness = max(0.0, min(100.0, $lightness)) / 100;

        // Convert HSL to RGB using the standard CSS algorithm.
        if ($saturation < 0.0001) {
            $channel = (int) round($lightness * 255);

            return sprintf('#%02x%02x%02x', $channel, $channel, $channel);
        }

        $q = $lightness < 0.5
            ? $lightness * (1 + $saturation)
            : $lightness + $saturation - $lightness * $saturation;
        $p = 2 * $lightness - $q;
        $red = (int) round($this->hueToRgb($p, $q, $hue + (1 / 3)) * 255);
        $green = (int) round($this->hueToRgb($p, $q, $hue) * 255);
        $blue = (int) round($this->hueToRgb($p, $q, $hue - (1 / 3)) * 255);

        return sprintf('#%02x%02x%02x', $red, $green, $blue);
    }

    /**
     * Resolve one RGB channel from HSL intermediate values.
     *
     * @since 3.0.0
     * @param float $p HSL intermediate p.
     * @param float $q HSL intermediate q.
     * @param float $t Hue offset component.
     * @return float Channel intensity 0-1.
     */
    private function hueToRgb(float $p, float $q, float $t): float
    {
        // Wrap the hue offset into the 0-1 range.
        if ($t < 0) {
            $t += 1;
        }
        if ($t > 1) {
            $t -= 1;
        }
        if ($t < 1 / 6) {
            return $p + ($q - $p) * 6 * $t;
        }
        if ($t < 1 / 2) {
            return $q;
        }
        if ($t < 2 / 3) {
            return $p + ($q - $p) * (2 / 3 - $t) * 6;
        }

        return $p;
    }
}
