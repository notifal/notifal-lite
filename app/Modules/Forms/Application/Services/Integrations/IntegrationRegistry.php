<?php

namespace Notifal\Modules\Forms\Application\Services\Integrations;

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;

defined('ABSPATH') || exit;

/**
 * Dispatches submission data to registered integration adapters.
 *
 * Secrets come from Form CPT meta only, never from HTML or localize.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class IntegrationRegistry
{
    /**
     * Run enabled integrations for a submission.
     *
     * @param int                  $formId       Form CPT ID.
     * @param int                  $submissionId Submission ID.
     * @param array<string, mixed> $fields       Sanitized fields.
     * @param array<string, mixed> $meta         Form meta (secrets).
     * @return void
     * @since 3.0.0
     */
    public function dispatch(int $formId, int $submissionId, array $fields, array $meta): void
    {
        $adapters = [
            'webhook'   => WebhookIntegration::class,
            'mailchimp' => MailchimpIntegration::class,
        ];

        /**
         * Filter available form integration adapters.
         *
         * @param array<string, class-string> $adapters Adapter map.
         * @since 3.0.0
         */
        $adapters = (array) apply_filters(FilterHooks::FORMS_INTEGRATION_ADAPTERS, $adapters);

        foreach ($adapters as $key => $class) {
            if (!is_string($class) || !class_exists($class)) {
                continue;
            }

            $adapter = new $class();
            if (!method_exists($adapter, 'send')) {
                continue;
            }

            $adapter->send($formId, $submissionId, $fields, $meta);
        }

        /**
         * Fires after integration dispatch.
         *
         * @param int   $formId Form ID.
         * @param int   $submissionId Submission ID.
         * @param array $fields Fields.
         * @since 3.0.0
         */
        do_action(ActionHooks::FORMS_INTEGRATIONS_DISPATCHED, $formId, $submissionId, $fields);
    }
}
