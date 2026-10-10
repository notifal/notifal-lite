<?php

namespace Notifal\Modules\Ai\Domain;

defined('ABSPATH') || exit;

/**
 * Allowed hosted AI generation feature slugs.
 *
 * @since 3.0.0
 * @updated 3.0.0 Add standalone Form CPT create/edit (`form`).
 * @author Hossein <hossein@notifal.com>
 */
class AiFeature
{
    /**
     * OnPage notification full import generation.
     *
     * @var string
     */
    public const ONPAGE_NOTIFICATION = 'onpage_notification';

    /**
     * HTML Builder template create and edit generation.
     *
     * @var string
     */
    public const HTML_TEMPLATE = 'html_template';

    /**
     * Standalone Notifal Form CPT create and edit generation.
     *
     * @var string
     */
    public const FORM = 'form';

    /**
     * Setup Wizard public site analysis generation.
     *
     * @var string
     */
    public const WIZARD_SITE_ANALYSIS = 'wizard_site_analysis';

    /**
     * Legacy HTML Builder edit slug (maps to {@see HTML_TEMPLATE}).
     *
     * @deprecated 3.0.0 Use html_template with intent.existing_html instead.
     * @var string
     */
    public const HTML_TEMPLATE_EDIT = 'html_template_edit';

    /**
     * Return all supported feature slugs.
     *
     * @since 3.0.0
     * @return string[]
     */
    public static function all(): array
    {
        // Keep the allowlist explicit for validation and reporting.
        return [
            self::ONPAGE_NOTIFICATION,
            self::HTML_TEMPLATE,
            self::FORM,
            self::WIZARD_SITE_ANALYSIS,
        ];
    }

    /**
     * Normalize legacy feature slugs to the current gateway contract.
     *
     * @since 3.0.0
     * @param string $feature Raw feature slug from the client.
     * @return string Canonical feature slug.
     */
    public static function normalize(string $feature): string
    {
        // Map the deprecated edit slug onto the unified html_template feature.
        $feature = sanitize_key($feature);
        if (self::HTML_TEMPLATE_EDIT === $feature) {
            return self::HTML_TEMPLATE;
        }

        // Return the sanitized slug unchanged for supported features.
        return $feature;
    }

    /**
     * Validate one feature slug.
     *
     * @since 3.0.0
     * @param string $feature Feature slug candidate.
     * @return bool True when the slug is supported.
     */
    public static function isValid(string $feature): bool
    {
        // Accept legacy edit slugs and compare against the canonical allowlist.
        $normalized = self::normalize($feature);

        return in_array($normalized, self::all(), true);
    }
}
