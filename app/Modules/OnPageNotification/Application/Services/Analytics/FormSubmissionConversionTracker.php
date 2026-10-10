<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Analytics;

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Modules\Forms\Infrastructure\WordPress\Repositories\SubmissionRepository;
use Notifal\Modules\OnPageNotification\Helpers\AnalyticsHelper;
use Notifal\Modules\OnPageNotification\Infrastructure\WordPress\Repositories\DatabaseRepository;

defined('ABSPATH') || exit;

/**
 * Records OnPage analytics conversions when visitors submit embedded Notifal forms.
 *
 * Runs on all site types. On ecommerce, form conversions add to order conversions in totals
 * (separate storage). Each successful submission with a notification context increments
 * daily stats and stores a conversion tracking event. *
 * @package Notifal\Modules\OnPageNotification\Application\Services\Analytics
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class FormSubmissionConversionTracker
{
    /**
     * Database repository for analytics tables.
     *
     * @var DatabaseRepository
     * @since 3.0.0
     */
    private DatabaseRepository $databaseRepository;

    /**
     * Form submissions repository for loading stored rows.
     *
     * @var SubmissionRepository
     * @since 3.0.0
     */
    private SubmissionRepository $submissionRepository;

    /**
     * Whether WordPress hooks were already registered.
     *
     * @var bool
     * @since 3.0.0
     */
    private static bool $hooksRegistered = false;

    /**
     * Constructor.
     *
     * @since 3.0.0
     */
    public function __construct()
    {
        // Resolve analytics repository from the plugin container.
        $this->databaseRepository = notifal_app(DatabaseRepository::class);

        // Forms repository is instantiated directly (not container-bound).
        $this->submissionRepository = new SubmissionRepository();
    }

    /**
     * Register WordPress hooks for form submission conversion tracking.
     *
     * @return void
     * @since 3.0.0
     */
    public static function register(): void
    {
        // Skip duplicate hook registration within the same request.
        if (self::$hooksRegistered) {
            return;
        }

        // Skip when form submissions are not part of conversion metrics (filterable via countsFormConversions).
        if (! AnalyticsHelper::countsFormConversions()) {
            return;
        }
        // Listen after a submission is stored successfully.
        add_action(ActionHooks::FORMS_SUBMIT_AFTER, [notifal_app(self::class), 'handleFormSubmitAfter'], 10, 3);

        self::$hooksRegistered = true;
    }

    /**
     * Handle a successful form submission and record a conversion when tied to a notification.
     *
     * @param int                  $submissionId Stored submission ID.
     * @param array<string, mixed> $schema       Parsed form schema.
     * @param array<string, mixed> $fields       Sanitized field values.
     * @return void
     * @since 3.0.0
     */
    public function handleFormSubmitAfter(int $submissionId, array $schema, array $fields): void
    {
        // Ignore invalid submission IDs.
        if ($submissionId <= 0) {
            return;
        }

        // Load the persisted submission row for notification context.
        $submission = $this->submissionRepository->get($submissionId);

        if (! is_array($submission)) {
            return;
        }

        // Require a notification ID so the conversion maps to OnPage analytics.
        $notificationId = isset($submission['notification_id']) ? absint($submission['notification_id']) : 0;

        if ($notificationId <= 0) {
            return;
        }

        // Increment aggregated daily conversion counters.
        $date = current_time('Y-m-d');
        $updated = $this->databaseRepository->updateDailyStats($notificationId, 'conversion', $date);

        if (! $updated) {
            return;
        }

        // Persist a raw conversion event for charts and exports.
        $userId = get_current_user_id();
        $userAgent = isset($_SERVER['HTTP_USER_AGENT'])
            ? sanitize_text_field(wp_unslash((string) $_SERVER['HTTP_USER_AGENT']))
            : '';
        $pageUrl = isset($submission['page_url']) ? esc_url_raw((string) $submission['page_url']) : '';

        $this->databaseRepository->storeTrackingEvent(
            [
                'notification_id' => $notificationId,
                'event_type'      => 'conversion',
                'user_id'         => $userId,
                'timestamp'       => current_time('timestamp'),
                'user_agent'      => $userAgent,
                'referrer'        => '',
                'page_url'        => $pageUrl,
                'ip_address'      => '',
                'session_id'      => '',
                'device_type'     => 'desktop',
                'campaign_id'     => 0,
            ]
        );

        // Update per-user stats when the submitter is logged in.
        if ($userId > 0) {
            $this->databaseRepository->updateUserStats($notificationId, $userId, 'conversion');
        }

        /**
         * Fires after recording a form-based OnPage conversion.
         *
         * @param array<string, mixed> $payload Conversion context.
         * @since 3.0.0
         */
        do_action(
            ActionHooks::ONPAGE_CONVERSION_RECORDED,
            [
                'notification_id'      => $notificationId,
                'submission_id'        => $submissionId,
                'attribution_type'     => 'form_submission',
                'conversion_timestamp' => current_time('mysql'),
                'user_id'              => $userId,
            ]
        );
    }
}
