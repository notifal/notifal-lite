<?php

namespace Notifal\Infrastructure\WordPress\Hooks;

defined('ABSPATH') || exit;

/**
 * Class FilterHooks
 * Centralized reference for Notifal plugin filter hook names.
 * Intended for documentation, consistency, and IDE auto-completion.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class FilterHooks {


    // =========================================================================
    // 🎛️ ADMIN FILTER HOOKS
    // =========================================================================

    /**
     * WordPress core filter for adding block categories in Gutenberg editor.
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const WP_BLOCK_CATEGORIES_ALL = 'block_categories_all';

    /**
     * WordPress core filter for controlling allowed block types in Gutenberg editor.
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const WP_ALLOWED_BLOCK_TYPES_ALL = 'allowed_block_types_all';

    /**
     * Filters the post type registration arguments.
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const POST_TYPE_ARGS = 'notifal/onpage_post_type/args';

    /**
     * Filters the list of excluded post types in post type discovery.
     *
     * Allows developers to modify which post types are excluded from
     * the post type discovery service used for tag generation.
     *
     * @param string[] $excludedTypes Array of post type names to exclude
     * @return string[] Modified array of excluded post types
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com> <hossein@notifal.com>
     */
    public const POST_TYPE_DISCOVERY_EXCLUDED_TYPES = 'notifal/post_type_discovery/excluded_types';


    /**
     * Filters the template post type registration arguments.
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const TEMPLATE_TYPE_ARGS = 'notifal/template_post_type/args';

    /**
     * Filters the options for a <select> field.
     * Dynamic part: field ID
     *
     * @example notifal/field/select/options/notification_type
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const FIELD_SELECT_OPTIONS = 'notifal/field/select/options/';


    /**
     * Filters the available bulk actions in the list view.
     *
     * Allows developers to customize or extend the available bulk actions
     * for any post type rendered using BaseListView.
     *
     * @since 2.0.0
     */
    public const ADMIN_LIST_BULK_ACTIONS = 'notifal/admin/list/bulk_actions';


    /**
     * Filter the count query arguments.
     *
     * @since 2.0.0
     */
    public const ADMIN_LIST_COUNT_QUERY_ARGS = 'notifal/admin/list/count_query_args';

    /**
     * Filter the query arguments before executing.
     *
     * @since 2.0.0
     */
    public const ADMIN_LIST_QUERY_ARGS = 'notifal/admin/list/query_args';

    /**
     * Filter the query arguments before executing.
     *
     * @since 2.0.0
     */
    public const ADMIN_LIST_ROW_ACTIONS = 'notifal/admin/list/row_actions';



    /**
     * Filters the admin status tab data for the given post type.
     *
     * Usage: apply_filters(sprintf(FilterHooks::ADMIN_STATUS_TABS, $postType), $tabs, $postType)
     *
     * @since 2.0.0
     */
    public const ADMIN_STATUS_TABS = 'notifal/admin/%s/status_tabs';



    /**
     * Filters the content of a custom column in the table body.
     *
     * Allows injecting dynamic values into any column.
     *
     * @param string $output Default output (usually `-`)
     * @param string $column_key The column key being rendered
     * @param WP_Post $post The current post object
     *
     * @since 2.0.0
     */
    public const ADMIN_LIST_CUSTOM_COLUMN = 'notifal/admin/list/custom_column';

    /**
     * Filters the result array after a template import is processed.
     *
     * Allows other plugins to modify the import result,
     * such as appending metadata, logging, or error handling.
     *
     * @param array  $result   The import result array from Importer.
     * @param string $filePath Absolute path to the uploaded file.
     *
     * @since 2.0.0
     */
    public const TEMPLATE_IMPORT_RESULT = 'notifal/template/import/result';

    /**
     * Filters the post status for imported Notifal templates.
     *
     * Default is 'publish' for immediate availability. Returning an invalid value will fall back to 'publish'.
     *
     * @param string $status       Proposed status (default 'publish').
     * @param array  $template_data Raw imported template data.
     * @since 2.0.0
     */
    public const TEMPLATE_IMPORT_POST_STATUS = 'notifal/template/import/post_status';


    /**
     * Filters the product data before rendering the ProductImageWidget.
     *
     * Allows developers to modify product information (e.g., image, price, title)
     * before it is rendered inside the Elementor widget.
     *
     * @param array $data The raw product data.
     *
     * @since 2.0.0
     */
    public const ELEMENTOR_RANDOM_PRODUCT_DATA = 'notifal/elementor/random_product/data';


    /**
     * Filters the default title used for newly created Notifal templates.
     *
     * @param string $title Default post title.
     * @since 2.0.0
     */
    public const TEMPLATE_DEFAULT_TITLE = 'notifal/template/default_title';

    /**
     * Filters the final post title after creation of Notifal template.
     *
     * @param string $title     The final title to be assigned.
     * @param int    $post_id   ID of the newly created post.
     * @since 2.0.0
     */
    public const TEMPLATE_FINAL_TITLE = 'notifal/template/final_title';


    /**
     * Filters the final rendered HTML output of a template preview.
     *
     * @param string  $content The rendered HTML content.
     * @param WP_Post $post    The template post object.
     * @since 2.0.0
     */
    public const TEMPLATE_PREVIEW_OUTPUT = 'notifal/template/preview/output';


    /**
     * Filters the service class list registered for Templates module.
     *
     * @param string[] $services List of class names with ::register()
     * @since 2.0.0
     */
    public const TEMPLATE_SERVICES = 'notifal/templates/services';


    /**
     * Filters the count of templates by builder type before returning.
     *
     * @param int    $count
     * @param string $builder
     * @since 2.0.0
     */
    public const TEMPLATES_BUILDER_COUNT = 'notifal/templates/builder/count';

    /**
     * Filters the total count of templates for the Templates module.
     *
     * Usage:
     * apply_filters(FilterHooks::TEMPLATES_TOTAL_COUNT, $total);
     *
     * @since 2.0.0
     */
    public const TEMPLATES_TOTAL_COUNT = 'notifal/templates/total_count';


    /**
     * Filters the query arguments used to fetch templates by builder for counting.
     *
     * @param array  $args    The original query args.
     * @param string $builder The builder slug (e.g., elementor).
     * @since 2.0.0
     */
    public const TEMPLATES_BUILDER_QUERY_ARGS = 'notifal/templates/builder/query_args';


    /**
     * Filters the meta_query used for retrieving templates by builder.
     *
     * @param array  $metaQuery The meta query array.
     * @param string $builder   The builder slug (e.g. 'elementor').
     * @since 2.0.0
     */
    public const TEMPLATES_BUILDER_META_QUERY = 'notifal/templates/builder/meta_query';


    /**
     * Filters the AJAX search results before returning them.
     *
     * @param array  $results   The array of search result items.
     * @param string $post_type The post type or taxonomy identifier (e.g. term_category, product).
     * @param string $keyword   The search keyword.
     * @since 2.0.0
     */
    public const ONPAGE_ADMIN_SEARCH_RESULTS = 'notifal/onpage/admin_search/results';

    /**
     * Filters the user search results returned by the OnPage admin search functionality.
     *
     * @param array  $results The array of user search result items.
     * @param string $keyword The search keyword.
     * @since 2.0.0
     */
    public const ONPAGE_ADMIN_USER_SEARCH_RESULTS = 'notifal/onpage/admin_search/user_results';


    /**
     * Filter to customize or extend the JS translations used in the OnPageNotification module.
     *
     * Used inside lang/translations.php.
     *
     * @since 2.0.0
     */
    public const ONPAGE_JS_TRANSLATIONS = 'notifal/onpage/translations';


    /**
     * Filters the arguments passed to get_terms when fetching label options.
     *
     * Used in OnPageNotification\Services\LabelService.
     *
     * @since 2.0.0
     */
    public const ONPAGE_LABEL_GET_TERMS_ARGS = 'notifal/onpage/labels/get_terms_args';

    /**
     * Filters the label options returned as [slug => name].
     *
     * @since 2.0.0
     */
    public const ONPAGE_LABEL_OPTIONS = 'notifal/onpage/labels/options';


    /**
     * Modify services registered by OnPageNotification\ServiceProvider
     *
     * @since 2.0.0
     */
    public const ONPAGE_SERVICES = 'notifal/onpage/services';

    /**
     * Filter order filters before applying in content source service.
     *
     * @since 2.0.0
     */
    public const ONPAGE_ORDER_FILTERS = 'notifal/onpage/order_filters';

    /**
     * Filter product filters before applying in content source service.
     *
     * @since 2.0.0
     */
    public const ONPAGE_PRODUCT_FILTERS = 'notifal/onpage/product_filters';

    /**
     * Filter user filters before applying in content source service.
     *
     * @since 2.0.0
     */
    public const ONPAGE_USER_FILTERS = 'notifal/onpage/user_filters';

    /**
     * Filter parsed content source settings.
     *
     * @since 2.0.0
     */
    public const ONPAGE_CONTENT_SOURCE_SETTINGS = 'notifal/onpage/content_source_settings';

    /**
     * Filter sanitized content source settings.
     *
     * Allows developers to modify the sanitized content source settings
     * before they are saved or used.
     *
     * @param array $sanitized Sanitized content source settings
     * @param array $raw_settings Raw content source settings
     * @return array Modified sanitized settings
     * @since 2.0.0
     */
    public const ONPAGE_CONTENT_SOURCE_SANITIZED_SETTINGS = 'notifal/onpage/content_source/sanitized_settings';

    /**
     * Filter entity-specific content source filters before pool queries run.
     *
     * @param array  $filters     Built filters for the entity type.
     * @param string $entity_type Entity scope (product, order, post, page, comment, custom_posttype:{slug}).
     * @param array  $settings    Notification content source settings.
     * @param array  $page_context Current visitor page context.
     * @return array Modified filters.
     * @since 2.3.7
     */
    public const ONPAGE_CONTENT_SOURCE_ENTITY_FILTERS = 'notifal/onpage/content_source/entity_filters';

    /**
     * Extend content source pool cache keys (e.g. smart targeting page context).
     *
     * @param string $cache_key    Base cache key.
     * @param string $entity_type  Entity scope key.
     * @param array  $settings     Content source settings.
     * @param array  $page_context Current visitor page context.
     * @return string Modified cache key.
     * @since 2.3.7
     */
    public const ONPAGE_CONTENT_SOURCE_POOL_CACHE_KEY = 'notifal/onpage/content_source/pool_cache_key';

    /**
     * Resolve a content source pool with optional multi-phase logic.
     *
     * Return an array to bypass default pool loading; return null to use core loader.
     *
     * @param array|null $pool    Pre-resolved pool when provided by a filter.
     * @param array      $context Resolver context (entity_type, settings, cache_key, cache_group, fetcher, page_context).
     * @return array|null Resolved pool or null to defer to core.
     * @since 2.3.7
     */
    public const ONPAGE_CONTENT_SOURCE_RESOLVE_POOL = 'notifal/onpage/content_source/resolve_pool';

    /**
     * Filter the size of the product pool for pool-based caching.
     *
     * Allows developers to modify the number of products fetched
     * for the pool-based caching system.
     *
     * @param int $pool_size Number of products to fetch (default: 18)
     * @param array $content_source_settings Content source settings
     * @return int Modified pool size
     * @since 2.0.0
     */
    public const ONPAGE_PRODUCT_POOL_SIZE = 'notifal/onpage/product_pool/size';

    /**
     * Filter the timeout duration for product pool cache.
     *
     * Allows developers to modify how long product pools are cached
     * before being refreshed.
     *
     * @param int $timeout Cache timeout in seconds (default: 12 minutes)
     * @param array $content_source_settings Content source settings
     * @return int Modified timeout in seconds
     * @since 2.0.0
     */
    public const ONPAGE_PRODUCT_POOL_TIMEOUT = 'notifal/onpage/product_pool/timeout';

    /**
     * Filter the interval (in seconds) between live sale re-validations of cached product pools.
     *
     * When a product pool targets "sale only" products, the system periodically re-checks
     * each pool member against {@see \WC_Product::is_on_sale()} and drops entries that are
     * no longer on sale. This filter controls how often that check runs.
     *
     * @param int   $interval Seconds between validations (default: 300 = 5 minutes).
     * @param array $content_source_settings Content source settings for the notification.
     * @return int Modified interval in seconds (minimum 60).
     * @since 2.0.0
     */
    public const ONPAGE_PRODUCT_POOL_SALE_REVALIDATION_INTERVAL = 'notifal/onpage/product_pool/sale_revalidation_interval';

    /**
     * Filter max number of pre-rendered pool variants shipped for client-side retrigger (no extra HTTP).
     *
     * @since 2.3.5
     * @author Hossein <hossein@notifal.com>
     */
    public const ONPAGE_RETRIGGER_CLIENT_VARIANTS_MAX = 'notifal/onpage/retrigger_variants/max';

    /**
     * Filter the size of the order pool for pool-based caching.
     *
     * Allows developers to modify the number of orders fetched
     * for the pool-based caching system.
     *
     * @param int $pool_size Number of orders to fetch (default: 18)
     * @param array $content_source_settings Content source settings
     * @return int Modified pool size
     * @since 2.0.0
     */
    public const ONPAGE_ORDER_POOL_SIZE = 'notifal/onpage/order_pool/size';

    /**
     * Filter the timeout duration for order pool cache.
     *
     * Allows developers to modify how long order pools are cached
     * before being refreshed.
     *
     * @param int $timeout Cache timeout in seconds (default: 12 minutes)
     * @param array $content_source_settings Content source settings
     * @return int Modified timeout in seconds
     * @since 2.0.0
     */
    public const ONPAGE_ORDER_POOL_TIMEOUT = 'notifal/onpage/order_pool/timeout';

    /**
     * Filter the cached order count used by the {order_counter} tag.
     *
     * @param int   $count Matching order count.
     * @param array $content_source_settings Content source settings.
     * @param array $filters Built order filters.
     * @return int Modified order count.
     * @since 2.3.7
     */
    public const ONPAGE_ORDER_COUNT = 'notifal/onpage/order_count';

    /**
     * Filter the cache timeout for filtered order counts.
     *
     * @param int   $timeout Cache timeout in seconds (default: 1 hour).
     * @param array $content_source_settings Content source settings.
     * @return int Modified timeout in seconds.
     * @since 2.3.7
     */
    public const ONPAGE_ORDER_COUNT_CACHE_TIMEOUT = 'notifal/onpage/order_count/cache_timeout';

    /**
     * Filter the cached product count used by the {product_counter} tag.
     *
     * @since 2.3.7
     */
    public const ONPAGE_PRODUCT_COUNT = 'notifal/onpage/product_count';

    /**
     * Filter the cached post count used by the {post_counter} tag.
     *
     * @since 2.3.7
     */
    public const ONPAGE_POST_COUNT = 'notifal/onpage/post_count';

    /**
     * Filter the cached page count used by the {page_counter} tag.
     *
     * @since 2.3.7
     */
    public const ONPAGE_PAGE_COUNT = 'notifal/onpage/page_count';

    /**
     * Filter the cached comment count used by the {comment_counter} tag.
     *
     * @since 2.3.7
     */
    public const ONPAGE_COMMENT_COUNT = 'notifal/onpage/comment_count';

    /**
     * Filter the cached custom post type count used by {custom_posttype_counter_{post_type}}.
     *
     * @since 2.3.7
     */
    public const ONPAGE_CUSTOM_POSTTYPE_COUNT = 'notifal/onpage/custom_posttype_count';

    /**
     * Filter cache timeout for all content-source counter tags.
     *
     * @since 2.3.7
     */
    public const ONPAGE_CONTENT_SOURCE_COUNT_CACHE_TIMEOUT = 'notifal/onpage/content_source_count/cache_timeout';

    /**
     * Filter toast type before redirecting.
     *
     * Allows overriding the type of the toast message (e.g. success, error).
     *
     * @since 2.0.0
     */
    public const TOAST_TYPE = 'notifal/toast/type';

    /**
     * Filter toast message before it is stored in the query string.
     *
     * Useful for modifying, translating, or sanitizing the message content.
     *
     * @since 2.0.0
     */
    public const TOAST_MESSAGE = 'notifal/toast/message';



    /**
     * Modify the prepared export data for a notifal template.
     *
     * Useful for adding custom fields, metadata, etc.
     *
     * @since 2.0.0
     */
    public const EXPORT_TEMPLATE_DATA = 'notifal/export/template_data';

    /**
     * Modify the prepared export data for a notifal onpage notification.
     *
     * Useful for adding custom fields, metadata, etc.
     *
     * @since 2.0.0
     */
    public const EXPORT_ONPAGE_NOTIFICATION_DATA = 'notifal/export/onpage_notification_data';

    /**
     * Filters the result array after an onpage notification import is processed.
     *
     * Allows other plugins to modify the import result,
     * add custom validation, or perform additional actions.
     *
     * @param array $result The import result array from OnPageNotificationImporter.
     * @param string $filePath The path to the imported file.
     * @since 2.0.0
     */
    public const ONPAGE_NOTIFICATION_IMPORT_RESULT = 'notifal/onpage_notification/import/result';

    /**
     * Filters the post_status used when creating a post during OnPage notification import.
     *
     * Default is 'draft' for safety. Returning an invalid value will fall back to 'draft'.
     *
     * @param string $status            Proposed status (default 'draft').
     * @param array  $notification_data Raw imported notification data.
     * @since 2.0.0
     */
    public const ONPAGE_NOTIFICATION_IMPORT_POST_STATUS = 'notifal/onpage_notification/import/post_status';


    /**
     * Filters the result of random WooCommerce product fetch.
     *
     * Usage: apply_filters(sprintf(FilterHooks::WOOCOMMERCE_RANDOM_PRODUCT, 'woocommerce'), $products)
     *
     * @since 2.0.0
     */
    public const WOOCOMMERCE_RANDOM_PRODUCT = 'notifal/woocommerce/product/random';



    /**
     * Filters the list of global infrastructure services (non-module specific).
     *
     * Used to register global services like admin menus, ajax handlers, etc.
     *
     * Usage:
     * apply_filters(FilterHooks::INFRASTRUCTURE_SERVICES, $services);
     *
     * @since 2.0.0
     */
    public const INFRASTRUCTURE_SERVICES = 'notifal/infrastructure/services';


    /**
     * JS translation filter hook for dynamic modules.
     *
     * Usage: sprintf(FilterHooks::MODULE_JS_TRANSLATIONS, 'Templates')
     *
     * @since 2.0.0
     */
    public const MODULE_JS_TRANSLATIONS = 'notifal/%s/js_translations';


    /**
     * Filters the load more templates response data.
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com> <hossein@notifal.com>
     */
    public const TEMPLATES_LOAD_MORE_RESPONSE = 'notifal/templates/load_more/response';

    /**
     * Filters the filtered templates response data.
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const TEMPLATES_FILTER_RESPONSE = 'notifal/templates/filter/response';

    /**
     * Filters the resolved value of a tag.
     *
     * Allows developers to modify the value returned by a tag resolver.
     *
     * @param string                          $value   The resolved value.
     * @param \Notifal\Domain\Tags\Tag        $tag     The tag object being resolved.
     * @param array                           $context Context data available for resolution.
     * @return string
     * @since 2.0.0
     */
    public const FILTER_MODIFY_TAG_VALUE = 'notifal/tag/modify_value';

    /**
     * Filters the final rendered content after all tags are replaced.
     *
     * Useful for altering the output or applying additional transformations.
     *
     * @param string $content The fully rendered content.
     * @param array  $context Context data used during rendering.
     * @return string
     * @since 2.0.0
     */
    public const FILTER_MODIFY_TAGS_LIST = 'notifal/tag/manager/modify_rendered_content';



    /**
     * Filters the ProductDTO after it has been built from a WooCommerce product.
     *
     * Allows developers to modify the DTO before it is returned to the TagEngine or other services.
     *
     * Example:
     * ```php
     * add_filter(FilterHooks::WOOCOMMERCE_PRODUCT_DTO, function ($dto, $wcProduct) {
     *     // Change the product name
     *     return new ProductDTO(
     *         $dto->getId(),
     *         $dto->getName() . ' (Custom)',
     *         $dto->getRegularPrice(),
     *         $dto->getSalePrice(),
     *         $dto->getStockQuantity(),
     *         $dto->getSoldQuantity(),
     *         $dto->getLink(),
     *         $dto->getDiscountEndsAt()
     *     );
     * }, 10, 2);
     * ```
     *
     * @param \Notifal\Domain\Products\DTO\ProductDTO $dto      The built ProductDTO.
     * @param \WC_Product                             $wcProduct The original WooCommerce product object.
     * @return \Notifal\Domain\Products\DTO\ProductDTO
     * @since 2.0.0
     */
    public const WOOCOMMERCE_PRODUCT_DTO = 'notifal/woocommerce/product/dto';


    /**
     * Filters the list of tag categories available in Notifal.
     *
     * Allows developers to add or modify tag categories that are used
     * when registering tags or displaying them in the admin UI.
     *
     * Example:
     * ```php
     * add_filter(FilterHooks::TAG_CATEGORIES, function ($categories) {
     *     $categories[] = 'reviews'; // Add a "Reviews" category
     *     return $categories;
     * });
     * ```
     *
     * @param string[] $categories The array of tag category identifiers.
     * @return string[]
     * @since 2.0.0
     */
    public const TAG_CATEGORIES = 'notifal/tag/categories';


    /**
     * Filters the REST API response for fetching all tags.
     *
     * Allows developers to modify the array of tags returned by
     * the API endpoint `/wp-json/notifal/v1/tags`.
     *
     * Example:
     * ```php
     * add_filter(FilterHooks::FILTER_MODIFY_TAGS_API_RESPONSE, function ($tags) {
     *     // Add a custom tag or modify existing ones
     *     $tags['custom'][] = [
     *         'key' => 'custom_tag',
     *         'label' => 'Custom Tag',
     *         'description' => 'A custom tag added via filter.',
     *     ];
     *     return $tags;
     * });
     * ```
     *
     * @param array $tags Array of tags grouped by category.
     * @return array Modified array of tags.
     * @since 2.0.0
     */
    public const FILTER_MODIFY_TAGS_API_RESPONSE = 'notifal/tag/api/modify_response';



    /**
     * Filters the most used meta keys for dynamic tag resolution.
     *
     * Allows developers to modify or extend the default most used keys
     * returned for different entity types (user, order, product, custom_posttype).
     *
     * Example usage:
     * ```php
     * add_filter(FilterHooks::DYNAMIC_KEYS_MOST_USED, function ($keys, $type, $postType) {
     *     if ($type === 'user') {
     *         $keys[] = 'custom_user_field';
     *     }
     *     return $keys;
     * }, 10, 3);
     * ```
     *
     * @param string[] $keys     Array of meta key names.
     * @param string   $type     Entity type: user, order, product, custom_posttype.
     * @param string   $postType Post type name (for custom_posttype only).
     * @return string[] Modified array of meta keys.
     * @since 2.0.0
     */
    public const DYNAMIC_KEYS_MOST_USED = 'notifal/dynamic_keys/most_used';

    /**
     * Filters the search results for dynamic meta keys.
     *
     * Allows developers to modify or extend the meta keys returned
     * from database searches for different entity types.
     *
     * Example usage:
     * ```php
     * add_filter(FilterHooks::DYNAMIC_KEYS_SEARCH_RESULTS, function ($keys, $search, $type, $postType) {
     *     // Add custom keys to search results
     *     if ($type === 'user' && strpos($search, 'custom') !== false) {
     *         $keys[] = 'custom_field_1';
     *         $keys[] = 'custom_field_2';
     *     }
     *     return $keys;
     * }, 10, 4);
     * ```
     *
     * @param string[] $keys     Array of meta key names from search.
     * @param string   $search   The search term used.
     * @param string   $type     Entity type: user, order, product, custom_posttype.
     * @param string   $postType Post type name (for custom_posttype only).
     * @return string[] Modified array of meta keys.
     * @since 2.0.0
     */
    public const DYNAMIC_KEYS_SEARCH_RESULTS = 'notifal/dynamic_keys/search_results';

    /**
     * Filters the service class list registered for Templates module.
     *
     * @param string[] $services List of class names with ::register()
     * @since 2.0.0
     */
    public const TAGS_SERVICES = 'notifal/tags/services';

    /**
     * Filters the service class list registered for Settings.
     *
     * @param string[] $services List of class names with ::register()
     * @since 2.0.0
     */
    public const SETTINGS_SERVICES = 'notifal/settings/services';

    /**
     * Filters the service class list registered for Deactivation Popup.
     *
     * @param string[] $services List of class names with ::register()
     * @since 2.0.0
     */
    public const DEACTIVATION_SERVICES = 'notifal/deactivation/services';

    // =========================================================================
    // 📋 ONPAGE NOTIFICATION GENERAL FILTERS
    // =========================================================================

    /**
     * Filters the default general settings for OnPage notifications.
     *
     * Allows developers to modify the default general settings before they are used.
     *
     * @param array $settings Default general settings
     * @return array Modified general settings
     * @since 2.0.0
     */
    public const ONPAGE_GENERAL_DEFAULT_SETTINGS = 'notifal/onpage/general/default_settings';

    /**
     * Filters the sanitized general settings for OnPage notifications.
     *
     * Allows developers to modify the sanitized general settings
     * after validation and sanitization has been performed.
     *
     * @param array $sanitized_settings Sanitized general settings
     * @param array $raw_settings Raw input settings
     * @return array Modified sanitized settings
     * @since 2.0.0
     */
    public const ONPAGE_GENERAL_SANITIZED_SETTINGS = 'notifal/onpage/general/sanitized_settings';

    /**
     * Filters whether general pro features are allowed for the current user.
     *
     * @param mixed $allowed Current allowance status (null = not checked)
     * @return mixed Modified allowance status
     * @since 2.0.0
     */
    public const ONPAGE_GENERAL_PRO_FEATURES_ALLOWED = 'notifal/onpage/general/pro_features_allowed';

    // =========================================================================
    // ⏰ ONPAGE NOTIFICATION TIMING FILTERS
    // =========================================================================

    /**
     * Filters the default timing settings for OnPage notifications.
     *
     * Allows developers to modify the default timing configuration
     * for OnPage notifications.
     *
     * @param array $settings Default timing settings
     * @return array Modified timing settings
     * @since 2.0.0
     */
    public const ONPAGE_TIMING_DEFAULT_SETTINGS = 'notifal/onpage/timing/default_settings';

    /**
     * Filters the sanitized timing settings for OnPage notifications.
     *
     * Allows developers to modify the sanitized timing settings
     * before they are saved or used.
     *
     * @param array $sanitized Sanitized timing settings
     * @param array $raw_settings Raw timing settings
     * @return array Modified sanitized settings
     * @since 2.0.0
     */
    public const ONPAGE_TIMING_SANITIZED_SETTINGS = 'notifal/onpage/timing/sanitized_settings';

    /**
     * Filters the display timing configuration for OnPage notifications.
     *
     * Allows developers to modify the display timing configuration
     * (when to show, delays, scroll triggers, etc.).
     *
     * @param array $config Display timing configuration
     * @param array $settings Full timing settings
     * @return array Modified display timing configuration
     * @since 2.0.0
     */
    public const ONPAGE_TIMING_DISPLAY_CONFIG = 'notifal/onpage/timing/display_config';

    /**
     * Filters the duration configuration for OnPage notifications.
     *
     * Allows developers to modify the duration configuration
     * (how long to show, auto-hide settings, etc.).
     *
     * @param array $config Duration configuration
     * @param array $settings Full timing settings
     * @return array Modified duration configuration
     * @since 2.0.0
     */
    public const ONPAGE_TIMING_DURATION_CONFIG = 'notifal/onpage/timing/duration_config';

    /**
     * Filters the frequency configuration for OnPage notifications.
     *
     * Allows developers to modify the frequency configuration
     * (how often to show, session limits, etc.).
     *
     * @param array $config Frequency configuration
     * @param array $settings Full timing settings
     * @return array Modified frequency configuration
     * @since 2.0.0
     */
    public const ONPAGE_TIMING_FREQUENCY_CONFIG = 'notifal/onpage/timing/frequency_config';

    /**
     * Filters the advanced timing configuration for OnPage notifications.
     *
     * Allows developers to modify the advanced timing configuration
     * (tab behavior, user preferences, session management, etc.).
     *
     * @param array $config Advanced timing configuration
     * @param array $settings Full timing settings
     * @return array Modified advanced timing configuration
     * @since 2.0.0
     */
    public const ONPAGE_TIMING_ADVANCED_CONFIG = 'notifal/onpage/timing/advanced_config';

    /**
     * Filters whether a notification should be shown based on timing settings.
     *
     * Allows developers to override the timing logic and control
     * whether a notification should be displayed.
     *
     * @param bool $should_show Whether the notification should be shown
     * @param array $settings Timing settings
     * @param array $context Current context (user, page, etc.)
     * @return bool Whether the notification should be shown
     * @since 2.0.0
     */
    public const ONPAGE_TIMING_SHOULD_SHOW = 'notifal/onpage/timing/should_show';

    /**
     * Filters the JavaScript timing configuration for OnPage notifications.
     *
     * Allows developers to modify the JavaScript configuration
     * that will be passed to the frontend for timing behavior.
     *
     * @param array $js_config JavaScript timing configuration
     * @param array $settings Full timing settings
     * @return array Modified JavaScript timing configuration
     * @since 2.0.0
     */
    public const ONPAGE_TIMING_JS_CONFIG = 'notifal/onpage/timing/js_config';

    // =========================================================================
    // 🎭 ONPAGE NOTIFICATION BEHAVIOR FILTERS
    // =========================================================================

    /**
     * Filters the default behavior settings for OnPage notifications.
     *
     * Allows developers to modify the default behavior configuration
     * for OnPage notifications.
     *
     * @param array $settings Default behavior settings
     * @return array Modified behavior settings
     * @since 2.0.0
     */
    public const ONPAGE_BEHAVIOR_DEFAULT_SETTINGS = 'notifal/onpage/behavior/default_settings';

    /**
     * Filters the sanitized behavior settings for OnPage notifications.
     *
     * Allows developers to modify the sanitized behavior settings
     * before they are saved or used.
     *
     * @param array $sanitized Sanitized behavior settings
     * @param array $raw_settings Raw behavior settings
     * @return array Modified sanitized settings
     * @since 2.0.0
     */
    public const ONPAGE_BEHAVIOR_SANITIZED_SETTINGS = 'notifal/onpage/behavior/sanitized_settings';

    /**
     * Filters the user interaction configuration for OnPage notifications.
     *
     * Allows developers to modify the user interaction configuration
     * (dismiss behaviors, click handling, etc.).
     *
     * @param array $config User interaction configuration
     * @param array $settings Full behavior settings
     * @return array Modified user interaction configuration
     * @since 2.0.0
     */
    public const ONPAGE_BEHAVIOR_INTERACTION_CONFIG = 'notifal/onpage/behavior/interaction_config';

    /**
     * Filters the animation configuration for OnPage notifications.
     *
     * Allows developers to modify the animation configuration
     * (animation type, duration, easing, effects, etc.).
     *
     * @param array $config Animation configuration
     * @param array $settings Full behavior settings
     * @return array Modified animation configuration
     * @since 2.0.0
     */
    public const ONPAGE_BEHAVIOR_ANIMATION_CONFIG = 'notifal/onpage/behavior/animation_config';

    /**
     * Filters the accessibility configuration for OnPage notifications.
     *
     * Allows developers to modify the accessibility configuration
     * (ARIA labels, focus management, screen reader support, etc.).
     *
     * @param array $config Accessibility configuration
     * @param array $settings Full behavior settings
     * @return array Modified accessibility configuration
     * @since 2.0.0
     */
    public const ONPAGE_BEHAVIOR_ACCESSIBILITY_CONFIG = 'notifal/onpage/behavior/accessibility_config';

    /**
     * Filters the frontend accessibility configuration for OnPage notifications.
     *
     * Allows developers to modify the frontend accessibility configuration
     * before it's passed to the JavaScript accessibility manager.
     *
     * @param array $config Frontend accessibility configuration
     * @param array $behaviorSettings Behavior settings
     * @return array Modified frontend accessibility configuration
     * @since 2.0.0
     */
    public const ONPAGE_FRONTEND_ACCESSIBILITY_CONFIG = 'notifal/onpage/frontend/accessibility_config';

    /**
     * Filters the ARIA attributes for OnPage notification elements.
     *
     * Allows developers to modify the ARIA attributes that are applied
     * to notification elements for screen reader compatibility.
     *
     * @param array $ariaAttributes ARIA attributes
     * @param array $accessibilityConfig Accessibility configuration
     * @param array $notificationData Notification data
     * @return array Modified ARIA attributes
     * @since 2.0.0
     */
    public const ONPAGE_FRONTEND_ARIA_ATTRIBUTES = 'notifal/onpage/frontend/aria_attributes';

    /**
     * Filters the focus trap configuration for OnPage notifications.
     *
     * Allows developers to modify the focus trap behavior
     * for keyboard navigation accessibility.
     *
     * @param array $focusTrapConfig Focus trap configuration
     * @param array $accessibilityConfig Accessibility configuration
     * @param array $notificationData Notification data
     * @return array Modified focus trap configuration
     * @since 2.0.0
     */
    public const ONPAGE_FRONTEND_FOCUS_TRAP_CONFIG = 'notifal/onpage/frontend/focus_trap_config';

    /**
     * Filters the screen reader announcement configuration for OnPage notifications.
     *
     * Allows developers to modify how notifications are announced
     * to screen readers for accessibility.
     *
     * @param array $screenReaderConfig Screen reader configuration
     * @param array $accessibilityConfig Accessibility configuration
     * @param array $notificationData Notification data
     * @return array Modified screen reader configuration
     * @since 2.0.0
     */
    public const ONPAGE_FRONTEND_SCREEN_READER_CONFIG = 'notifal/onpage/frontend/screen_reader_config';

    /**
     * Filters the accessibility data attributes for OnPage notification elements.
     *
     * Allows developers to modify the data attributes that are applied
     * to notification elements for accessibility feature detection.
     *
     * @param array $dataAttributes Data attributes
     * @param array $accessibilityConfig Accessibility configuration
     * @param array $notificationData Notification data
     * @return array Modified data attributes
     * @since 2.0.0
     */
    public const ONPAGE_FRONTEND_ACCESSIBILITY_DATA_ATTRIBUTES = 'notifal/onpage/frontend/accessibility_data_attributes';

    /**
     * Filters the advanced behavior configuration for OnPage notifications.
     *
     * Allows developers to modify the advanced behavior configuration
     * (page scroll prevention, form handling, state management, etc.).
     *
     * @param array $config Advanced behavior configuration
     * @param array $settings Full behavior settings
     * @return array Modified advanced behavior configuration
     * @since 2.0.0
     */
    public const ONPAGE_BEHAVIOR_ADVANCED_CONFIG = 'notifal/onpage/behavior/advanced_config';

    /**
     * Filters the mobile behavior configuration for OnPage notifications.
     *
     * Allows developers to modify the mobile behavior configuration
     * (touch interactions, mobile optimizations, etc.).
     *
     * @param array $config Mobile behavior configuration
     * @param array $settings Full behavior settings
     * @return array Modified mobile behavior configuration
     * @since 2.0.0
     */
    public const ONPAGE_BEHAVIOR_MOBILE_CONFIG = 'notifal/onpage/behavior/mobile_config';

    /**
     * Filters the JavaScript behavior configuration for OnPage notifications.
     *
     * Allows developers to modify the JavaScript configuration
     * that will be passed to the frontend for behavior control.
     *
     * @param array $js_config JavaScript behavior configuration
     * @param array $settings Full behavior settings
     * @return array Modified JavaScript behavior configuration
     * @since 2.0.0
     */
    public const ONPAGE_BEHAVIOR_JS_CONFIG = 'notifal/onpage/behavior/js_config';

    /**
     * Filters the tab badge configuration for OnPage notifications.
     *
     * Allows developers to modify the tab badge configuration
     * that controls browser tab badge behavior.
     *
     * @param array $tab_badge_config Tab badge configuration
     * @param array $settings Full behavior settings
     * @return array Modified tab badge configuration
     * @since 2.0.0
     */
    public const ONPAGE_BEHAVIOR_TAB_BADGE_CONFIG = 'notifal/onpage/behavior/tab_badge_config';

    /**
     * Filters the tab badge frontend configuration.
     *
     * Allows developers to modify the tab badge configuration
     * that is passed to the frontend JavaScript.
     *
     * @param array $frontend_config Frontend configuration
     * @return array Modified frontend configuration
     * @since 2.0.0
     */
    public const ONPAGE_TAB_BADGE_FRONTEND_CONFIG = 'notifal/onpage/tab_badge/frontend_config';

    // =========================================================================
    // 🎨 ONPAGE NOTIFICATION APPEARANCE FILTERS
    // =========================================================================

    /**
     * Filters the default appearance settings for OnPage notifications.
     *
     * Allows developers to modify the default appearance configuration
     * for OnPage notifications.
     *
     * @param array $settings Default appearance settings
     * @return array Modified appearance settings
     * @since 2.0.0
     */
    public const ONPAGE_APPEARANCE_DEFAULT_SETTINGS = 'notifal/onpage/appearance/default_settings';

    /**
     * Filters the sanitized appearance settings for OnPage notifications.
     *
     * Allows developers to modify the sanitized appearance settings
     * before they are saved or used.
     *
     * @param array $sanitized Sanitized appearance settings
     * @param array $raw_settings Raw appearance settings
     * @return array Modified sanitized settings
     * @since 2.0.0
     */
    public const ONPAGE_APPEARANCE_SANITIZED_SETTINGS = 'notifal/onpage/appearance/sanitized_settings';

    /**
     * Filters the position settings for OnPage notifications.
     *
     * Allows developers to modify the position configuration
     * (desktop/mobile positions, distances, etc.).
     *
     * @param array $position_settings Position configuration
     * @param array $settings Full appearance settings
     * @param string $device Device type (desktop/mobile)
     * @return array Modified position configuration
     * @since 2.0.0
     */
    public const ONPAGE_APPEARANCE_POSITION_SETTINGS = 'notifal/onpage/appearance/position_settings';

    /**
     * Filters the position CSS for OnPage notifications.
     *
     * Allows developers to modify the generated CSS
     * for positioning the notification.
     *
     * @param string $css Generated CSS
     * @param array $position_settings Position configuration
     * @param string $device Device type (desktop/mobile)
     * @return string Modified CSS
     * @since 2.0.0
     */
    public const ONPAGE_APPEARANCE_POSITION_CSS = 'notifal/onpage/appearance/position_css';

    // =========================================================================
    // 💾 ONPAGE NOTIFICATION SAVE FILTERS
    // =========================================================================

    /**
     * Filters the sanitized notification data before saving.
     *
     * Allows developers to modify the sanitized notification data
     * before it is saved to the database.
     *
     * @param array $sanitized Sanitized notification data
     * @param array $raw_data Raw notification data
     * @return array Modified sanitized data
     * @since 2.0.0
     */
    public const ONPAGE_NOTIFICATION_SANITIZED_DATA = 'notifal/onpage_notification/sanitized_data';

    /**
     * Filters the post data before saving to database.
     *
     * Allows developers to modify the post data
     * before it is saved to the WordPress database.
     *
     * @param array $post_data Post data for wp_insert_post
     * @param array $sanitized_data Sanitized notification data
     * @return array Modified post data
     * @since 2.0.0
     */
    public const ONPAGE_NOTIFICATION_POST_DATA = 'notifal/onpage_notification/post_data';

    /**
     * Filters the loaded notification data.
     *
     * Allows developers to modify the notification data
     * when it is loaded for editing.
     *
     * @param array $data Loaded notification data
     * @param WP_Post $post WordPress post object
     * @return array Modified notification data
     * @since 2.0.0
     */
    public const ONPAGE_NOTIFICATION_LOADED_DATA = 'notifal/onpage_notification/loaded_data';

    /**
     * Filters the sanitized display rules settings.
     *
     * Allows developers to modify the sanitized display rules settings
     * before they are saved or used.
     *
     * @param array $sanitized Sanitized display rules settings
     * @param array $raw_settings Raw display rules settings
     * @return array Modified sanitized settings
     * @since 2.0.0
     */
    public const ONPAGE_DISPLAY_RULES_SANITIZED_SETTINGS = 'notifal/onpage/display_rules/sanitized_settings';

    /**
     * Filters the supported rule types for display rules.
     *
     * Allows Pro plugin to add advanced rule types like categories,
     * URL matching, and user targeting. Lite version should only
     * support basic rule types.
     *
     * @param array $rule_types Supported rule types configuration
     * @return array Modified rule types configuration
     * @since 2.0.0
     */
    public const ONPAGE_DISPLAY_RULES_SUPPORTED_TYPES = 'notifal/onpage/display_rules/supported_types';

    /**
     * Filters the rules before they are processed for display validation.
     *
     * Allows Pro plugin to inject advanced rule processing logic.
     * Lite version will strip pro features from rules.
     *
     * @param array $rules Display rules to process
     * @param string $combination_logic Rule combination logic (AND/OR)
     * @param array $context Current page context
     * @return array Modified rules array
     * @since 2.0.0
     */
    public const ONPAGE_DISPLAY_RULES_BEFORE_VALIDATION = 'notifal/onpage/display_rules/before_validation';

    /**
     * Filters the rules processing result.
     *
     * Allows Pro plugin to override or enhance rule evaluation logic
     * for advanced features like multiple rules combination.
     *
     * @param bool|null $result Rule evaluation result (null = continue with default logic)
     * @param array $rules Display rules
     * @param string $combination_logic Rule combination logic
     * @param array $context Current page context
     * @return bool|null Modified result
     * @since 2.0.0
     */
    public const ONPAGE_DISPLAY_RULES_EVALUATION_RESULT = 'notifal/onpage/display_rules/evaluation_result';

    /**
     * Filters the default audio files for OnPage notifications.
     *
     * Allows developers to modify the list of available default audio files
     * that users can select from.
     *
     * @param array $audio_files List of default audio files
     * @return array Modified audio files list
     * @since 2.0.0
     */
    public const ONPAGE_APPEARANCE_DEFAULT_AUDIO_FILES = 'notifal/onpage/appearance/default_audio_files';

    /**
     * Filters the audio file URL for OnPage notifications.
     *
     * Allows developers to modify the audio file URL
     * before it's used in the frontend.
     *
     * @param string $url Audio file URL
     * @param string $filename Audio filename
     * @return string Modified audio file URL
     * @since 2.0.0
     */
    public const ONPAGE_APPEARANCE_AUDIO_FILE_URL = 'notifal/onpage/appearance/audio_file_url';

    /**
     * Filters the frontend configuration for appearance settings.
     *
     * Allows developers to modify the frontend configuration generated
     * from appearance settings before it's sent to the client.
     *
     * @param array $config Frontend configuration array
     * @param array $settings Raw appearance settings
     * @return array Modified frontend configuration
     * @since 2.0.0
     */
    public const ONPAGE_APPEARANCE_FRONTEND_CONFIG = 'notifal/onpage/appearance/frontend_config';

    /**
     * Filters the CSS selector used to find the site header for "above header" top-bar placement.
     *
     * When top-bar placement is set to "above header", the notification bar is inserted
     * before the first element matching this selector. Default: header, .site-header, #masthead, #header, [role="banner"]
     *
     * @param string $selector Comma-separated CSS selector(s)
     * @return string Modified selector
     * @since 2.0.0
     */
    public const ONPAGE_TOPBAR_HEADER_SELECTOR = 'notifal/onpage/topbar/header_selector';

    /**
     * Filters extra CSS appended as inline style when OnPage assets load, for theme compatibility
     * with floating bar placement "above header" (e.g. sticky header shells that use padding-top
     * to reserve space). Default empty; use for non-WoodMart themes or fine-tuning.
     *
     * @param string $css Additional CSS (no script tags; keep selectors scoped when possible).
     * @return string Modified CSS string
     * @since 2.0.0
     *
     * Example (child theme `functions.php` or a small plugin; use the constant or the literal hook string):
     *
     *     use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
     *
     *     add_filter( FilterHooks::ONPAGE_TOPBAR_ABOVE_HEADER_COMPAT_CSS, function ( $css ) {
     *         return $css . 'body.notifal-has-topbar-above-header .your-theme-sticky-shell { padding-top: 0 !important; }';
     *     } );
     *
     *     add_filter( 'notifal/onpage/topbar/above_header_compat_css', function ( $css ) {
     *         return $css . 'body.notifal-has-topbar-above-header .your-theme-sticky-shell { padding-top: 0 !important; }';
     *     } );
     */
    public const ONPAGE_TOPBAR_ABOVE_HEADER_COMPAT_CSS = 'notifal/onpage/topbar/above_header_compat_css';

    /**
     * Filters the available animation types for OnPage notifications.
     *
     * Allows developers to modify, extend, or customize the available
     * animation types and their configurations.
     *
     * @param array $animation_types Available animation types configuration
     * @return array Modified animation types configuration
     * @since 2.0.0
     */
    public const ONPAGE_ANIMATION_TYPES = 'notifal/onpage/animation/types';

    /**
     * Filters the total count of OnPage notifications.
     *
     * Allows developers to override the total count of OnPage notifications
     * for the list view.
     *
     * @param int $total Total count of notifications
     * @return int Modified total count
     * @since 2.0.0
     */
    public const ONPAGE_NOTIFICATIONS_TOTAL_COUNT = 'notifal/onpage_notifications/total_count';

    /**
     * Filters the status tabs for OnPage notifications list.
     *
     * Allows developers to modify the status tabs displayed
     * in the OnPage notifications list view.
     *
     * @param array $tabs Status tabs configuration
     * @return array Modified status tabs
     * @since 2.0.0
     */
    public const ONPAGE_NOTIFICATIONS_STATUS_TABS = 'notifal/onpage_notifications/status_tabs';

    // =========================================================================
    // 🌐 FRONTEND FILTER HOOKS
    // =========================================================================

    /**
     * Filter to control OnPage notification asset loading.
     *
     * Allows other components to force loading of OnPage frontend assets
     * when they determine notifications should be shown.
     *
     * @param bool $should_load Whether assets should be loaded
     * @return bool Modified asset loading decision
     * @since 2.0.0
     */
    public const ONPAGE_SHOULD_LOAD_ASSETS = 'notifal/onpage/frontend/should_load_assets';

    /**
     * Filter to indicate if OnPage notifications should be loaded.
     *
     * Components can use this filter to indicate that OnPage notification
     * JavaScript should be loaded on the current page.
     *
     * @param bool $contains_notifications Whether page contains OnPage notifications
     * @return bool Modified notification presence decision
     * @since 2.0.0
     */
    public const ONPAGE_PAGE_CONTAINS_NOTIFICATIONS = 'notifal/onpage/page/contains_notifications';

    /**
     * Filter to indicate if there are active notifications.
     *
     * Components can use this filter to indicate that there are
     * active notifications that should trigger asset loading.
     *
     * @param bool $has_notifications Whether there are active notifications
     * @return bool Modified active notifications status
     * @since 2.0.0
     */
    public const ONPAGE_HAS_ACTIVE_NOTIFICATIONS = 'notifal/onpage/has_active_notifications';

    // =========================================================================
    // 🌐 REST API FILTER HOOKS
    // =========================================================================

    /**
     * Filters the OnPage notification eligibility data before sending to frontend.
     *
     * Allows developers to modify the eligibility data
     * before it's sent to the frontend.
     *
     * @param array $eligibility_data Eligibility data for notifications
     * @param array $context Current page context
     * @return array Modified eligibility data
     * @since 2.0.0
     */
    public const ONPAGE_ELIGIBILITY_DATA = 'notifal/onpage/eligibility/data';

    /**
     * Filters the OnPage notification tracking data before processing.
     *
     * Allows developers to modify the tracking data
     * before it's processed and stored.
     *
     * @param array $tracking_data Tracking data from frontend
     * @return array Modified tracking data
     * @since 2.0.0
     */
    public const ONPAGE_TRACKING_DATA = 'notifal/onpage/tracking/data';

    /**
     * Filters whether to use queue system for tracking events.
     *
     * Allows developers to enable/disable queue system for tracking
     * events processing. When enabled, events are queued for background
     * processing instead of being processed immediately.
     *
     * @param bool $use_queue Whether to use queue system (default: true)
     * @return bool Modified queue usage setting
     * @since 2.0.0
     */
    public const ONPAGE_TRACKING_USE_QUEUE = 'notifal/onpage/tracking/use_queue';

    /**
     * Filters the tracking validation rules.
     *
     * Allows developers to modify validation rules such as
     * required fields and valid event types.
     *
     * @param array $validation_rules Array containing 'required_fields' and 'valid_event_types'
     * @return array Modified validation rules
     * @since 2.0.0
     */
    public const ONPAGE_TRACKING_VALIDATION_RULES = 'notifal/onpage/tracking/validation/rules';

    /**
     * Filters the event data before queuing.
     *
     * Allows developers to modify event data before it's
     * stored in the event queue for background processing.
     *
     * @param array $event_data Event data to be queued
     * @return array Modified event data
     * @since 2.0.0
     */
    public const ONPAGE_EVENT_QUEUE_DATA = 'notifal/onpage/event/queue_data';

    /**
     * Filters the rate limiting settings for OnPage notification API endpoints.
     *
     * Allows developers to modify rate limiting configuration
     * for eligibility and tracking endpoints.
     *
     * @param array $rate_limit_settings Rate limiting configuration
     * @return array Modified rate limiting settings
     * @since 2.0.0
     */
    public const ONPAGE_API_RATE_LIMIT_SETTINGS = 'notifal/onpage/api/rate_limit_settings';

    /**
     * Filters the user eligibility check for OnPage notifications.
     *
     * Allows developers to override or extend the user eligibility logic
     * for specific notifications or user types.
     *
     * @param bool $is_eligible Whether user is eligible for notifications
     * @param array $notification Notification data
     * @param array $user_context User context data
     * @return bool Modified eligibility status
     * @since 2.0.0
     */
    public const ONPAGE_USER_ELIGIBILITY = 'notifal/onpage/user/eligibility';

    /**
     * Filters the notification content before sending to frontend.
     *
     * Allows developers to modify notification content
     * before it's sent to the frontend (e.g., dynamic content, A/B testing).
     *
     * @param array $notification Notification data
     * @param array $context Current page context
     * @return array Modified notification data
     * @since 2.0.0
     */
    public const ONPAGE_NOTIFICATION_CONTENT = 'notifal/onpage/notification/content';

    /**
     * Filters the frontend context data passed to JavaScript.
     *
     * Allows developers to modify or extend the context data used
     * for display rules evaluation and API calls on the frontend.
     *
     * @param array $context Current page context data
     * @return array Modified context data
     * @since 2.0.0
     */
    public const ONPAGE_FRONTEND_CONTEXT = 'notifal/onpage/frontend/context';

    /**
     * Filters archive pseudo post_type slugs used in OnPage visitor context detection.
     *
     * @param string[] $postTypes Archive context post type slugs.
     * @return string[]
     * @since 2.3.7
     */
    public const ONPAGE_ARCHIVE_CONTEXT_POST_TYPES = 'notifal/onpage/archive_context_post_types';

    /**
     * Filters singular post type slugs excluded from OnPage smart targeting.
     *
     * The core `page` post type is excluded by default because WordPress pages are
     * structural routes, not taxonomy-backed content queries.
     *
     * @param string[] $postTypes Excluded singular post type slugs.
     * @return string[]
     * @since 2.3.7
     */
    public const ONPAGE_SMART_TARGETING_EXCLUDED_SINGULAR_POST_TYPES = 'notifal/onpage/smart_targeting_excluded_singular_post_types';

    /**
     * Filters whether Smart Targeting admin UI should be visible for a notification.
     *
     * Pro uses this to hide settings when the selected template only contains page tags.
     *
     * @param bool                 $isVisible        Default visibility flag.
     * @param array<string, mixed> $notificationData Notification edit payload.
     * @return bool
     * @since 2.3.7
     */
    public const ONPAGE_SMART_TARGETING_UI_VISIBLE = 'notifal/onpage/smart_targeting_ui_visible';

    /**
     * Filters client-side user display rules attached to a notification frontend payload.
     *
     * Used for visit-history constraints (new / return / first session) evaluated in JS.
     *
     * @param array<string, string>|null $clientRules    Client rules or null.
     * @param int                          $notificationId Notification post ID.
     * @return array<string, string>|null
     * @since 2.3.5
     * @author Hossein <hossein@notifal.com>
     */
    public const ONPAGE_CLIENT_USER_RULES = 'notifal/onpage/client_user_rules';

    /**
     * Filters client-side WooCommerce cart display rules attached to a notification frontend payload.
     *
     * @param array<string, mixed>|null $clientRules    Client cart rules or null.
     * @param int                         $notificationId Notification post ID.
     * @return array<string, mixed>|null
     * @since 2.3.5
     * @author Hossein <hossein@notifal.com>
     */
    public const ONPAGE_CLIENT_CART_RULES = 'notifal/onpage/client_cart_rules';

    /**
     * Filters client-side page targeting display rules attached to a notification frontend payload.
     *
     * @param array<string, mixed>|null $clientRules    Client page rules or null.
     * @param int                         $notificationId Notification post ID.
     * @return array<string, mixed>|null
     * @since 2.3.10
     * @author Hossein <hossein@notifal.com>
     */
    public const ONPAGE_CLIENT_PAGE_RULES = 'notifal/onpage/client_page_rules';

    /**
     * Filters the WooCommerce cart snapshot used for display rule evaluation.
     *
     * @param array<string, mixed> $cart    Normalized cart snapshot.
     * @param array<string, mixed> $context Full page context.
     * @return array<string, mixed>
     * @since 2.3.5
     * @author Hossein <hossein@notifal.com>
     */
    public const ONPAGE_WOOCOMMERCE_CART_CONTEXT = 'notifal/onpage/woocommerce/cart_context';

    /**
     * Filters cart-derived product IDs before content source pool building.
     *
     * @param int[]                $productIds Resolved product IDs.
     * @param array<string, mixed> $condition  Cart filter condition.
     * @param array<string, mixed> $cart       Cart snapshot.
     * @return int[]
     * @since 2.3.9
     * @author Hossein <hossein@notifal.com>
     */
    public const ONPAGE_CART_PRODUCT_POOL_IDS = 'notifal/onpage/content_source/cart_product_pool_ids';

    /**
     * Filters the dynamic tag context data for OnPage notifications.
     *
     * Allows modification of the context data used for tag replacement
     * in OnPage notification templates during frontend rendering.
     *
     * @param array $context Built context array with tag data
     * @param array $contentSourceSettings Content source settings
     * @param array $pageContext Current page context
     * @return array Modified context data
     * @since 2.0.0
     */
    public const ONPAGE_TAG_CONTEXT = 'notifal/onpage/tag/context';

    /**
     * Filters the service class list registered for the Campaign module.
     *
     * @param string[] $services List of class names with ::register()
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const CAMPAIGN_SERVICES = 'notifal/campaign/services';

    /**
     * Filters the campaign settings before they are used/stored.
     *
     * @param array $settings Campaign settings
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const CAMPAIGN_SETTINGS = 'notifal/campaign/settings';

    /**
     * Filters campaign schedule check results.
     *
     * @param bool     $should_show Whether the campaign schedule allows execution
     * @param int      $campaign_id Campaign ID
     * @param \WP_Post $notification On-page notification post
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const CAMPAIGN_SCHEDULE_CHECK = 'notifal/campaign/schedule_check';

    /**
     * Filters how campaign schedule overrides a notification schedule.
     *
     * @param array $schedule Resolved schedule values
     * @param int $campaign_id Campaign ID
     * @param int $notification_id Notification ID
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const CAMPAIGN_NOTIFICATION_SCHEDULE = 'notifal/notification/campaign_schedule';

    /**
     * Filters on-page notification rows returned by the campaign assignment picker search.
     *
     * @param array<int, array<string, int|string>> $items  Each row: id, title.
     * @param string                                $search Sanitized search string.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public const CAMPAIGN_ONPAGE_PICKER_SEARCH_RESULTS = 'notifal/campaign/onpage_picker/search_results';

    // =========================================================================
    // 🗄️ DATABASE FILTER HOOKS
    // =========================================================================

    /**
     * Filters the list of database table names for cleanup operations.
     *
     * Allows modules to register their table names for database maintenance.
     *
     * @param array $tables Array of table names
     * @return array Modified array of table names
     * @since 2.0.0
     */
    public const DATABASE_TABLE_NAMES = 'notifal/database/table_names';

    // =========================================================================
    // 💰 Analytics & Conversion Tracking Filters
    // =========================================================================

    /**
     * Filters the conversion attribution window in seconds.
     *
     * @param int $window Attribution window in seconds (default: 7 days)
     * @since 2.0.0
     */
    public const ONPAGE_CONVERSION_ATTRIBUTION_WINDOW = 'notifal/onpage/conversion/attribution_window';

    /**
     * Filters the analytics data for OnPage notifications.
     *
     * Allows Pro plugin to restrict detailed analytics for lite users
     * while preserving basic revenue data.
     *
     * @param array $data Analytics data (overview, notifications, etc.)
     * @return array Modified analytics data
     * @since 2.0.0
     */
    public const ONPAGE_ANALYTICS_DATA = 'notifal/onpage/analytics/data';

    /**
     * Filters the list of OnPage notification post IDs used for analytics queries
     * (dashboard totals, charts, tables, revenue scope) after campaign, status, and
     * single-notification filters are applied.
     *
     * @param array<int> $notification_ids Resolved notification IDs.
     * @param array      $filters        Analytics filters (date_range, notification_id, campaign_id, status).
     * @return array<int>
     * @since 2.2.0
     */
    public const ONPAGE_ANALYTICS_FILTERED_NOTIFICATION_IDS = 'notifal/onpage/analytics/filtered_notification_ids';

    /**
     * Filters the start date (Y-m-d) used when analytics date_range is `all_time`.
     *
     * @param string $start_date Resolved start date before the current period end.
     * @return string Start date in Y-m-d format.
     * @since 2.3.0
     */
    public const ONPAGE_ANALYTICS_ALL_TIME_START_DATE = 'notifal/onpage/analytics/all_time_start_date';

    /**
     * Filter the CSS class (without dot) used on custom template elements to count as analytics clicks.
     *
     * @since 2.3.1
     * @param string $class_name Default `notifal-track-click`.
     */
    public const ONPAGE_ANALYTICS_TRACK_CLICK_CLASS = 'notifal/onpage/analytics/track_click_class';

    /**
     * Filter for detecting if Notifal Pro is active and providing analytics.
     * Used to determine if detailed analytics should be shown or upsell UI.
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com> <hossein@notifal.com>
     */
    public const IS_PRO_ANALYTICS_ACTIVE = 'notifal/onpage/is_pro_analytics_active';

    /**
     * Filter for getting Pro analytics service when available.
     * Allows Pro plugin to provide its analytics service to main plugin.
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com> <hossein@notifal.com>
     */
    public const GET_PRO_ANALYTICS_SERVICE = 'notifal/onpage/get_pro_analytics_service';

    /**
     * Filter for getting chart data for analytics dashboard.
     * Allows Pro plugin to provide comprehensive chart data.
     *
     * @param array $default_data Default empty data for free users
     * @param array $filters Analytics filters
     * @return array Chart data with time series
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com> <hossein@notifal.com>
     */
    public const ANALYTICS_GET_CHART_DATA = 'notifal/onpage/analytics/get_chart_data';

    /**
     * Filter for getting top performing notifications.
     * Allows Pro plugin to provide top performing notifications analysis.
     *
     * @param array $default_data Default empty data for free users
     * @param array $notification_ids Array of notification IDs to analyze
     * @param string $start_date Start date for analysis
     * @param string $end_date End date for analysis
     * @return array Top performing notifications data
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com> <hossein@notifal.com>
     */
    public const ANALYTICS_GET_TOP_PERFORMING = 'notifal/onpage/analytics/get_top_performing';

    /**
     * Filter for calculating Click-Through Rate.
     * Allows Pro plugin to provide advanced CTR calculations.
     *
     * @param float $default_value Default CTR value (0.0 for free users)
     * @param int $notification_id Notification ID
     * @param array $date_range Date range with 'start' and 'end' keys
     * @return float CTR percentage
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com> <hossein@notifal.com>
     */
    public const ANALYTICS_CALCULATE_CTR = 'notifal/onpage/analytics/calculate_ctr';

    /**
     * Filter for calculating Conversion Rate.
     * Allows Pro plugin to provide advanced conversion rate calculations.
     *
     * @param float $default_value Default conversion rate value (0.0 for free users)
     * @param int $notification_id Notification ID
     * @param array $date_range Date range with 'start' and 'end' keys
     * @return float Conversion rate percentage
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com> <hossein@notifal.com>
     */
    public const ANALYTICS_CALCULATE_CONVERSION_RATE = 'notifal/onpage/analytics/calculate_conversion_rate';

    /**
     * Filter for calculating Close Rate.
     * Allows Pro plugin to provide advanced close rate calculations.
     *
     * @param float $default_value Default close rate value (0.0 for free users)
     * @param int $notification_id Notification ID
     * @param array $date_range Date range with 'start' and 'end' keys
     * @return float Close rate percentage
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com> <hossein@notifal.com>
     */
    public const ANALYTICS_CALCULATE_CLOSE_RATE = 'notifal/onpage/analytics/calculate_close_rate';

    /**
     * Filter for calculating period Click-Through Rate.
     * Allows Pro plugin to calculate CTR from aggregated statistics.
     *
     * @param float $default_value Default CTR value (0.0 for free users)
     * @param array $stats Period statistics array
     * @return float CTR percentage
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com> <hossein@notifal.com>
     */
    public const ANALYTICS_CALCULATE_PERIOD_CTR = 'notifal/onpage/analytics/calculate_period_ctr';

    /**
     * Filter for calculating period Conversion Rate.
     * Allows Pro plugin to calculate conversion rate from aggregated statistics.
     *
     * @param float $default_value Default conversion rate value (0.0 for free users)
     * @param array $stats Period statistics array
     * @return float Conversion rate percentage
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com> <hossein@notifal.com>
     */
    public const ANALYTICS_CALCULATE_PERIOD_CONVERSION_RATE = 'notifal/onpage/analytics/calculate_period_conversion_rate';

    /**
     * Filter for calculating period Close Rate.
     * Allows Pro plugin to calculate close rate from aggregated statistics.
     *
     * @param float $default_value Default close rate value (0.0 for free users)
     * @param array $stats Period statistics array
     * @return float Close rate percentage
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com> <hossein@notifal.com>
     */
    public const ANALYTICS_CALCULATE_PERIOD_CLOSE_RATE = 'notifal/onpage/analytics/calculate_period_close_rate';

    /**
     * Filter for calculating metric growth rate.
     * Allows Pro plugin to provide advanced growth rate calculations.
     *
     * @param float $default_value Default growth rate value (0.0 for free users)
     * @param float $current Current period value
     * @param float $previous Previous period value
     * @return float Growth rate percentage
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com> <hossein@notifal.com>
     */
    public const ANALYTICS_CALCULATE_METRIC_GROWTH = 'notifal/onpage/analytics/calculate_metric_growth';

    /**
     * Filter active notifications shown in the WordPress dashboard widget.
     *
     * @param array $items Each item: id (int), title (string), edit_url (string).
     * @return array
     * @since 2.3.0
     * @author Hossein <hossein@notifal.com>
     */
    public const DASHBOARD_WIDGET_ACTIVE_NOTIFICATIONS = 'notifal/dashboard_widget/active_notifications';

    /**
     * Filters the order attribution data for a given order/payment.
     *
     * Allows Pro plugin or third parties to modify or enrich the attribution rows
     * returned for a WooCommerce order or EDD payment admin page.
     *
     * @param array $attributionData Attribution rows from the conversions table.
     * @param int   $orderId         WooCommerce order ID or EDD payment ID.
     * @return array Modified attribution data.
     * @since 2.3.0
     * @author Hossein <hossein@notifal.com>
     */
    public const ORDER_ATTRIBUTION_DATA = 'notifal/order_attribution/data';

    /**
     * Filters whether to show the Notifal order column in WooCommerce/EDD order list.
     *
     * @param bool $show  Whether to show the column (default: true).
     * @return bool
     * @since 2.3.0
     * @author Hossein <hossein@notifal.com>
     */
    public const ORDER_ATTRIBUTION_SHOW_COLUMN = 'notifal/order_attribution/show_column';

    /**
     * Filters the capability required for order attribution AJAX requests.
     *
     * @param string $capability Default capability slug.
     * @return string
     * @since 2.3.5
     * @author Hossein <hossein@notifal.com>
     */
    public const ORDER_ATTRIBUTION_AJAX_CAPABILITY = 'notifal/order_attribution/ajax_capability';

    /**
     * Filters whether the current user can view order attribution details via AJAX.
     *
     * @param bool $allowed Default false when no commerce order was matched.
     * @param int  $orderId WooCommerce order ID or EDD payment ID.
     * @return bool
     * @since 2.3.5
     * @author Hossein <hossein@notifal.com>
     */
    public const ORDER_ATTRIBUTION_CAN_VIEW = 'notifal/order_attribution/can_view';

    /**
     * Filter formatted money string for OnPage analytics (admin dashboard, exports, AJAX HTML).
     *
     * @param string $formatted Current plain-text representation.
     * @param float  $amount      Raw numeric amount in store currency units.
     * @return string
     * @since 2.2.4
     * @author Hossein <hossein@notifal.com>
     */
    public const ANALYTICS_FORMAT_MONEY = 'notifal/onpage/analytics/format_money';

    /**
     * Filter JavaScript money configuration for analytics charts (symbol, decimals, separators).
     *
     * @param array<string, mixed> $config   Money settings passed to NotifalAnalyticsConfig.money.
     * @param mixed                $formatter Formatter instance (AnalyticsMoneyFormatter) for advanced extensions.
     * @return array<string, mixed>
     * @since 2.2.4
     * @author Hossein <hossein@notifal.com>
     */
    public const ANALYTICS_MONEY_JS_CONFIG = 'notifal/onpage/analytics/money_js_config';

    // =========================================================================
    // 📅 DATE TAG FILTER HOOKS
    // =========================================================================

    /**
     * Filters the date format before formatting in date tags.
     *
     * Allows developers to modify the date format string used
     * for formatting product and order date tags.
     *
     * @param string $dateFormat The date format string
     * @param string $tagKey The original tag key
     * @param string $dateValue The raw date value
     * @return string Modified date format
     * @since 2.0.0
     */
    public const FILTER_DATE_TAG_FORMAT = 'notifal/tag/date/format';

    /**
     * Filters the final formatted date result in date tags.
     *
     * Allows developers to modify the final formatted date string
     * before it's returned by the tag system.
     *
     * @param string $formattedDate The formatted date string
     * @param string $dateFormat The date format used
     * @param string $tagKey The original tag key
     * @param string $dateValue The raw date value
     * @return string Modified formatted date
     * @since 2.0.0
     */
    public const FILTER_DATE_TAG_RESULT = 'notifal/tag/date/result';

    // =========================================================================
    // 🔔 PRE-CREATED NOTIFICATIONS FILTER HOOKS
    // =========================================================================

    /**
     * Filter the API query parameters before making the request.
     *
     * Allows developers to modify the query parameters sent to the pre-created
     * notifications API endpoint.
     *
     * @param array $params Sanitized query parameters
     * @param array $args Original arguments
     * @return array Modified query parameters
     * @since 2.0.0
     */
    public const PRE_CREATED_NOTIFICATIONS_API_PARAMS = 'notifal/pre_created_notifications/api/params';

    /**
     * Filter the HTTP request arguments before making the API call.
     *
     * Allows developers to modify HTTP request settings like headers,
     * timeout, or authentication for the pre-created notifications API.
     *
     * @param array $args HTTP request arguments
     * @param string $url Full API URL
     * @param array $params Original query parameters
     * @return array Modified HTTP request arguments
     * @since 2.0.0
     */
    public const PRE_CREATED_NOTIFICATIONS_API_REQUEST_ARGS = 'notifal/pre_created_notifications/api/request_args';

    /**
     * Filters dropdown options for image sizes.
     *
     * @param array $options Array of image size options with keys as size names and values as display labels
     * @return array Modified array of image size options
     * @since 2.0.0
     */
    public const IMAGE_SIZE_DROPDOWN_OPTIONS = 'notifal/image_size/dropdown_options';
}
