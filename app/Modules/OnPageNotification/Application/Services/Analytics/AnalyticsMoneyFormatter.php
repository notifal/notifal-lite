<?php
/**
 * Formats analytics revenue amounts using WooCommerce, EDD, or a safe fallback.
 *
 * Centralizes money display so admin analytics stay aligned with the store currency
 * (for example custom symbols such as Toman on WooCommerce sites).
 *
 * @package Notifal\Modules\OnPageNotification\Application\Services\Analytics
 * @since   2.2.4
 * @author  Hossein <hossein@notifal.com>
 */

namespace Notifal\Modules\OnPageNotification\Application\Services\Analytics;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Infrastructure\WordPress\Support\PluginDetector;

defined('ABSPATH') || exit;

/**
 * Class AnalyticsMoneyFormatter
 *
 * @since 2.2.4
 */
final class AnalyticsMoneyFormatter
{
    /**
     * Format a monetary amount as plain text suitable for esc_html().
     *
     * @param float $amount Raw amount in store currency units.
     * @return string Human-readable money string without HTML wrappers.
     * @since 2.2.4
     */
    public function formatPlain(float $amount): string
    {
        // Build the default presentation using whichever commerce stack is active.
        $formatted = $this->formatPlainInternal($amount);
        // Allow extensions (for example SaaS billing) to override the final string.
        return (string) apply_filters(FilterHooks::ANALYTICS_FORMAT_MONEY, $formatted, $amount);
    }

    /**
     * Configuration for client-side chart axis labels and similar UI.
     *
     * @return array<string, mixed> Decimals, separators, symbol, and symbol position.
     * @since 2.2.4
     */
    public function getJsMoneyConfig(): array
    {
        // Start from WooCommerce settings when the store runs on WooCommerce.
        if (PluginDetector::isWooCommerceActive()) {
            $config = $this->buildWooCommerceJsConfig();
        } elseif (PluginDetector::isEDDActive()) {
            // Fall back to Easy Digital Downloads currency options when EDD is active.
            $config = $this->buildEddJsConfig();
        } else {
            // Generic fallback when no supported commerce plugin is present.
            $config = $this->buildFallbackJsConfig();
        }
        // Permit other plugins to tweak the payload passed to JavaScript.
        return (array) apply_filters(FilterHooks::ANALYTICS_MONEY_JS_CONFIG, $config, $this);
    }

    /**
     * Core formatting logic without the public filter hook.
     *
     * @param float $amount Raw amount.
     * @return string Plain-text money string.
     * @since 2.2.4
     */
    private function formatPlainInternal(float $amount): string
    {
        // Prefer WooCommerce price formatting so filters like woocommerce_currency_symbol apply.
        if (PluginDetector::isWooCommerceActive() && function_exists('wc_price')) {
            $html = \wc_price($amount);
            // Strip markup while preserving textual content such as multi-byte currency labels.
            return $this->decodeEntities(wp_strip_all_tags((string) $html));
        }
        // Use EDD helpers when WooCommerce is not active but EDD is.
        if (PluginDetector::isEDDActive() && function_exists('edd_format_amount') && function_exists('edd_currency_filter')) {
            $numeric = \edd_format_amount($amount);
            return $this->decodeEntities((string) \edd_currency_filter($numeric));
        }
        // Last resort keeps a predictable shape for sites without WC or EDD.
        return $this->formatFallbackPlain($amount);
    }

    /**
     * Build JS config from WooCommerce price settings.
     *
     * @return array<string, mixed>
     * @since 2.2.4
     */
    private function buildWooCommerceJsConfig(): array
    {
        // Resolve symbol text for use in canvas labels (no HTML).
        $symbol = '';
        if (function_exists('get_woocommerce_currency_symbol')) {
            $symbol = $this->decodeEntities(wp_strip_all_tags((string) \get_woocommerce_currency_symbol()));
        }
        // Decimal count drives how fractional amounts render on the chart axis.
        $decimals = function_exists('wc_get_price_decimals') ? (int) \wc_get_price_decimals() : 2;
        // Separator characters mirror WooCommerce storefront formatting.
        $decimalSep = function_exists('wc_get_price_decimal_separator') ? (string) \wc_get_price_decimal_separator() : '.';
        $thousandSep = function_exists('wc_get_price_thousand_separator') ? (string) \wc_get_price_thousand_separator() : ',';
        // Map WooCommerce position codes to a small vocabulary understood by the dashboard script.
        $wcPos = function_exists('get_option') ? (string) \get_option('woocommerce_currency_pos', 'left') : 'left';
        $position = $this->normalizeCurrencyPositionFromWooCommerce($wcPos);

        return [
            'engine' => 'woocommerce',
            'symbol' => $symbol,
            'decimals' => $decimals,
            'decimal_sep' => $decimalSep,
            'thousand_sep' => $thousandSep,
            'position' => $position,
        ];
    }

    /**
     * Build JS config from EDD settings.
     *
     * @return array<string, mixed>
     * @since 2.2.4
     */
    private function buildEddJsConfig(): array
    {
        // Currency glyph for EDD-driven stores.
        $symbol = function_exists('edd_currency_symbol') ? $this->decodeEntities((string) \edd_currency_symbol()) : '';
        // Derive decimal places from a canonical formatted sample to stay compatible across EDD versions.
        $decimals = 2;
        if (function_exists('edd_format_amount')) {
            $sample = (string) \edd_format_amount(1.234);
            if (preg_match('/\.(\d+)/', $sample, $matches)) {
                $decimals = strlen($matches[1]);
            }
        }
        // EDD stores position as before or after the numeric amount.
        $eddPos = 'before';
        if (function_exists('edd_get_option')) {
            $eddPos = (string) \edd_get_option('currency_position', 'before');
        }
        $position = ('after' === $eddPos) ? 'after' : 'before';
        // Separator characters mirror EDD global settings when available.
        $decimalSep = function_exists('edd_get_option') ? (string) \edd_get_option('decimal_separator', '.') : '.';
        $thousandSep = function_exists('edd_get_option') ? (string) \edd_get_option('thousands_separator', ',') : ',';

        return [
            'engine' => 'edd',
            'symbol' => $symbol,
            'decimals' => $decimals,
            'decimal_sep' => $decimalSep,
            'thousand_sep' => $thousandSep,
            'position' => $position,
        ];
    }

    /**
     * Generic JS config when no commerce plugin is detected.
     *
     * @return array<string, mixed>
     * @since 2.2.4
     */
    private function buildFallbackJsConfig(): array
    {
        return [
            'engine' => 'fallback',
            'symbol' => '$',
            'decimals' => 2,
            'decimal_sep' => '.',
            'thousand_sep' => ',',
            'position' => 'before',
        ];
    }

    /**
     * Map WooCommerce currency position option to normalized codes.
     *
     * @param string $wcPosition Raw option value from WooCommerce.
     * @return string Normalized position key.
     * @since 2.2.4
     */
    private function normalizeCurrencyPositionFromWooCommerce(string $wcPosition): string
    {
        switch ($wcPosition) {
            case 'right':
                return 'after';
            case 'right_space':
                return 'after_space';
            case 'left_space':
                return 'before_space';
            case 'left':
            default:
                return 'before';
        }
    }

    /**
     * Plain fallback formatter when neither WooCommerce nor EDD is available.
     *
     * @param float $amount Raw amount.
     * @return string Formatted amount with a leading dollar sign.
     * @since 2.2.4
     */
    private function formatFallbackPlain(float $amount): string
    {
        return '$' . number_format($amount, 2, '.', ',');
    }

    /**
     * Decode common HTML entities left after stripping tags.
     *
     * @param string $value Possibly entity-encoded string.
     * @return string Decoded plain text.
     * @since 2.2.4
     */
    private function decodeEntities(string $value): string
    {
        return wp_specialchars_decode($value, ENT_QUOTES);
    }
}
