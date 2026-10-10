<?php

namespace Notifal\Modules\Ai\Presentation\Admin\Controllers\Ajax;

use Notifal\Modules\Ai\Application\Services\SiteProfileService;
use Notifal\Modules\Ai\Config\Constants;

defined('ABSPATH') || exit;

/**
 * AJAX boundary for the hosted AI site profile.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class AiSiteProfileAjaxController
{
    /**
     * Register WordPress AJAX handlers.
     *
     * @since 3.0.0
     * @return void
     */
    public static function register(): void
    {
        // Register the site profile save endpoint for authenticated editors.
        add_action('wp_ajax_notifal_ai_save_site_profile', [self::class, 'saveSiteProfile']);
    }

    /**
     * Save industry and/or brand color preferences for future generations.
     *
     * @since 3.0.0
     * @return void
     */
    public static function saveSiteProfile(): void
    {
        try {
            // Verify nonce and require editor capability for profile saves.
            notifal_verify_ajax_request(Constants::AJAX_NONCE_ACTION, 'edit_posts');

            // Read optional industry fields from the request.
            $industry = isset($_POST['industry']) ? wp_unslash($_POST['industry']) : '';
            $industrySlug = isset($_POST['industry_slug']) ? wp_unslash($_POST['industry_slug']) : '';
            $primaryColor = isset($_POST['primary_color']) ? wp_unslash($_POST['primary_color']) : '';
            $contactName = isset($_POST['contact_name']) ? wp_unslash($_POST['contact_name']) : '';
            $businessDescription = isset($_POST['business_description']) ? wp_unslash($_POST['business_description']) : '';

            // Brand colors may arrive as JSON; do not sanitize_text_field the raw JSON string.
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $brandColorsRaw = isset($_POST['brand_colors']) ? wp_unslash($_POST['brand_colors']) : '';

            // Cast scalar request fragments to strings for the service layer.
            $industry = is_string($industry) ? $industry : '';
            $industrySlug = is_string($industrySlug) ? $industrySlug : '';
            $primaryColor = is_string($primaryColor) ? $primaryColor : '';
            $brandColorsRaw = is_string($brandColorsRaw) ? $brandColorsRaw : '';
            $contactName = is_string($contactName) ? sanitize_text_field($contactName) : '';
            $businessDescription = is_string($businessDescription) ? sanitize_textarea_field($businessDescription) : '';

            // Decode optional brand color palette JSON.
            $brandColors = [];
            if ('' !== trim($brandColorsRaw)) {
                $decoded = json_decode($brandColorsRaw, true);
                $brandColors = is_array($decoded) ? $decoded : [];
            }

            $service = self::siteProfileService();
            $profile = $service->get();

            // Persist industry when provided by the one-time personalization step.
            if ('' !== trim($industry)) {
                $profile = $service->saveIndustry($industry, $industrySlug);
            }

            // Persist brand colors when provided after a generation preference change.
            if ('' !== trim($primaryColor) || !empty($brandColors)) {
                $resolvedPrimary = '' !== trim($primaryColor)
                    ? $primaryColor
                    : (string) ($brandColors[0] ?? '');
                $profile = $service->savePrimaryColor($resolvedPrimary, $brandColors);
            }

            // Persist the connected account greeting name when provided.
            if ('' !== trim($contactName)) {
                $profile = $service->saveContactName($contactName);
            }

            if ('' !== trim($businessDescription)) {
                $profile = $service->saveBusinessDescription($businessDescription);
            }

            // Require at least one supported profile field.
            if ('' === trim($industry) && '' === trim($primaryColor) && empty($brandColors) && '' === trim($contactName) && '' === trim($businessDescription)) {
                notifal_json_error(__('Please enter your industry or niche.', 'notifal'));
            }

            // Return the saved profile snapshot.
            notifal_json_success(['site_profile' => $profile]);
        } catch (\InvalidArgumentException $exception) {
            // Map known validation failures to user-facing copy.
            if ('invalid_primary_color' === $exception->getMessage()) {
                notifal_json_error(__('Please choose a valid primary color.', 'notifal'));
            }

            if ('invalid_contact_name' === $exception->getMessage()) {
                notifal_json_error(__('Please enter your name.', 'notifal'));
            }

            if ('invalid_business_description' === $exception->getMessage()) {
                notifal_json_error(__('Please tell us a bit about your business.', 'notifal'));
            }

            notifal_json_error(__('Please enter your industry or niche.', 'notifal'));
        } catch (\Throwable $exception) {
            notifal_json_error(__('An unexpected error occurred. Please try again.', 'notifal'));
        }
    }

    /**
     * Resolve the site profile service from the container.
     *
     * @since 3.0.0
     * @return SiteProfileService Site profile service instance.
     */
    private static function siteProfileService(): SiteProfileService
    {
        // Resolve the singleton service from the Notifal container.
        return notifal_app(SiteProfileService::class);
    }
}
