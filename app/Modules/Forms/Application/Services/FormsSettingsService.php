<?php

namespace Notifal\Modules\Forms\Application\Services;

use Notifal\Domain\Settings\Constants\SettingsKeys;
use Notifal\Domain\Settings\Services\SettingsService;

defined('ABSPATH') || exit;

/**
 * Reads and interprets Forms module settings (kill switch, captcha, rate limit).
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class FormsSettingsService
{
    /**
     * Settings service.
     *
     * @var SettingsService
     */
    private $settingsService;

    /**
     * Constructor.
     *
     * @param SettingsService $settingsService Core settings service.
     * @since 3.0.0
     */
    public function __construct(SettingsService $settingsService)
    {
        $this->settingsService = $settingsService;
    }

    /**
     * Whether the Forms module is enabled.
     *
     * @return bool
     * @since 3.0.0
     */
    public function isEnabled(): bool
    {
        // Prefer an explicit stored value when present.
        $value = $this->settingsService->get(
            SettingsKeys::FORMS_ENABLED,
            true
        );

        // Accept bool/int/string values from option storage.
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            if ($normalized === '' || $normalized === '0' || $normalized === 'false' || $normalized === 'off' || $normalized === 'no') {
                return false;
            }

            if ($normalized === '1' || $normalized === 'true' || $normalized === 'on' || $normalized === 'yes') {
                return true;
            }
        }

        return (bool) $value;
    }

    /**
     * Whether honeypot protection is enabled.
     *
     * @return bool
     * @since 3.0.0
     */
    public function isHoneypotEnabled(): bool
    {
        return (bool) $this->settingsService->get(
            SettingsKeys::FORMS_HONEYPOT_ENABLED,
            true
        );
    }

    /**
     * Rate limit: max submissions per window.
     *
     * @return int
     * @since 3.0.0
     */
    public function getRateLimitMax(): int
    {
        $max = (int) $this->settingsService->get(
            SettingsKeys::FORMS_RATE_LIMIT_MAX,
            5
        );

        return max(1, min(100, $max));
    }

    /**
     * Rate limit window in seconds.
     *
     * @return int
     * @since 3.0.0
     */
    public function getRateLimitWindowSeconds(): int
    {
        $window = (int) $this->settingsService->get(
            SettingsKeys::FORMS_RATE_LIMIT_WINDOW,
            300
        );

        return max(30, min(86400, $window));
    }

    /**
     * Captcha provider key from settings.
     *
     * @return string none|google_recaptcha_v2|google_recaptcha_v3|cloudflare_turnstile
     * @since 3.0.0
     */
    public function getCaptchaProvider(): string
    {
        $provider = (string) $this->settingsService->get(
            SettingsKeys::FORMS_CAPTCHA_PROVIDER,
            'none'
        );

        $allowed = [
            'none',
            'google_recaptcha_v2',
            'google_recaptcha_v3',
            'cloudflare_turnstile',
        ];

        return in_array($provider, $allowed, true) ? $provider : 'none';
    }

    /**
     * Public captcha site key (safe for frontend).
     *
     * @return string
     * @since 3.0.0
     */
    public function getCaptchaSiteKey(): string
    {
        return (string) $this->settingsService->get(
            SettingsKeys::FORMS_CAPTCHA_SITE_KEY,
            ''
        );
    }

    /**
     * Captcha secret key (server only, never localize).
     *
     * @return string
     * @since 3.0.0
     */
    public function getCaptchaSecretKey(): string
    {
        return (string) $this->settingsService->get(
            SettingsKeys::FORMS_CAPTCHA_SECRET_KEY,
            ''
        );
    }

    /**
     * Vendor script URL for the configured captcha provider.
     *
     * Returns an empty string when captcha is off or a required site key is missing.
     *
     * @return string Absolute HTTPS URL or empty string.
     * @since 3.0.0
     */
    public function getCaptchaVendorScriptUrl(): string
    {
        // Read the allowlisted provider from Forms settings.
        $provider = $this->getCaptchaProvider();

        // Google checkbox / explicit render path (v2).
        if ($provider === 'google_recaptcha_v2') {
            return 'https://www.google.com/recaptcha/api.js?render=explicit';
        }

        // Google invisible score path (v3) requires the site key in the script URL.
        if ($provider === 'google_recaptcha_v3') {
            $siteKey = $this->getCaptchaSiteKey();
            if ($siteKey === '') {
                return '';
            }

            return 'https://www.google.com/recaptcha/api.js?render=' . rawurlencode($siteKey);
        }

        // Cloudflare Turnstile explicit render path.
        if ($provider === 'cloudflare_turnstile') {
            return 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
        }

        // Provider is none or unknown: no vendor script.
        return '';
    }

    /**
     * WordPress script handle for the configured captcha vendor.
     *
     * @return string Handle or empty when no vendor script is needed.
     * @since 3.0.0
     */
    public function getCaptchaVendorScriptHandle(): string
    {
        // Map provider to a stable enqueue handle used by OnPage asset filtering.
        $provider = $this->getCaptchaProvider();

        if ($provider === 'google_recaptcha_v2' || $provider === 'google_recaptcha_v3') {
            return 'notifal-google-recaptcha';
        }

        if ($provider === 'cloudflare_turnstile') {
            return 'notifal-turnstile';
        }

        return '';
    }

    /**
     * Default admin notification email for form submissions.
     *
     * @return string
     * @since 3.0.0
     */
    public function getDefaultAdminEmail(): string
    {
        $email = (string) $this->settingsService->get(
            SettingsKeys::FORMS_DEFAULT_ADMIN_EMAIL,
            ''
        );

        if ($email !== '' && is_email($email)) {
            return $email;
        }

        $fallback = get_option('admin_email');

        return is_email((string) $fallback) ? (string) $fallback : '';
    }

    /**
     * All Forms settings with defaults for admin UI.
     *
     * @return array<string, mixed>
     * @since 3.0.0
     */
    public function getAll(): array
    {
        $defaults = SettingsKeys::getDefaultFormsSettings();
        $settings = [];

        foreach ($defaults as $key => $defaultValue) {
            $settings[$key] = $this->settingsService->get($key, $defaultValue);
        }

        return $settings;
    }

    /**
     * Whether Forms settings have a captcha provider selected (not none).
     *
     * Used to show per-form skip controls in admin / HTML Builder.
     *
     * @return bool
     * @since 3.0.0
     */
    public function isGlobalCaptchaEnabled(): bool
    {
        // Provider alone drives the admin UI; keys still gate frontend mounts.
        return $this->getCaptchaProvider() !== 'none';
    }

    /**
     * Whether captcha verification is required for a form captcha mode.
     *
     * @param string $formCaptchaMode Form data-captcha value.
     * @return bool
     * @since 3.0.0
     */
    public function isCaptchaRequired(string $formCaptchaMode = 'inherit'): bool
    {
        $mode = strtolower($formCaptchaMode);

        if ($mode === 'none') {
            return false;
        }

        $provider = $this->getCaptchaProvider();

        if ($provider === 'none') {
            return false;
        }

        if ($mode === 'google' && strpos($provider, 'google_') !== 0) {
            return false;
        }

        if ($mode === 'turnstile' && $provider !== 'cloudflare_turnstile') {
            return false;
        }

        return $this->getCaptchaSiteKey() !== '' && $this->getCaptchaSecretKey() !== '';
    }
}
