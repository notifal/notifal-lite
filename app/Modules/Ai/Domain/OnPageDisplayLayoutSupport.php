<?php

namespace Notifal\Modules\Ai\Domain;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;

defined('ABSPATH') || exit;

/**
 * Capability-aware OnPage display layout allowlists for local intent validation.
 *
 * Mirrors notifal-ai-gateway DisplayLayoutKnowledgeBuilder so the client plugin
 * rejects the same layouts the hosted gateway would accept or deny.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class OnPageDisplayLayoutSupport
{
    /**
     * Display layouts available on every Notifal install.
     *
     * @var string[]
     */
    private const BASE_LAYOUTS = ['toast', 'popup', 'topbar'];

    /**
     * Pro-only display layouts (Notifal Pro 3.0.0+).
     *
     * @var string[]
     */
    private const PRO_LAYOUTS = ['inline'];

    /**
     * Build normalized client capability flags used for layout checks.
     *
     * Uses the same filter pipeline as GenerationBridgeService::buildCapabilities().
     *
     * @since 3.0.0
     * @return array<string, bool> Capability map (notifal_pro is the layout gate).
     */
    public static function clientCapabilities(): array
    {
        // Seed baseline flags from the local WordPress install.
        $capabilities = [
            'woocommerce'       => false,
            'notifal_pro'       => (bool) apply_filters('notifal_pro_multiple_notifications_allowed', false),
            'pro_display_rules' => (bool) apply_filters('notifal_pro_multiple_notifications_allowed', false),
            'comment_tags'      => false,
            'unfiltered_html'   => (bool) current_user_can('unfiltered_html'),
        ];

        /**
         * Filter client capability flags (shared with hosted generation requests).
         *
         * @since 3.0.0
         * @param array<string, bool> $capabilities Capability map.
         */
        $filtered = (array) apply_filters(FilterHooks::AI_GENERATION_CAPABILITIES, $capabilities);

        // Return only the Pro flag needed for layout allowlists.
        return [
            'notifal_pro' => !empty($filtered['notifal_pro']),
        ];
    }

    /**
     * Return every layout slug allowed for the current site capabilities.
     *
     * @since 3.0.0
     * @param array<string, bool>|null $capabilities Optional pre-built capability map.
     * @return string[] Allowed layout slugs in stable order.
     */
    public static function allowedLayouts(?array $capabilities = null): array
    {
        // Resolve capabilities when the caller did not pass a map.
        $capabilities = is_array($capabilities) ? $capabilities : self::clientCapabilities();

        // Start with floating overlay layouts every site supports.
        $layouts = self::BASE_LAYOUTS;

        // Append Pro-only inline when the install reports an active Pro license.
        if (!empty($capabilities['notifal_pro'])) {
            $layouts = array_merge($layouts, self::PRO_LAYOUTS);
        }

        // Return the capability-filtered slug list.
        return $layouts;
    }

    /**
     * Decide whether one layout slug is allowed for the current site.
     *
     * @since 3.0.0
     * @param string                   $layout       Candidate layout slug.
     * @param array<string, bool>|null $capabilities Optional pre-built capability map.
     * @return bool True when the slug is supported for this request.
     */
    public static function isAllowed(string $layout, ?array $capabilities = null): bool
    {
        // Sanitize the slug before comparing against the allowlist.
        $layout = sanitize_key($layout);

        // Reject empty slugs immediately.
        if ('' === $layout) {
            return false;
        }

        // Match against the capability-aware allowlist.
        return in_array($layout, self::allowedLayouts($capabilities), true);
    }

    /**
     * Build a localized message when layout validation fails.
     *
     * @since 3.0.0
     * @param string                   $layout       Rejected layout slug (may be empty).
     * @param array<string, bool>|null $capabilities Optional pre-built capability map.
     * @return string User-facing guidance for the Setup Wizard or OnPage AI UI.
     */
    public static function invalidLayoutMessage(string $layout, ?array $capabilities = null): string
    {
        // Normalize inputs for branching copy.
        $layout = sanitize_key($layout);
        $capabilities = is_array($capabilities) ? $capabilities : self::clientCapabilities();

        // Inline without Pro needs a distinct recovery hint (convert or activate Pro).
        if ('inline' === $layout && empty($capabilities['notifal_pro'])) {
            return __(
                'Inline layouts need an active Notifal Pro license. Choose popup, toast, or top bar, or activate Pro and try again.',
                'notifal'
            );
        }

        // Pro sites may legally request inline alongside floating layouts.
        if (!empty($capabilities['notifal_pro'])) {
            return __(
                'Choose a display layout (popup, toast, top bar, or inline) before generating.',
                'notifal'
            );
        }

        // Default free-plan copy matches legacy gateway messaging.
        return __(
            'Choose a display layout (popup, toast, or top bar) before generating.',
            'notifal'
        );
    }
}
