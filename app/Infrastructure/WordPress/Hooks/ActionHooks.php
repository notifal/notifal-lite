<?php

namespace Notifal\Infrastructure\WordPress\Hooks;

defined('ABSPATH') || exit;

/**
 * Class ActionHooks
 * Centralized reference for Notifal plugin action hook names.
 * Intended for documentation, consistency, and IDE auto-completion.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class ActionHooks {

    // =========================================================================
    // 🔄 ADMIN ACTION HOOKS
    // =========================================================================


    /**
     * Fires when the plugin core class is initialized.
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const PLUGIN_INIT = 'notifal/initialized';

    /**
     * Fires after plugin is fully loaded and ready.
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const PLUGIN_READY = 'notifal/ready';

    /**
     * Fires on plugin activation.
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const PLUGIN_ACTIVATED = 'notifal/activated';

    /**
     * Fires on plugin deactivation.
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const PLUGIN_DEACTIVATED = 'notifal/deactivated';

    /**
     * Fires when plugin is uninstalled.
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const PLUGIN_UNINSTALLED = 'notifal/uninstalling';

    /**
     * Fires when deactivation feedback is submitted.
     *
     * @param array $feedback_data The submitted feedback data
     * @since 2.0.0
     */
    public const DEACTIVATION_FEEDBACK_SUBMITTED = 'notifal/deactivation/feedback_submitted';

    /**
     * Fires when deactivation feedback is skipped.
     *
     * @param array $site_data Basic site information
     * @since 2.0.0
     */
    public const DEACTIVATION_FEEDBACK_SKIPPED = 'notifal/deactivation/feedback_skipped';

    /**
     * Fires after post type for on-page notifications is registered.
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const POST_TYPE_REGISTERED = 'notifal/onpage_post_type/registered';


    /**
     * Fires before registering admin menu.
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const ADMIN_MENU_BEFORE = 'notifal/admin_menu/register/before';


    /**
     * Fires after registering admin menu.
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const ADMIN_MAIN_MENU_AFTER = 'notifal/admin_main_menu/register/after';

    /**
     * Fires before rendering a select field.
     * Dynamic part: field ID
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const FIELD_SELECT_BEFORE = 'notifal/field/select/before/';

    /**
     * Fires after rendering a select field.
     * Dynamic part: field ID
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const FIELD_SELECT_AFTER = 'notifal/field/select/after/';

    /**
     * Fires before rendering a text field.
     * Dynamic part: field ID
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const FIELD_TEXT_BEFORE = 'notifal/field/text/before/';

    /**
     * Fires after rendering a text field.
     * Dynamic part: field ID
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const FIELD_TEXT_AFTER = 'notifal/field/text/after/';

    /**
     * Fires before rendering a textarea field.
     * Dynamic part: field ID
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const FIELD_TEXTAREA_BEFORE = 'notifal/field/textarea/before/';

    /**
     * Fires after rendering a textarea field.
     * Dynamic part: field ID
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const FIELD_TEXTAREA_AFTER = 'notifal/field/textarea/after/';

    /**
     * Fires before rendering a date field.
     * Dynamic part: field ID
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const FIELD_DATE_BEFORE = 'notifal/field/date/before/';

    /**
     * Fires after rendering a date field.
     * Dynamic part: field ID
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const FIELD_DATE_AFTER = 'notifal/field/date/after/';

    /**
     * Fires before rendering a datetime field.
     * Dynamic part: field ID
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const FIELD_DATETIME_BEFORE = 'notifal/field/datetime/before/';

    /**
     * Fires after rendering a datetime field.
     * Dynamic part: field ID
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const FIELD_DATETIME_AFTER = 'notifal/field/datetime/after/';

    /**
     * Fires before rendering a toggle field.
     * Dynamic part: field ID
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const FIELD_TOGGLE_BEFORE = 'notifal/field/toggle/before/';

    /**
     * Fires after rendering a toggle field.
     * Dynamic part: field ID
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const FIELD_TOGGLE_AFTER = 'notifal/field/toggle/after/';

    /**
     * Fires before rendering any Notifal admin page content.
     *
     * This hook runs before the main content of any Notifal admin page,
     * allowing for elements like sticky menus to be rendered at the top of the page content.
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const ADMIN_PAGE_CONTENT_BEFORE = 'notifal/admin_page/content/before';

    /**
     * Fires before rendering On-Page Notification admin page.
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const ADMIN_ONPAGE_NOTIFICATIONS_BEFORE = 'notifal/admin_page/onpage_notifications/before';

    /**
     * Fires after rendering On-Page Notification admin page.
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const ADMIN_ONPAGE_NOTIFICATIONS_AFTER = 'notifal/admin_page/onpage_notifications/after';

    /**
     * Fires when a notification is saved successfully.
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const ONPAGE_NOTIFICATION_SAVED = 'notifal/onpage_notification/saved';

    /**
     * Fires when notification meta data is saved.
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const ONPAGE_NOTIFICATION_META_SAVED = 'notifal/onpage_notification/meta_saved';

    /**
     * Fires when a schedule start/end string could not be parsed during timing sanitization.
     *
     * @since 2.2.0
     * @author Hossein <hossein@notifal.com>
     */
    public const ONPAGE_TIMING_SCHEDULE_INCOMING_PARSE_FAILED = 'notifal/onpage/timing/schedule_incoming_parse_failed';

    /**
     * Fires after notification caches have been cleared.
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const ONPAGE_NOTIFICATION_CACHE_CLEARED = 'notifal/onpage_notification/cache_cleared';

    /**
     * Fires after Elementor caches are cleared for notifications.
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const ONPAGE_ELEMENTOR_CACHE_CLEARED = 'notifal/onpage_notification/elementor_cache_cleared';

    /**
     * Fires after order pool caches are cleared.
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const ONPAGE_ORDER_POOL_CACHE_CLEARED = 'notifal/onpage_notification/order_pool_cache_cleared';

    /**
     * Fires after product pool caches are cleared.
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const ONPAGE_PRODUCT_POOL_CACHE_CLEARED = 'notifal/onpage_notification/product_pool_cache_cleared';

    /**
     * Fires after frontend template cache is cleared.
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const ONPAGE_FRONTEND_TEMPLATE_CACHE_CLEARED = 'notifal/onpage_notification/frontend_template_cache_cleared';

    /**
     * Fires after WordPress object cache is cleared.
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const ONPAGE_WP_OBJECT_CACHE_CLEARED = 'notifal/onpage_notification/wp_object_cache_cleared';

    /**
     * Fires after all notification caches are manually cleared.
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const ONPAGE_ALL_CACHES_CLEARED = 'notifal/onpage_notification/all_caches_cleared';


    /**
     * Fires before rendering a specific admin OnPage tab.
     *
     * Usage: do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_BEFORE, $tab));
     *
     * Example: do_action('notifal/admin/onpage/products/before');
     *
     * @since 2.0.0
     */
    public const ADMIN_ONPAGE_TAB_BEFORE = 'notifal/admin/onpage/%s/before';

    /**
     * Fires after rendering a specific admin OnPage tab.
     *
     * Usage: do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_BEFORE, $tab));
     *
     * Example: do_action('notifal/admin/onpage/products/before');
     *
     * @since 2.0.0
     */
    public const ADMIN_ONPAGE_TAB_AFTER = 'notifal/admin/onpage/%s/after';

    /**
     * Fires before rendering a specific admin OnPage tab section.
     *
     * Usage: do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_SECTION_BEFORE, $tab, $section));
     *
     * Example: do_action('notifal/admin/onpage/appearance/device_visibility/before');
     *
     * @since 2.0.0
     */
    public const ADMIN_ONPAGE_TAB_SECTION_BEFORE = 'notifal/admin/onpage/%s/%s/before';

    /**
     * Fires after rendering a specific admin OnPage tab section.
     *
     * Usage: do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_SECTION_AFTER, $tab, $section));
     *
     * Example: do_action('notifal/admin/onpage/appearance/device_visibility/after');
     *
     * @since 2.0.0
     */
    public const ADMIN_ONPAGE_TAB_SECTION_AFTER = 'notifal/admin/onpage/%s/%s/after';

    /**
     * Fires when a campaign is saved successfully.
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const CAMPAIGN_SAVED = 'notifal/campaign/saved';

    /**
     * Fires when a campaign is deleted (trashed).
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const CAMPAIGN_DELETED = 'notifal/campaign/deleted';

    /**
     * Fires when campaign status has changed.
     *
     * @param int   $campaign_id   Campaign post ID.
     * @param array $previous_meta Previous `_notifal_campaign_settings` snapshot.
     * @param array $new_meta      Updated `_notifal_campaign_settings` snapshot.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const CAMPAIGN_STATUS_CHANGED = 'notifal/campaign/status_changed';

    /**
     * Fires after the hourly task that marks ended campaigns inactive completes.
     *
     * @param int $updated_count Campaigns updated in this run.
     * @since 2.2.0
     * @author Hossein <hossein@notifal.com>
     */
    public const CAMPAIGN_END_DATE_EXPIRY_CRON_COMPLETED = 'notifal/campaign/cron/end_date_expiry_completed';

    /**
     * Fires before rendering a specific admin Campaign tab.
     *
     * Dynamic part: tab identifier
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const ADMIN_CAMPAIGN_TAB_BEFORE = 'notifal/admin/campaign/tab/%s/before';

    /**
     * Fires after rendering a specific admin Campaign tab.
     *
     * Dynamic part: tab identifier
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const ADMIN_CAMPAIGN_TAB_AFTER = 'notifal/admin/campaign/tab/%s/after';

    /**
     * Fires before rendering a specific admin Campaign tab section.
     *
     * Dynamic parts: tab identifier, section identifier
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const ADMIN_CAMPAIGN_TAB_SECTION_BEFORE = 'notifal/admin/campaign/tab/%s/section/%s/before';

    /**
     * Fires after rendering a specific admin Campaign tab section.
     *
     * Dynamic parts: tab identifier, section identifier
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const ADMIN_CAMPAIGN_TAB_SECTION_AFTER = 'notifal/admin/campaign/tab/%s/section/%s/after';


    /**
     * Fires before rendering an AJAX-powered post selector field.
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const FIELD_AJAX_SEARCH_BEFORE = 'notifal/field/ajax_search/before';

    /**
     * Fires after rendering an AJAX-powered post selector field.
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const FIELD_AJAX_SEARCH_AFTER = 'notifal/field/ajax_search/after';


    /**
     * Fires before rendering a multi-select field.
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const FIELD_MULTI_SELECT_BEFORE = 'notifal/field/multi_select/before';

    /**
     * Fires after rendering a multi-select field.
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const FIELD_MULTI_SELECT_AFTER = 'notifal/field/multi_select/after';

    /**
     * Fires before rendering a multi-checkbox field.
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const FIELD_MULTI_CHECKBOX_BEFORE = 'notifal/field/multi_checkbox/before/';

    /**
     * Fires after rendering a multi-checkbox field.
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const FIELD_MULTI_CHECKBOX_AFTER = 'notifal/field/multi_checkbox/after/';

    /**
     * Fires before rendering a number input field.
     * Dynamic part: field ID
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const FIELD_NUMBER_BEFORE = 'notifal/field/number/before/';

    /**
     * Fires after rendering a number input field.
     * Dynamic part: field ID
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const FIELD_NUMBER_AFTER = 'notifal/field/number/after/';

    /**
     * Fires before rendering a color picker field.
     * Dynamic part: field ID
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const FIELD_COLOR_BEFORE = 'notifal/field/color/before/';

    /**
     * Fires after rendering a color picker field.
     * Dynamic part: field ID
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const FIELD_COLOR_AFTER = 'notifal/field/color/after/';

    /**
     * Fires before rendering a range slider field.
     * Dynamic part: field ID
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const FIELD_RANGE_BEFORE = 'notifal/field/range/before/';

    /**
     * Fires after rendering a range slider field.
     * Dynamic part: field ID
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const FIELD_RANGE_AFTER = 'notifal/field/range/after/';

    /**
     * Fires before rendering a media upload field.
     * Dynamic part: field ID
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com> <hossein@notifal.com>
     */
    public const FIELD_MEDIA_BEFORE = 'notifal/field/media/before/';

    /**
     * Fires after rendering a media upload field.
     * Dynamic part: field ID
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com> <hossein@notifal.com>
     */
    public const FIELD_MEDIA_AFTER = 'notifal/field/media/after/';


    /**
     * Fires before rendering Template admin page.
     *
     * @since 2.0.0
     */
    public const ADMIN_TEMPLATES_BEFORE = 'notifal/admin_page/templates/before';

    /**
     * Fires after rendering Template admin page.
     *
     * @since 2.0.0
     */
    public const ADMIN_TEMPLATES_AFTER = 'notifal/admin_page/templates/after';


    /**
     * Fires before the default action buttons (edit/view/delete) in each row.
     *
     * Useful for injecting extra action buttons specific to your post type.
     *
     * @param WP_Post $post The current post object
     *
     * @since 2.0.0
     */
    public const ADMIN_LIST_ACTIONS_BEFORE = 'notifal/admin/list/actions_before';

    /**
     * Fires after the default action buttons in each row.
     *
     * Can be used to add dropdowns, toggles, etc. at the end of the actions column.
     *
     * @param WP_Post $post The current post object
     *
     * @since 2.0.0
     */
    public const ADMIN_LIST_ACTIONS_AFTER = 'notifal/admin/list/actions_after';


    /**
     * Fires after rendering the entire list UI.
     *
     * Useful for injecting footer content or additional markup
     * after the BaseListView output ends.
     *
     * @since 2.0.0
     */
    public const ADMIN_LIST_AFTER_RENDER = 'notifal/admin/list/after_render';


    /**
     * Fires before rendering the entire list UI.
     *
     * Useful for injecting content, notices, or other markup
     * before the BaseListView output starts.
     *
     * @since 2.0.0
     */
    public const ADMIN_LIST_BEFORE_RENDER = 'notifal/admin/list/before_render';

    /**
     * Fires when templates are loaded via AJAX for the load more functionality.
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com> <hossein@notifal.com>
     */
    public const TEMPLATES_LOAD_MORE_BEFORE = 'notifal/templates/load_more/before';

    /**
     * Fires after templates are loaded via AJAX for the load more functionality.
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com> <hossein@notifal.com>
     */
    public const TEMPLATES_LOAD_MORE_AFTER = 'notifal/templates/load_more/after';

    /**
     * Fires when a custom bulk action is triggered from the admin list.
     *
     * Useful for extending the default bulk behavior (e.g., duplicate, export, etc.).
     *
     * @since 2.0.0
     * @param string $action    The action slug selected by user (e.g., 'delete', 'duplicate').
     * @param int[]  $ids       Array of selected post IDs.
     * @param string $postType  The post type this action is for.
     */
    public const ADMIN_LIST_HANDLE_BULK_ACTION = 'notifal/admin/list/handle_bulk_action';

    /**
     * Fires after a post has been duplicated.
     *
     * Useful for adding custom logic after duplication (e.g., updating meta, notifications, etc.).
     *
     * @since 2.0.0
     * @param int    $newPostId      The ID of the newly created post
     * @param int    $originalPostId The ID of the original post
     * @param string $postType       The post type
     */
    public const POST_DUPLICATED = 'notifal/post/duplicated';


    /**
     * Fires before enqueuing templates admin assets.
     *
     * @since 2.0.0
     */
    public const TEMPLATES_ADMIN_ASSETS_BEFORE = 'notifal/templates/admin/assets/before';

    /**
     * Fires after enqueuing templates admin assets.
     *
     * @since 2.0.0
     */
    public const TEMPLATES_ADMIN_ASSETS_AFTER = 'notifal/templates/admin/assets/after';


    /**
     * Fires after all Notifal Elementor widgets are registered.
     *
     * @since 2.0.0
     */
    public const ELEMENTOR_WIDGETS_REGISTERED = 'notifal/elementor/widgets/registered';


    /**
     * Fires before rendering a Notifal Elementor widget.
     *
     * The widget slug is appended to the hook dynamically.
     * Example: `notifal/elementor/widget/notifal-product-image/before`
     *
     * @param \Elementor\Widget_Base $widget The widget instance.
     *
     * @since 2.0.0
     */
    public const ELEMENTOR_WIDGET_RENDER_BEFORE = 'notifal/elementor/widget/%s/before';

    /**
     * Fires after rendering a Notifal Elementor widget.
     *
     * The widget slug is appended to the hook dynamically.
     * Example: `notifal/elementor/widget/notifal-product-image/after`
     *
     * @param \Elementor\Widget_Base $widget The widget instance.
     *
     * @since 2.0.0
     */
    public const ELEMENTOR_WIDGET_RENDER_AFTER = 'notifal/elementor/widget/%s/after';



    /**
     * Fires after a Notifal template is soft-deleted (moved to trash).
     *
     * @param int $template_id The ID of the template being trashed.
     * @since 2.0.0
     */
    public const TEMPLATE_TRASHED = 'notifal/template/trashed';

    /**
     * Fires after a Notifal template is permanently deleted.
     *
     * @param int    $template_id The ID of the deleted template.
     * @param string $status      The previous post status (e.g. 'trash' or 'publish').
     * @since 2.0.0
     */
    public const TEMPLATE_DELETED = 'notifal/template/deleted';

    /**
     * Fires after emptying the trash for Notifal templates.
     *
     * @param int[] $deleted_ids Array of deleted template IDs.
     * @since 2.0.0
     */
    public const TEMPLATE_TRASH_EMPTIED = 'notifal/template/trash_emptied';

    /**
     * Fires before rendering the template creation view.
     *
     * @param bool $is_modal Whether the view is rendered as a modal.
     * @since 2.0.0
     */
    public const TEMPLATE_CREATION_BEFORE = 'notifal/template/creation/before';

    /**
     * Fires after rendering the template creation view.
     *
     * @param bool $is_modal Whether the view was rendered as a modal.
     * @since 2.0.0
     */
    public const TEMPLATE_CREATION_AFTER = 'notifal/template/creation/after';

    /**
     * Fires after a new Notifal template is created (via admin route).
     *
     * @param int $template_id The newly created template post ID.
     * @since 2.0.0
     */
    public const TEMPLATE_CREATED = 'notifal/template/created';

    /**
     * Fires after shortcode-related context is initialised for rendering
     * a template in REST / AJAX context.
     *
     * This is typically used to bootstrap heavy subsystems that are
     * normally skipped outside a full frontend page load, such as
     * WooCommerce cart/session initialisation required by shortcodes
     * like [woocommerce_cart].
     *
     * @param \WP_Post $template The template post being rendered.
     * @since 2.0.3
     */
    public const TEMPLATE_RENDERER_SHORTCODE_CONTEXT = 'notifal/template_renderer/shortcode_context';

    /**
     * Fires after a Notifal template is duplicated.
     *
     * @param int $original_id The original template post ID.
     * @param int $new_id The newly duplicated template post ID.
     * @since 2.0.0
     */
    public const TEMPLATE_DUPLICATED = 'notifal/template/duplicated';


    /**
     * Fires after a Notifal onpage notification is soft-deleted (moved to trash).
     *
     * @param int $notification_id The ID of the notification being trashed.
     * @since 2.0.0
     */
    public const ONPAGE_NOTIFICATION_TRASHED = 'notifal/onpage_notification/trashed';

    /**
     * Fires after a Notifal onpage notification is permanently deleted.
     *
     * @param int    $notification_id The ID of the deleted notification.
     * @param string $status          The previous post status (e.g. 'trash' or 'publish').
     * @since 2.0.0
     */
    public const ONPAGE_NOTIFICATION_DELETED = 'notifal/onpage_notification/deleted';

    /**
     * Fires after a Notifal onpage notification is duplicated.
     *
     * @param int $original_id The original notification post ID.
     * @param int $new_id The newly duplicated notification post ID.
     * @since 2.0.0
     */
    public const ONPAGE_NOTIFICATION_DUPLICATED = 'notifal/onpage_notification/duplicated';

    /**
     * Fires after emptying the trash for Notifal onpage notifications.
     *
     * @param int $deleted_count The number of notifications that were deleted.
     * @since 2.0.0
     */
    public const ONPAGE_NOTIFICATIONS_TRASH_EMPTIED = 'notifal/onpage_notifications/trash_emptied';


    /**
     * Fires before rendering a template preview in the iframe route.
     *
     * @param WP_Post $post The template post object.
     * @since 2.0.0
     */
    public const TEMPLATE_PREVIEW_BEFORE = 'notifal/template/preview/before';

    /**
     * Fires after rendering a template preview in the iframe route.
     *
     * @param WP_Post $post The template post object.
     * @since 2.0.0
     */
    public const TEMPLATE_PREVIEW_AFTER = 'notifal/template/preview/after';

    /**
     * Fires before rendering the OnPage notification preview page.
     *
     * @param \WP_Post $post The notification post object.
     * @since 2.0.0
     */
    public const ONPAGE_PREVIEW_BEFORE = 'notifal/onpage/preview/before';

    /**
     * Fires after rendering the OnPage notification preview page.
     *
     * @param \WP_Post $post The notification post object.
     * @since 2.0.0
     */
    public const ONPAGE_PREVIEW_AFTER = 'notifal/onpage/preview/after';

    /**
     * Fires after templates are queried by builder.
     *
     * @param string   $builder
     * @param WP_Post[] $templates
     * @since 2.0.0
     */
    public const TEMPLATES_QUERIED_BY_BUILDER = 'notifal/templates/queried_by_builder';

    /**
     * Fires before templates are filtered via AJAX.
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const TEMPLATES_FILTER_BEFORE = 'notifal/templates/filter/before';

    /**
     * Fires after templates are filtered via AJAX.
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const TEMPLATES_FILTER_AFTER = 'notifal/templates/filter/after';

    /**
     * Fires before enqueuing admin scripts/styles for OnPage module.
     *
     * @param WP_Screen $screen The current screen object.
     * @since 2.0.0
     */
    public const ONPAGE_ADMIN_ASSETS_BEFORE_ENQUEUE = 'notifal/admin_onpage/assets/before_enqueue';

    /**
     * Fires after enqueuing admin scripts/styles for OnPage module.
     *
     * @param WP_Screen $screen The current screen object.
     * @since 2.0.0
     */
    public const ONPAGE_ADMIN_ASSETS_AFTER_ENQUEUE = 'notifal/admin_onpage/assets/after_enqueue';

    /**
     * Fires before enqueuing Notifal OnPage list admin assets.
     *
     * @since 2.0.0
     */
    public const ONPAGE_ADMIN_LIST_ASSETS_BEFORE_ENQUEUE = 'notifal/admin_onpage/list/assets/before_enqueue';

    /**
     * Fires after enqueuing Notifal OnPage list admin assets.
     *
     * @since 2.0.0
     */
    public const ONPAGE_ADMIN_LIST_ASSETS_AFTER_ENQUEUE = 'notifal/admin_onpage/list/assets/after_enqueue';

    /**
     * Fires before toggling OnPage notification status.
     *
     * @since 2.0.0
     */
    public const ONPAGE_NOTIFICATION_STATUS_TOGGLE_BEFORE = 'notifal/onpage/notification/status/toggle/before';

    /**
     * Fires after toggling OnPage notification status.
     *
     * @since 2.0.0
     */
    public const ONPAGE_NOTIFICATION_STATUS_TOGGLE_AFTER = 'notifal/onpage/notification/status/toggle/after';

    /**
     * Fires before registering frontend assets.
     *
     * Allows developers to prepare for frontend asset registration.
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com> <hossein@notifal.com>
     */
    public const FRONTEND_ASSETS_BEFORE_REGISTER = 'notifal/frontend/assets/before_register';

    /**
     * Fires after registering frontend assets.
     *
     * Allows developers to modify or extend frontend asset registration.
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com> <hossein@notifal.com>
     */
    public const FRONTEND_ASSETS_AFTER_REGISTER = 'notifal/frontend/assets/after_register';

    /**
     * Fires before enqueuing frontend assets conditionally.
     *
     * Allows developers to prepare for conditional asset enqueuing.
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com> <hossein@notifal.com>
     */
    public const FRONTEND_ASSETS_BEFORE_ENQUEUE = 'notifal/frontend/assets/before_enqueue';

    /**
     * Fires after enqueuing frontend assets conditionally.
     *
     * Allows developers to extend conditional asset enqueuing.
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com> <hossein@notifal.com>
     */
    public const FRONTEND_ASSETS_AFTER_ENQUEUE = 'notifal/frontend/assets/after_enqueue';

    /**
     * Fires before rendering any Notifal Gutenberg block on the frontend.
     *
     * Allows developers to hook into the rendering process for all Notifal blocks.
     *
     * Usage: do_action(ActionHooks::BLOCK_RENDER_BEFORE . '_action_button', ...)
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com> <hossein@notifal.com>
     */
    public const BLOCK_RENDER_BEFORE = 'notifal/block/render/before';

    /**
     * Fires after rendering any Notifal Gutenberg block on the frontend.
     *
     * Allows developers to hook into the rendering process for all Notifal blocks.
     *
     * Usage: do_action(ActionHooks::BLOCK_RENDER_AFTER . '_action_button', ...)
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com> <hossein@notifal.com>
     */
    public const BLOCK_RENDER_AFTER = 'notifal/block/render/after';


    /**
     * Fires when a toast message is being dispatched.
     *
     * Provides the final message, type, and redirect URL (if any).
     *
     * @since 2.0.0
     */
    const TOAST_DISPATCHED = 'notifal/toast/dispatched';



    /**
     * Fires before resolving a tag value.
     *
     * Allows developers to prepare or modify context before a tag is resolved.
     *
     * @param \Notifal\Domain\Tags\Tag $tag     The tag object being resolved.
     * @param array                    $context Context data passed to the resolver.
     * @since 2.0.0
     */
    public const ACTION_BEFORE_TAG_RESOLVE = 'notifal/tag/before_resolve';

    /**
     * Fires after resolving a tag value.
     *
     * Useful for logging, debugging, or altering post-resolve behavior.
     *
     * @param \Notifal\Domain\Tags\Tag $tag     The tag object that was resolved.
     * @param string                   $value   The resolved value of the tag.
     * @param array                    $context Context data used during resolution.
     * @since 2.0.0
     */
    public const ACTION_AFTER_TAG_RESOLVE = 'notifal/tag/after_resolve';

    /**
     * Fires before rendering all tags in the TagManager.
     *
     * This is called once before processing all tags in the content.
     *
     * @param \Notifal\Domain\Tags\TagManager $tagManager The TagManager instance.
     * @param string                          $content    The content containing placeholders.
     * @param array                           $context    Context data available for tag resolution.
     * @since 2.0.0
     */
    public const ACTION_BEFORE_TAGMANAGER_RENDER = 'notifal/tag/manager/before_render';

    /**
     * Fires after rendering all tags in the TagManager.
     *
     * Allows final adjustments to the fully rendered content.
     *
     * @param \Notifal\Domain\Tags\TagManager $tagManager The TagManager instance.
     * @param string                          $content    The content after tag replacement.
     * @param array                           $context    Context data used during rendering.
     * @since 2.0.0
     */
    public const ACTION_AFTER_TAGMANAGER_RENDER = 'notifal/tag/manager/after_render';

    /**
     * Fires after a new tag has been registered in the TagManager.
     *
     * Allows third-party plugins to react to the registration of a tag.
     *
     * @param \Notifal\Domain\Tags\Tag $tag The newly registered tag.
     * @since 2.0.0
     */
    public const ACTION_ON_TAG_REGISTERED = 'notifal/tag/manager/on_registered';


    /**
     * Fires after all default tags have been registered.
     *
     * Allows developers to register their own custom tags to the TagManager.
     *
     * Example usage:
     * ```php
     * add_action(ActionHooks::TAG_REGISTER, function ($manager) {
     *     $manager->register(new \Notifal\Domain\Tags\Tag(
     *         'comment_author_name',
     *         __('Comment Author Name', 'notifal'),
     *         function ($context) {
     *             // PHP 7.4 compatible: check if 'comment' exists and is an object before calling getAuthorName()
     *             return isset($context['comment']) && is_object($context['comment']) && method_exists($context['comment'], 'getAuthorName')
     *                 ? $context['comment']->getAuthorName() : '';
     *         },
     *         \Notifal\Domain\Tags\Enums\TagCategory::GENERAL,
     *         __('Displays the name of the comment author.', 'notifal')
     *     ));
     * });
     * ```
     *
     * @param \Notifal\Domain\Tags\TagManager $manager The TagManager instance.
     * @since 2.0.0
     */
    public const TAG_REGISTER = 'notifal/tag/register';

    // =========================================================================
    // 🌐 REST API ACTION HOOKS
    // =========================================================================

    /**
     * Fires before processing OnPage notification eligibility request.
     *
     * @since 2.0.0
     */
    public const ONPAGE_ELIGIBILITY_BEFORE_PROCESS = 'notifal/onpage/eligibility/before_process';

    /**
     * Fires after processing OnPage notification eligibility request.
     *
     * @since 2.0.0
     */
    public const ONPAGE_ELIGIBILITY_AFTER_PROCESS = 'notifal/onpage/eligibility/after_process';

    /**
     * Fires before tracking OnPage notification event.
     *
     * @since 2.0.0
     */
    public const ONPAGE_TRACKING_BEFORE_PROCESS = 'notifal/onpage/tracking/before_process';

    /**
     * Fires after tracking OnPage notification event.
     *
     * @since 2.0.0
     */
    public const ONPAGE_TRACKING_AFTER_PROCESS = 'notifal/onpage/tracking/after_process';

    /**
     * Fires before validating tracking data.
     *
     * @since 2.0.0
     */
    public const ONPAGE_TRACKING_VALIDATION_BEFORE = 'notifal/onpage/tracking/validation/before';

    /**
     * Fires after validating tracking data.
     *
     * @since 2.0.0
     */
    public const ONPAGE_TRACKING_VALIDATION_AFTER = 'notifal/onpage/tracking/validation/after';

    // =========================================================================
    // 🗄️ DATABASE ACTION HOOKS
    // =========================================================================

    /**
     * Fires before running database migrations.
     *
     * @since 2.0.0
     */
    public const DATABASE_MIGRATIONS_BEFORE_RUN = 'notifal/database/migrations/before_run';

    /**
     * Fires after running database migrations.
     *
     * @since 2.0.0
     */
    public const DATABASE_MIGRATIONS_AFTER_RUN = 'notifal/database/migrations/after_run';

    /**
     * Fires after cleaning up old database data.
     *
     * @since 2.0.0
     */
    public const DATABASE_CLEANUP_COMPLETED = 'notifal/database/cleanup/completed';

    /**
     * Fires after storing a tracking event.
     *
     * @since 2.0.0
     */
    public const ONPAGE_TRACKING_EVENT_STORED = 'notifal/onpage/tracking/event_stored';

    /**
     * Fires after cleaning up OnPage notification database data.
     *
     * @since 2.0.0
     */
    public const ONPAGE_DATABASE_CLEANUP_COMPLETED = 'notifal/onpage/database/cleanup/completed';




    /**
     * Fires after cleaning up OnPage notification database tables.
     *
     * Allows developers to perform additional cleanup operations
     * after the main table cleanup is completed.
     *
     * @since 2.0.0
     */
    public const ONPAGE_DATABASE_TABLES_CLEANED_UP = 'notifal/onpage/database/tables_cleaned_up';

    /**
     * Fires when database cleanup of old data should run.
     *
     * Allows modules to perform cleanup operations on old data.
     *
     * @param int $daysOld Number of days old data to consider for cleanup
     * @since 2.0.0
     */
    public const DATABASE_CLEANUP_OLD_DATA = 'notifal/database/cleanup_old_data';

    /**
     * Fires before running OnPage notification database migrations.
     *
     * @param string $fromVersion Previous version
     * @param string $toVersion Target version
     * @since 2.0.0
     */
    public const ONPAGE_DATABASE_MIGRATIONS_BEFORE_RUN = 'notifal/onpage/database/migrations/before_run';

    /**
     * Fires after running OnPage notification database migrations.
     *
     * @param string $fromVersion Previous version
     * @param string $toVersion Target version
     * @since 2.0.0
     */
    public const ONPAGE_DATABASE_MIGRATIONS_AFTER_RUN = 'notifal/onpage/database/migrations/after_run';

    // =========================================================================
    // 📬 EVENT QUEUE ACTION HOOKS
    // =========================================================================

    /**
     * Fires before queuing OnPage notification event.
     *
     * @param array $eventData Event data
     * @since 2.0.0
     */
    public const ONPAGE_EVENT_BEFORE_QUEUE = 'notifal/onpage/event/before_queue';

    /**
     * Fires after queuing OnPage notification event.
     *
     * @param int $queueId Queue ID
     * @param array $eventData Event data
     * @since 2.0.0
     */
    public const ONPAGE_EVENT_AFTER_QUEUE = 'notifal/onpage/event/after_queue';

    /**
     * Fires before processing queued events.
     *
     * @param int $batchSize Batch size
     * @since 2.0.0
     */
    public const ONPAGE_EVENT_PROCESSING_BEFORE = 'notifal/onpage/event/processing/before';

    /**
     * Fires after processing queued events.
     *
     * @param int $processedCount Number of events processed
     * @param int $errorCount Number of errors encountered
     * @param float $processingTime Processing time in milliseconds
     * @since 2.0.0
     */
    public const ONPAGE_EVENT_PROCESSING_AFTER = 'notifal/onpage/event/processing/after';

    /**
     * Fires after cron event processing.
     *
     * @param array $result Processing result
     * @since 2.0.0
     */
    public const ONPAGE_CRON_PROCESSING_COMPLETED = 'notifal/onpage/cron/processing/completed';

    /**
     * Fires after cron queue cleanup.
     *
     * @param array $result Cleanup result
     * @since 2.0.0
     */
    public const ONPAGE_CRON_CLEANUP_COMPLETED = 'notifal/onpage/cron/cleanup/completed';


    /**
     * Fires after successfully fetching notifications from pre-created API.
     *
     * @since 2.0.0
     * @param array $data API response data
     * @param array $args Original query arguments
     */
    public const PRE_CREATED_NOTIFICATIONS_API_FETCHED = 'notifal/pre_created_notifications/api/fetched';

    /**
     * Fires after successfully fetching a single notification from pre-created API.
     *
     * @since 2.0.0
     * @param array $data API response data
     * @param int $notificationId Notification ID
     */
    public const PRE_CREATED_NOTIFICATIONS_API_SINGLE_FETCHED = 'notifal/pre_created_notifications/api/single_fetched';

    /**
     * Fires after clearing pre-created notifications API cache.
     *
     * @since 2.0.0
     */
    public const PRE_CREATED_NOTIFICATIONS_API_CACHE_CLEARED = 'notifal/pre_created_notifications/api/cache_cleared';

    /**
     * Fires before rendering the pre-created notifications archive.
     *
     * @since 2.0.0
     * @param array $currentFilters Current filter state
     * @param array $apiResponse API response data
     */
    public const PRE_CREATED_NOTIFICATIONS_ARCHIVE_BEFORE = 'notifal/pre_created_notifications/archive/before';

    /**
     * Fires after rendering the pre-created notifications archive.
     *
     * @since 2.0.0
     * @param array $currentFilters Current filter state
     * @param array $apiResponse API response data
     */
    public const PRE_CREATED_NOTIFICATIONS_ARCHIVE_AFTER = 'notifal/pre_created_notifications/archive/after';

    // =========================================================================
    // ⚙️ SETTINGS ACTION HOOKS
    // =========================================================================

    /**
     * Fires when a setting value is updated.
     *
     * @param string $key Setting key that was updated
     * @param mixed $value New setting value
     * @since 2.0.0
     */
    public const SETTING_UPDATED = 'notifal/setting/updated';

    /**
     * Fires when generated tags are saved.
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com> <hossein@notifal.com>
     */
    public const GENERATED_TAGS_SAVED = 'notifal/generated_tags/saved';

    /**
     * Fires when generated tags are removed.
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com> <hossein@notifal.com>
     */
    public const GENERATED_TAGS_REMOVED = 'notifal/generated_tags/removed';

    /**
     * Fires when all settings are reset to defaults.
     *
     * @since 2.0.0
     */
    public const SETTINGS_RESET = 'notifal/settings/reset';

    /**
     * Fires before rendering settings page.
     *
     * @since 2.0.0
     */
    public const SETTINGS_PAGE_BEFORE = 'notifal/settings/page/before';

    /**
     * Fires after rendering settings page.
     *
     * @since 2.0.0
     */
    public const SETTINGS_PAGE_AFTER = 'notifal/settings/page/after';


    // =========================================================================
    // 🏷️ TAB BADGE ACTION HOOKS
    // =========================================================================

    /**
     * Fires when updating document title for tab badge.
     *
     * @param string $new_title New document title with badge
     * @param int $count Number of active notifications
     * @since 2.0.0
     */
    public const ONPAGE_TAB_BADGE_TITLE_UPDATE = 'notifal/onpage/tab_badge/title_update';

    /**
     * Fires when restoring original document title.
     *
     * @param string $original_title Original document title
     * @since 2.0.0
     */
    public const ONPAGE_TAB_BADGE_TITLE_RESTORE = 'notifal/onpage/tab_badge/title_restore';

    /**
     * Fires when updating favicon with badge overlay.
     *
     * @param array $badge_config Badge configuration
     * @param string $original_favicon Original favicon URL
     * @since 2.0.0
     */
    public const ONPAGE_TAB_BADGE_FAVICON_UPDATE = 'notifal/onpage/tab_badge/favicon_update';

    /**
     * Fires when restoring original favicon.
     *
     * @param string $original_favicon Original favicon URL
     * @since 2.0.0
     */
    public const ONPAGE_TAB_BADGE_FAVICON_RESTORE = 'notifal/onpage/tab_badge/favicon_restore';

    // =========================================================================
    // 💰 Analytics & Conversion Tracking Actions
    // =========================================================================

    /**
     * Fires to enqueue analytics dashboard assets.
     *
     * @since 2.0.0
     */
    public const ONPAGE_ANALYTICS_ENQUEUE_ASSETS = 'notifal/onpage/analytics/enqueue_assets';

    /**
     * Fires after recording a conversion.
     *
     * @param array $conversion_data Conversion data
     * @since 2.0.0
     */
    public const ONPAGE_CONVERSION_RECORDED = 'notifal/onpage/conversion/recorded';

    /**
     * Fires when conversion status is updated.
     *
     * @param int $order_id Order ID
     * @param string $status New status
     * @since 2.0.0
     */
    public const ONPAGE_CONVERSION_STATUS_UPDATED = 'notifal/onpage/conversion/status_updated';
}
