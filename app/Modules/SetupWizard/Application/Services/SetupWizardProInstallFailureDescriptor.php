<?php
namespace Notifal\Modules\SetupWizard\Application\Services;
defined('ABSPATH') || exit;
/**
 * Builds human-readable Notifal Pro install failure messages from upgrader results.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class SetupWizardProInstallFailureDescriptor
{
    /**
     * Describe an install failure using WP_Error, upgrader result, and skin messages.
     *
     * @since 3.0.0
     * @param mixed                      $installResult Raw Plugin_Upgrader::install() return value.
     * @param \Plugin_Upgrader|null      $upgrader      Upgrader instance after the install attempt.
     * @param \WP_Upgrader_Skin|null     $skin          Skin used for the install attempt.
     * @return array{message:string,code:string,detail:string} Structured failure description.
     */
    public function describe($installResult, $upgrader = null, $skin = null): array
    {
        // Prefer structured WP_Error payloads from the install return value.
        if (is_wp_error($installResult)) {
            return $this->fromWpError($installResult, 'install_result');
        }
        // Plugin_Upgrader stores richer failure data on $result after a soft false/null return.
        if ($upgrader instanceof \Plugin_Upgrader && is_wp_error($upgrader->result)) {
            return $this->fromWpError($upgrader->result, 'upgrader_result');
        }
        // WP_Ajax_Upgrader_Skin keeps explicit error objects that Automatic_Upgrader_Skin drops.
        if ($skin instanceof \WP_Ajax_Upgrader_Skin) {
            $skinErrors = $skin->get_errors();
            if (is_wp_error($skinErrors) && $skinErrors->has_errors()) {
                return $this->fromWpError($skinErrors, 'skin_errors');
            }
            $skinMessage = sanitize_text_field(wp_strip_all_tags((string) $skin->get_error_messages()));
            if ('' !== $skinMessage) {
                return [
                    'message' => $skinMessage,
                    'code'    => 'skin_message',
                    'detail'  => $skinMessage,
                ];
            }
        }
        // Reuse the last upgrader feedback line when WordPress only returned a soft false/null.
        $feedback = $this->lastSkinFeedback($skin);
        if ('' !== $feedback) {
            return [
                'message' => $feedback,
                'code'    => 'skin_feedback',
                'detail'  => $feedback,
            ];
        }
        // Soft false/null usually means filesystem credentials or a blocked FS connection.
        $fsDetail = $this->filesystemDetail();
        return [
            'message' => sanitize_text_field(
                __('Plugin install failed because WordPress could not write plugin files. Please verify the plugins directory is writable, then try again or install manually.', 'notifal')
            ),
            'code'    => 'filesystem_soft_fail',
            'detail'  => $fsDetail,
        ];
    }
    /**
     * Return true when a failure description represents a destination folder conflict.
     *
     * @since 3.0.0
     * @param array{message?:string,code?:string,detail?:string} $described Described failure payload.
     * @return bool
     */
    public function isFolderExistsDescription(array $described): bool
    {
        // Prefer the stable WordPress error code when present.
        if ('folder_exists' === sanitize_key((string) ($described['code'] ?? ''))) {
            return true;
        }
        // Fall back to the well-known WordPress message text for soft-null upgrader returns.
        $message = strtolower((string) ($described['message'] ?? ''));
        return false !== strpos($message, 'destination folder already exists');
    }
    /**
     * Normalize a WP_Error into the shared failure payload shape.
     *
     * @since 3.0.0
     * @param \WP_Error $error  Structured WordPress error.
     * @param string    $source Source label for logging (install_result, upgrader_result, skin_errors).
     * @return array{message:string,code:string,detail:string}
     */
    private function fromWpError(\WP_Error $error, string $source): array
    {
        // Keep the primary WordPress error message for the UI.
        $message = sanitize_text_field($error->get_error_message());
        $code = sanitize_key((string) $error->get_error_code());
        // Attach optional error data so logs can show destination paths and similar context.
        $data = $error->get_error_data($code);
        $detail = is_string($data) ? sanitize_text_field($data) : '';
        if ('' === $message) {
            $message = __('Plugin install failed. Please try again or install Notifal Pro manually.', 'notifal');
        }
        return [
            'message' => $message,
            'code'    => '' !== $code ? $code : sanitize_key($source),
            'detail'  => $detail,
        ];
    }
    /**
     * Return the last non-empty upgrader skin feedback message.
     *
     * @since 3.0.0
     * @param \WP_Upgrader_Skin|null $skin Upgrader skin instance.
     * @return string
     */
    private function lastSkinFeedback($skin): string
    {
        // Automatic and Ajax skins expose captured feedback via get_upgrade_messages().
        if (!$skin instanceof \Automatic_Upgrader_Skin) {
            return '';
        }
        $messages = $skin->get_upgrade_messages();
        if (!is_array($messages) || empty($messages)) {
            return '';
        }
        $last = end($messages);
        return sanitize_text_field(wp_strip_all_tags((string) $last));
    }
    /**
     * Describe the PHP type of an upgrader return value for logs.
     *
     * @since 3.0.0
     * @param mixed $installResult Upgrader return value.
     * @return string
     */
    public function describeResultType($installResult): string
    {
        // Prefer WP_Error codes when the upgrader returned a structured failure.
        if (is_wp_error($installResult)) {
            return 'wp_error:' . sanitize_key((string) $installResult->get_error_code());
        }
        // Soft failures are commonly null or false after an aborted filesystem connect.
        if (null === $installResult) {
            return 'null';
        }
        if (false === $installResult) {
            return 'false';
        }
        if (true === $installResult) {
            return 'true';
        }
        return sanitize_key(gettype($installResult));
    }
    /**
     * Build a short filesystem diagnostic string for logs and soft-fail details.
     *
     * @since 3.0.0
     * @return string
     */
    private function filesystemDetail(): string
    {
        // Report whether plugin installs are disabled by WordPress constants.
        if (defined('DISALLOW_FILE_MODS') && \DISALLOW_FILE_MODS) {
            return 'DISALLOW_FILE_MODS=1';
        }
        // Report plugins directory writability for local and shared-host debugging.
        $pluginsDir = (string) WP_PLUGIN_DIR;
        $writable = is_writable($pluginsDir) ? 'writable' : 'not_writable';
        return 'plugins_dir=' . $writable;
    }
}
