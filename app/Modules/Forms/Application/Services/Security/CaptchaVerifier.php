<?php

namespace Notifal\Modules\Forms\Application\Services\Security;

use Notifal\Modules\Forms\Application\Services\FormsSettingsService;

defined('ABSPATH') || exit;

/**
 * Server-side captcha verification (reCAPTCHA / Turnstile). Fail closed when required.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class CaptchaVerifier
{
    /**
     * Forms settings.
     *
     * @var FormsSettingsService
     */
    private $settings;

    /**
     * @param FormsSettingsService $settings Settings reader.
     * @since 3.0.0
     */
    public function __construct(FormsSettingsService $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Verify a captcha response token when required.
     *
     * @param string $responseToken Client captcha token.
     * @param string $formCaptchaMode Form data-captcha mode.
     * @return bool
     * @since 3.0.0
     */
    public function verify(string $responseToken, string $formCaptchaMode = 'inherit'): bool
    {
        if (!$this->settings->isCaptchaRequired($formCaptchaMode)) {
            return true;
        }

        if ($responseToken === '') {
            return false;
        }

        $provider = $this->settings->getCaptchaProvider();
        $secret = $this->settings->getCaptchaSecretKey();

        if ($secret === '') {
            return false;
        }

        if ($provider === 'cloudflare_turnstile') {
            return $this->verifyTurnstile($responseToken, $secret);
        }

        if (strpos($provider, 'google_') === 0) {
            return $this->verifyRecaptcha($responseToken, $secret);
        }

        return false;
    }

    /**
     * @param string $token  Response token.
     * @param string $secret Secret key.
     * @return bool
     */
    private function verifyRecaptcha(string $token, string $secret): bool
    {
        $response = wp_remote_post(
            'https://www.google.com/recaptcha/api/siteverify',
            [
                'timeout' => 8,
                'body'    => [
                    'secret'   => $secret,
                    'response' => $token,
                ],
            ]
        );

        if (is_wp_error($response)) {
            return false;
        }

        $body = json_decode((string) wp_remote_retrieve_body($response), true);

        return is_array($body) && !empty($body['success']);
    }

    /**
     * @param string $token  Response token.
     * @param string $secret Secret key.
     * @return bool
     */
    private function verifyTurnstile(string $token, string $secret): bool
    {
        $response = wp_remote_post(
            'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            [
                'timeout' => 8,
                'body'    => [
                    'secret'   => $secret,
                    'response' => $token,
                ],
            ]
        );

        if (is_wp_error($response)) {
            return false;
        }

        $body = json_decode((string) wp_remote_retrieve_body($response), true);

        return is_array($body) && !empty($body['success']);
    }
}
