<?php

namespace Notifal\Modules\Ai\Domain;

defined('ABSPATH') || exit;

/**
 * Maps stable gateway error codes to user-safe admin messages.
 *
 * @since 3.0.0
 * @updated 3.0.0 Map not_accepted_output like invalid_output for client messaging.
 * @updated 3.0.0 Clarify generation_invalid_layout copy for Pro inline layouts.
 * @updated 3.0.0 Add specific messages for input validation field failures.
 * @updated 3.0.0 Map site_not_ready / sample_site readiness rejections.
 * @author Hossein <hossein@notifal.com>
 */
class GatewayErrorMapper
{
    /**
     * Translate one gateway error code into a localized admin message.
     *
     * @since 3.0.0
     * @param string $code Stable gateway error code.
     * @param string $fallback Fallback message when the code is unknown.
     * @return string Localized user-facing message.
     */
    public static function message(string $code, string $fallback = ''): string
    {
        // Normalize the incoming code for lookup.
        $code = sanitize_key($code);

        // Resolve a curated message map keyed by gateway codes.
        $messages = self::messages();

        // Return the mapped message when available.
        if (isset($messages[$code])) {
            return $messages[$code];
        }

        // Use the supplied fallback when the gateway provided a safe message.
        if ($fallback !== '') {
            return $fallback;
        }

        // Fall back to a generic retry message.
        return __('Something went wrong. Please try again.', 'notifal');
    }

    /**
     * Determine whether the error should prompt the user to reconnect.
     *
     * @since 3.0.0
     * @param string $code Stable gateway error code.
     * @return bool True when a fresh account connection is required.
     */
    public static function requiresReconnect(string $code): bool
    {
        // Normalize the code before comparison.
        $code = sanitize_key($code);

        // Treat credential and disclosure failures as reconnect scenarios.
        return in_array(
            $code,
            [
                'account_connection_required',
                'installation_not_found',
                'invalid_access_token',
                'access_token_expired',
                'invalid_refresh_token',
                'refresh_token_reuse',
                'disclosure_required',
            ],
            true
        );
    }

    /**
     * Determine whether the error represents a quota exhaustion state.
     *
     * @since 3.0.0
     * @param string $code Stable gateway error code.
     * @return bool True when monthly allowance is exhausted.
     */
    public static function isQuotaExceeded(string $code): bool
    {
        // Compare against the canonical quota error slug.
        return sanitize_key($code) === 'quota_exceeded';
    }

    /**
     * Resolve related wizard field slugs for one error code when the payload omits them.
     *
     * @since 3.0.0
     * @param string $code Stable gateway or local error code.
     * @return string[] Related intent field slugs.
     */
    public static function fieldsForCode(string $code): array
    {
        // Normalize the code before lookup.
        $code = sanitize_key($code);

        // Map known validation codes onto the wizard inputs users can fix.
        $map = [
            'generation_description_too_short' => ['description'],
            'generation_description_too_long'  => ['description'],
            'generation_prompt_too_large'      => ['description'],
            'generation_instruction_too_short' => ['instruction'],
            'generation_instruction_too_long'  => ['instruction'],
            'generation_invalid_layout'        => ['layout'],
            'unsupported_layout'               => ['layout'],
            'generation_images_invalid'        => ['images'],
            'generation_reference_html_too_long' => ['reference_html'],
            'generation_existing_html_invalid' => ['existing_html'],
            'conversation_too_long'            => ['conversation'],
            'generation_site_content_length'   => ['site_content'],
        ];

        // Return the mapped fields when available.
        return $map[$code] ?? [];
    }

    /**
     * Return the curated user-facing message map.
     *
     * @since 3.0.0
     * @return array<string, string> Gateway code to message map.
     */
    private static function messages(): array
    {
        // Keep messages concise and non-technical for admin UX.
        return [
            'invalid_email'               => __('Enter a valid email address.', 'notifal'),
            'disclosure_required'         => __('Please accept the Terms and Conditions to continue.', 'notifal'),
            'rate_limited'                => __('Too many requests. Please wait a moment and try again.', 'notifal'),
            'resend_cooldown'             => __('Please wait before requesting another verification code.', 'notifal'),
            'code_delivery_failed'        => __('We could not send the verification code. Try again in a moment.', 'notifal'),
            'invalid_or_expired_code'     => __('That verification code is invalid or has expired.', 'notifal'),
            'account_connection_required' => __('Connect your Notifal account to use hosted AI generation.', 'notifal'),
            'quota_exceeded'              => __('Your monthly AI generation allowance has been used.', 'notifal'),
            'generation_busy'             => __('Another generation is already in progress. Please wait.', 'notifal'),
            'provider_unavailable'        => __('Hosted AI generation is temporarily unavailable.', 'notifal'),
            'generation_timeout'          => __('Generation took too long. You can retry without losing your choices.', 'notifal'),
            'invalid_output'              => __('The generated result did not pass validation. Please try again.', 'notifal'),
            'not_accepted_output'         => __('The generated result did not pass validation. Please try again.', 'notifal'),
            'invalid_request'             => __('Some inputs were invalid. Review the highlighted fields and try again.', 'notifal'),
            'conversation_too_long'       => __('This chat is too long. Start a new chat to continue with Noti.', 'notifal'),
            'request_out_of_scope'        => __('I am Noti, the Notifal AI assistant. I can only help with Notifal templates, notifications, forms, and product questions.', 'notifal'),
            'generation_description_too_short' => __('Your prompt is too short. Describe the popup you want in at least one full sentence, then try again.', 'notifal'),
            'generation_description_too_long' => __('Your prompt is too long. Shorten it or split the details across a few chat messages, then try again.', 'notifal'),
            'generation_instruction_too_short' => __('Describe the change you want (at least 5 characters).', 'notifal'),
            'generation_instruction_too_long' => __('Your edit request is too long. Shorten it or split the details across a few chat messages, then try again.', 'notifal'),
            'generation_reference_html_too_long' => __('Your reference HTML is too large. Shorten the pasted markup or CSS and try again.', 'notifal'),
            'generation_existing_html_invalid' => __('The current template HTML is missing or too large for this edit. Open the builder and try again.', 'notifal'),
            'generation_images_invalid'   => __('One or more reference images could not be used. Remove them or attach JPEG, PNG, WebP, or GIF files under 2 MB, then try again.', 'notifal'),
            'generation_prompt_too_large' => __('Your request is too large. Shorten the prompt or remove a reference image, then try again.', 'notifal'),
            'generation_unsupported_feature' => __('This AI feature is not supported on your Notifal version.', 'notifal'),
            'generation_site_content_length' => __('We could not read enough public site content for analysis. Check that your home page is reachable and try again.', 'notifal'),
            'site_not_ready'              => __('We noticed your site content is not ready yet or still looks like sample or placeholder data. Please prepare real site content and analyze again so we can actually help.', 'notifal'),
            'sample_site'                 => __('We noticed your site content is not ready yet or still looks like sample or placeholder data. Please prepare real site content and analyze again so we can actually help.', 'notifal'),
            'generation_contract_mismatch'    => __('Notifal AI could not start this request because versions are out of sync. Update Notifal to the latest version and try again.', 'notifal'),
            'generation_invalid_layout'   => OnPageDisplayLayoutSupport::invalidLayoutMessage('', null),
            'unsupported_layout'          => OnPageDisplayLayoutSupport::invalidLayoutMessage('', null),
            'generation_intent_unknown_fields' => __('Some wizard fields are not accepted by Notifal AI. Update Notifal to the latest version or simplify your choices and try again.', 'notifal'),
            'scope_denied'                => __('This site does not have permission for that action.', 'notifal'),
            'generation_not_found'        => __('That generation could not be found.', 'notifal'),
            'generation_storage_failed'   => __('AI generation could not be stored. Please try again.', 'notifal'),
            'generation_payload_missing'  => __('AI generation output is not ready yet. Please try again.', 'notifal'),
            'gateway_error'               => __('The AI service could not complete your request.', 'notifal'),
            'gateway_unreachable'         => __('Could not reach Notifal AI right now. Please try again.', 'notifal'),
            'invalid_gateway_response'    => __('The AI service returned an unexpected response. Please try again.', 'notifal'),
            'not_connected'               => __('Connect your Notifal account before installing Notifal Pro.', 'notifal'),
            'trial_exists'                => __('Your Notifal account already used a free Pro license (active or expired). Purchase a license to use Notifal Pro.', 'notifal'),
            'domain_trial_used'           => __('This site already used a free Pro license (active or expired). Purchase a license to use Notifal Pro.', 'notifal'),
            'trial_unavailable'           => __('Free Notifal Pro setup is temporarily unavailable. Please try again shortly.', 'notifal'),
            'trial_failed'                => __('Could not create your Notifal Pro license. Please try again.', 'notifal'),
            'install_proof_missing'       => __('Notifal Pro install details are missing. Please accept Pro setup again.', 'notifal'),
        ];
    }
}
