<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Settings;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Modules\OnPageNotification\Application\Services\Rules\WooCommerceCartContextBuilder;
use Notifal\Modules\OnPageNotification\Application\Services\Rules\WooCommerceCartRulesMatcher;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\WooCommerceCartDisplayRulesService;
use Notifal\Shared\Utils\Helper;

defined('ABSPATH') || exit;

/**
 * Class DisplayRulesService
 *
 * Handles display rules logic, validation, and processing for OnPage Notifications.
 * Manages rule evaluation, sanitization, and formatting for both lite and pro features.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class DisplayRulesService
{

    /**
     * Basic rule types supported by the lite version.
     * Pro features (categories, url_match, users) are added via filters.
     *
     * @since 2.0.0
     * @var array
     */
    /**
     * Allowed visit-history values for the Users display rule (client-side evaluation).
     *
     * @since 2.3.5
     * @var array<int, string>
     */
    private const USER_VISITOR_TYPE_OPTIONS = [
        'any',
        'new_visitor',
        'return_visitor',
        'first_session',
    ];

    /**
     * Basic rule types supported by the lite version.
     * Pro features (categories, url_match, users) are added via filters.
     *
     * @since 2.0.0
     * @var array
     */
    private const LITE_RULE_TYPES = [
        'pages' => [
            'label' => 'Pages',
            'icon' => '📄',
            'post_types' => ['page'],
        ],
        'posts' => [
            'label' => 'Posts',
            'icon' => '📝',
            'post_types' => ['post'],
        ],
        'products' => [
            'label' => 'Products',
            'icon' => '🛍️',
            'post_types' => ['product'],
        ],
        'post_type' => [
            'label' => 'Post Type',
            'icon' => '📚',
            'post_types' => [], // Will be dynamically populated
        ],
    ];

    /**
     * Get all supported rule types with their configurations.
     * Pro features are added via existing filter from the Pro plugin.
     *
     * @return array
     * @since 2.0.0
     */
    public static function getSupportedRuleTypes(): array
    {
        $rule_types = self::LITE_RULE_TYPES;

        // @since 2.3.5 WooCommerce cart conditions when WooCommerce is active.
        if (WooCommerceCartDisplayRulesService::isAvailable()) {
            $rule_types[WooCommerceCartDisplayRulesService::RULE_TYPE] = WooCommerceCartDisplayRulesService::getRuleTypeConfig();
        }

        // If Pro plugin is not active, add pro rule types with pro_feature flag
        // so they appear disabled in the UI
        if (!function_exists('is_notifal_pro_active') || !is_notifal_pro_active()) {
            $pro_rule_types = [
                'categories' => [
                    'label' => 'Categories',
                    'icon' => '🏷️',
                    'taxonomies' => ['category', 'product_cat'],
                    'post_types' => ['post', 'product'],
                    'pro_feature' => true,
                ],
                'url_match' => [
                    'label' => 'URL Conditions',
                    'icon' => '🔗',
                    'pro_feature' => true,
                ],
                'users' => [
                    'label' => 'Users',
                    'icon' => '👤',
                    'pro_feature' => true,
                ],
            ];
            $rule_types = array_merge($rule_types, $pro_rule_types);
        } else {
            // Pro plugin is active, let it add the rule types via filter
            $rule_types = apply_filters(FilterHooks::ONPAGE_DISPLAY_RULES_SUPPORTED_TYPES, $rule_types);
        }

        return $rule_types;
    }

    /**
     * Validate display rules data.
     *
     * @param array $rules
     * @param string $combinationLogic
     * @return array Validation errors
     * @since 2.0.0
     */
    public static function validateRules(array $rules, string $combinationLogic = 'OR'): array
    {
        $errors = [];
        $items = DisplayRulesDataNormalizer::extractItems($rules);
        $ruleCount = count($items);

        // Only validate combination logic when there are multiple rules (where it matters).
        if ($ruleCount > 1 && !in_array($combinationLogic, ['AND', 'OR'], true)) {
            $errors[] = __('Invalid rule combination logic. Must be either AND or OR.', 'notifal');
        }

        if ($ruleCount > 1 && !self::isProFeatureAllowed()) {
            $errors[] = __('Multiple display rules require Notifal Pro. Please activate your license or use only one rule.', 'notifal');
        }

        $supportedTypes = self::getSupportedRuleTypes();

        foreach ($items as $item) {
            $ruleType = $item['type'] ?? '';
            $ruleData = $item['data'] ?? [];

            if (!isset($supportedTypes[$ruleType])) {
                $errors[] = sprintf(__('Unsupported rule type: %s', 'notifal'), $ruleType);
                continue;
            }

            // Pro plugin will handle validation of its own rule types.
            if (!self::isLiteRuleType($ruleType)) {
                continue;
            }

            // @since 2.3.5 WooCommerce cart rule validation.
            if ($ruleType === WooCommerceCartDisplayRulesService::RULE_TYPE) {
                $ruleErrors = WooCommerceCartDisplayRulesService::validateRule($ruleData);
                $errors = array_merge($errors, $ruleErrors);
                continue;
            }

            $validationMethod = 'validate' . ucfirst($ruleType) . 'Rule';
            if (method_exists(self::class, $validationMethod)) {
                $ruleErrors = self::$validationMethod($ruleData);
                $errors = array_merge($errors, $ruleErrors);
            }
        }

        return $errors;
    }

    /**
     * Validate pages rule.
     *
     * @param array $ruleData
     * @return array
     * @since 2.0.0
     */
    private static function validatePagesRule(array $ruleData): array
    {
        $errors = [];

        if (!isset($ruleData['visibility']) || !in_array($ruleData['visibility'], ['all', 'specific', 'exclude'])) {
            $errors[] = __('Invalid page visibility setting.', 'notifal');
        }

        if ($ruleData['visibility'] === 'specific' && empty($ruleData['post_items'])) {
            $errors[] = __('Please select at least one page.', 'notifal');
        }

        return $errors;
    }

    /**
     * Validate posts rule.
     *
     * @param array $ruleData
     * @return array
     * @since 2.0.0
     */
    private static function validatePostsRule(array $ruleData): array
    {
        $errors = [];

        if (!isset($ruleData['visibility']) || !in_array($ruleData['visibility'], ['all', 'specific', 'exclude'])) {
            $errors[] = __('Invalid post visibility setting.', 'notifal');
        }

        if ($ruleData['visibility'] === 'specific' && empty($ruleData['post_items'])) {
            $errors[] = __('Please select at least one post.', 'notifal');
        }

        return $errors;
    }

    /**
     * Validate products rule.
     *
     * @param array $ruleData
     * @return array
     * @since 2.0.0
     */
    private static function validateProductsRule(array $ruleData): array
    {
        $errors = [];

        if (!isset($ruleData['mode']) || !in_array($ruleData['mode'], ['exclude', 'specific'])) {
            $errors[] = __('Invalid product visibility mode.', 'notifal');
        }

        if ($ruleData['mode'] === 'specific' && empty($ruleData['targets'])) {
            $errors[] = __('Please select at least one product.', 'notifal');
        }

        return $errors;
    }

    /**
     * Validate post type rule.
     *
     * @param array $ruleData
     * @return array
     * @since 2.0.0
     */
    private static function validatePostTypeRule(array $ruleData): array
    {
        $errors = [];

        if (!isset($ruleData['visibility']) || !in_array($ruleData['visibility'], ['all', 'exclude', 'specific'])) {
            $errors[] = __('Invalid post type visibility mode.', 'notifal');
        }

        // If visibility is not 'all', post types are required
        if ($ruleData['visibility'] !== 'all') {
            if (empty($ruleData['post_types'])) {
                $errors[] = __('Please select at least one post type.', 'notifal');
            }

            // Check second level if post types are selected
            if (!empty($ruleData['post_types']) && isset($ruleData['items_visibility'])) {
                if (!in_array($ruleData['items_visibility'], ['all', 'exclude', 'specific'])) {
                    $errors[] = __('Invalid post items visibility mode.', 'notifal');
                }

                // If items visibility is not 'all', post items are required
                if ($ruleData['items_visibility'] !== 'all' && empty($ruleData['post_items'])) {
                    $errors[] = __('Please select at least one post item.', 'notifal');
                }
            }
        }

        return $errors;
    }

    /**
     * Check if a notification should be displayed based on current page and rules.
     *
     * @param array $rules
     * @param string $combinationLogic
     * @param int|null $currentPostId
     * @param array $context Optional context data for enhanced rule checking
     * @return bool
     * @since 2.0.0
     */
    public static function shouldDisplay(
        array $rules,
        string $combinationLogic = 'OR',
        ?int $currentPostId = null,
        array $context = [],
        string $visibilityMode = DisplayRulesDataNormalizer::VISIBILITY_SHOW_IF
    ): bool {
        if (!DisplayRulesDataNormalizer::hasActiveRules($rules)) {
            // No rules means show everywhere; visibility mode only applies when rules exist.
            return true;
        }

        $visibilityMode = DisplayRulesDataNormalizer::sanitizeVisibilityMode($visibilityMode);

        $filtered_data = apply_filters(
            FilterHooks::ONPAGE_DISPLAY_RULES_BEFORE_VALIDATION,
            compact('rules', 'combinationLogic', 'visibilityMode'),
            $context
        );

        $rules = $filtered_data['rules'];
        $combinationLogic = $filtered_data['combinationLogic'];
        $visibilityMode = isset($filtered_data['visibilityMode'])
            ? DisplayRulesDataNormalizer::sanitizeVisibilityMode((string) $filtered_data['visibilityMode'])
            : $visibilityMode;

        $currentPostId = $currentPostId ?? get_the_ID();
        $currentUrl = $context['url'] ?? $_SERVER['REQUEST_URI'] ?? '';

        // Allow Pro plugin to override the entire evaluation process.
        $pro_result = apply_filters(
            FilterHooks::ONPAGE_DISPLAY_RULES_EVALUATION_RESULT,
            null,
            $rules,
            $combinationLogic,
            compact('currentPostId', 'currentUrl', 'visibilityMode') + $context
        );

        // If Pro plugin handled the evaluation, return its result.
        if ($pro_result !== null) {
            return (bool) $pro_result;
        }

        $items = DisplayRulesDataNormalizer::extractItems($rules);
        $ruleResults = [];

        foreach ($items as $item) {
            $ruleType = $item['type'] ?? '';
            $ruleData = $item['data'] ?? [];

            // Only process lite rule types in main plugin.
            if (!self::isLiteRuleType($ruleType)) {
                continue;
            }

            // Convert rule type to proper method name (handle underscores).
            $methodName = str_replace('_', '', ucwords($ruleType, '_'));
            $checkMethod = 'check' . $methodName . 'Rule';

            if (method_exists(self::class, $checkMethod)) {
                $ruleResults[] = self::$checkMethod($ruleData, $currentPostId, $currentUrl, $context);
            }
        }

        $matches = false;

        if (empty($ruleResults)) {
            // No lite rules evaluated — default to showing.
            $matches = true;
        } elseif ($combinationLogic === 'AND') {
            $matches = !in_array(false, $ruleResults, true);
        } else {
            $matches = in_array(true, $ruleResults, true);
        }

        if ($visibilityMode === DisplayRulesDataNormalizer::VISIBILITY_HIDE_IF) {
            return !$matches;
        }

        return $matches;
    }

    /**
     * Check if a rule type is available in the lite version.
     *
     * @param string $ruleType Rule type to check
     * @return bool True if lite rule type
     * @since 2.0.0
     */
    private static function isLiteRuleType(string $ruleType): bool
    {
        // @since 2.3.5 Cart rules are lite features when WooCommerce is active.
        if ($ruleType === WooCommerceCartDisplayRulesService::RULE_TYPE && WooCommerceCartDisplayRulesService::isAvailable()) {
            return true;
        }

        return array_key_exists($ruleType, self::LITE_RULE_TYPES);
    }

    /**
     * Check if pro features are allowed (user has active pro license).
     * Uses secure hooks that can only be provided by the legitimate pro plugin.
     *
     * @return bool True if pro features are allowed
     * @since 2.0.0
     */
    private static function isProFeatureAllowed(): bool
    {
        // Use secure hook that only the legitimate pro plugin can provide
        return apply_filters('notifal_pro_multiple_display_rules_allowed', false);
    }

    /**
     * Check post visibility based on rule data.
     *
     * @param array $ruleData
     * @param int $postId
     * @return bool
     * @since 2.0.0
     */
    private static function checkPostVisibility(array $ruleData, int $postId): bool
    {
        $visibility = $ruleData['visibility'] ?? 'all';

        if ($visibility === 'all') {
            return true;
        }

        if ($visibility === 'exclude') {
            return !in_array($postId, $ruleData['post_items'] ?? []);
        }

        // Specific visibility
        return in_array($postId, $ruleData['post_items'] ?? []);
    }

    /**
     * Check target-based visibility (used by products and similar rules).
     *
     * @param array $ruleData
     * @param int $postId
     * @return bool
     * @since 2.0.0
     */
    private static function checkTargetVisibility(array $ruleData, int $postId): bool
    {
        $mode = $ruleData['mode'] ?? 'exclude';
        $targets = $ruleData['targets'] ?? [];

        // If no targets specified, handle based on mode
        if (empty($targets)) {
            return $mode === 'exclude'; // For exclude mode, show everywhere if no targets specified
        }

        $isTargeted = in_array($postId, $targets);
        return $mode === 'exclude' ? !$isTargeted : $isTargeted;
    }

    /**
     * Check pages rule.
     *
     * @param array $ruleData
     * @param int $currentPostId
     * @param string $currentUrl
     * @param array $context
     * @return bool
     * @since 2.0.0
     */
    private static function checkPagesRule(array $ruleData, int $currentPostId, string $currentUrl, array $context = []): bool
    {
        $currentPost = get_post($currentPostId);
        if (!$currentPost || $currentPost->post_type !== 'page') {
            return false; // Not a page, so this rule doesn't match
        }

        return self::checkPostVisibility($ruleData, $currentPostId);
    }

    /**
     * Check posts rule.
     *
     * @param array $ruleData
     * @param int $currentPostId
     * @param string $currentUrl
     * @param array $context
     * @return bool
     * @since 2.0.0
     */
    private static function checkPostsRule(array $ruleData, int $currentPostId, string $currentUrl, array $context = []): bool
    {
        $currentPost = get_post($currentPostId);
        if (!$currentPost || !in_array($currentPost->post_type, ['post', 'product'])) {
            return false; // Not a post/product, so this rule doesn't match
        }

        return self::checkPostVisibility($ruleData, $currentPostId);
    }

    /**
     * Check products rule.
     *
     * @param array $ruleData
     * @param int $currentPostId
     * @param string $currentUrl
     * @param array $context
     * @return bool
     * @since 2.0.0
     */
    private static function checkProductsRule(array $ruleData, int $currentPostId, string $currentUrl, array $context = []): bool
    {
        $currentPost = get_post($currentPostId);
        if (!$currentPost || $currentPost->post_type !== 'product') {
            return false; // This rule doesn't match non-product post types
        }

        return self::checkTargetVisibility($ruleData, $currentPostId);
    }

    /**
     * Check post type rule.
     *
     * @param array $ruleData
     * @param int $currentPostId
     * @param string $currentUrl
     * @param array $context
     * @return bool
     * @since 2.0.0
     */
    private static function checkPostTypeRule(array $ruleData, int $currentPostId, string $currentUrl, array $context = []): bool
    {
        $currentPost = get_post($currentPostId);
        if (!$currentPost) {
            return false; // Not a post, so this rule doesn't match
        }

        $visibility = $ruleData['visibility'] ?? 'all';
        $postTypes = $ruleData['post_types'] ?? [];
        $itemsVisibility = $ruleData['items_visibility'] ?? 'all';
        $postItems = $ruleData['post_items'] ?? [];

        // First level: Check post type visibility
        $postTypeMatches = false;
        switch ($visibility) {
            case 'all':
                $postTypeMatches = true;
                break;
            case 'exclude':
                $postTypeMatches = !in_array($currentPost->post_type, $postTypes);
                break;
            case 'specific':
                $postTypeMatches = in_array($currentPost->post_type, $postTypes);
                break;
        }

        if (!$postTypeMatches) {
            return false;
        }

        // Check post items visibility (only if post type matches and items visibility is not 'all')
        if ($itemsVisibility === 'all') {
            return true;
        }

        $itemMatches = in_array($currentPostId, $postItems);
        $finalItemResult = $itemsVisibility === 'exclude' ? !$itemMatches : $itemMatches;

        return $finalItemResult;
    }

    /**
     * Check WooCommerce cart display rule against the current cart snapshot.
     *
     * @param array $ruleData Rule configuration.
     * @param int $currentPostId Unused for cart rules; kept for signature parity.
     * @param string $currentUrl Unused for cart rules.
     * @param array $context Request context; may include a `cart` snapshot.
     * @return bool True when the cart condition matches.
     * @since 2.3.5
     */
    private static function checkWoocommerceCartRule(array $ruleData, int $currentPostId, string $currentUrl, array $context = []): bool
    {
        // Cart rules require WooCommerce.
        if (!WooCommerceCartDisplayRulesService::isAvailable()) {
            return false;
        }

        // Use provided cart snapshot or build one from the current session.
        $cartContext = isset($context['cart']) && is_array($context['cart'])
            ? $context['cart']
            : WooCommerceCartContextBuilder::build();

        return WooCommerceCartRulesMatcher::matches($ruleData, $cartContext);
    }

    /**
     * Format rules for display in the admin interface.
     *
     * @param array $rules
     * @param string $combinationLogic
     * @return array
     * @since 2.0.0
     */
    public static function formatRulesForDisplay(array $rules, string $combinationLogic = 'OR'): array
    {
        $formatted = [];
        $supported_types = self::getSupportedRuleTypes();
        $items = DisplayRulesDataNormalizer::extractItems($rules);

        foreach ($items as $item) {
            $ruleType = $item['type'] ?? '';
            $ruleData = $item['data'] ?? [];

            if (!isset($supported_types[$ruleType])) {
                continue;
            }

            $config = $supported_types[$ruleType];
            $formatted[] = [
                'type' => $ruleType,
                'icon' => $config['icon'],
                'label' => $config['label'],
                'summary' => self::generateRuleSummary($ruleType, $ruleData),
            ];
        }

        // Add combination logic info.
        if (count($formatted) > 1) {
            $formatted['combination_logic'] = $combinationLogic;
        }

        return $formatted;
    }

    /**
     * Generate a human-readable summary of a rule.
     *
     * @param string $ruleType
     * @param array $ruleData
     * @return string
     * @since 2.0.0
     */
    private static function generateRuleSummary(string $ruleType, array $ruleData): string
    {
        $supported_types = self::getSupportedRuleTypes();
        $config = $supported_types[$ruleType] ?? [];
        $mode = $ruleData['mode'] ?? 'specific';

        switch ($ruleType) {
            case 'pages':
            case 'posts':
            case 'products':
                $visibility = $ruleData['visibility'] ?? 'all';
                $postTypes = $ruleData['post_types'] ?? [];
                $itemsVisibility = $ruleData['items_visibility'] ?? 'all';
                $postItems = $ruleData['post_items'] ?? [];

                if ($visibility === 'all') {
                    return __('All Post Types', 'notifal');
                }

                $postTypeCount = count($postTypes);
                $postTypeText = $postTypeCount === 1 ? __('post type', 'notifal') : __('post types', 'notifal');

                if ($visibility === 'exclude') {
                    $text = __('All Except', 'notifal');
                } else {
                    $text = __('Only', 'notifal');
                }

                $summary = sprintf('%s %s (%d)', $text, $postTypeText, $postTypeCount);

                // Add post items info if applicable
                if ($itemsVisibility !== 'all') {
                    $itemCount = count($postItems);
                    $itemText = $itemCount === 1 ? __('item', 'notifal') : __('items', 'notifal');
                    if ($itemsVisibility === 'exclude') {
                        $summary .= sprintf(' - %s %d %s', __('All Except', 'notifal'), $itemCount, $itemText);
                    } else {
                        $summary .= sprintf(' - %s %d %s', __('Only', 'notifal'), $itemCount, $itemText);
                    }
                }

                return $summary;

            case 'post_type':
                $visibility = $ruleData['visibility'] ?? 'all';
                $postTypes = $ruleData['post_types'] ?? [];
                $itemsVisibility = $ruleData['items_visibility'] ?? 'all';
                $postItems = $ruleData['post_items'] ?? [];

                if ($visibility === 'all') {
                    return __('All Post Types', 'notifal');
                }

                $postTypeCount = count($postTypes);
                $postTypeText = $postTypeCount === 1 ? __('post type', 'notifal') : __('post types', 'notifal');

                if ($visibility === 'exclude') {
                    /* translators: 1: number of post types, 2: "post type" or "post types" */
                    $text = sprintf(__('All Post Types Except %1$d %2$s', 'notifal'), $postTypeCount, $postTypeText);
                } else {
                    /* translators: 1: number of post types, 2: "post type" or "post types" */
                    $text = sprintf(__('Only %1$d %2$s', 'notifal'), $postTypeCount, $postTypeText);
                }

                // Add post type names to the summary
                if (!empty($postTypes)) {
                    $postTypeLabels = [];
                    foreach ($postTypes as $postType) {
                        switch ($postType) {
                            case 'page':
                                $postTypeLabels[] = __('Page', 'notifal');
                                break;
                            case 'post':
                                $postTypeLabels[] = __('Post', 'notifal');
                                break;
                            case 'product':
                                $postTypeLabels[] = __('Product', 'notifal');
                                break;
                            default:
                                $postTypeLabels[] = ucfirst($postType);
                                break;
                        }
                    }
                    $text .= ' (' . implode(', ', $postTypeLabels) . ')';
                }

                // Add second level information if applicable
                if ($itemsVisibility !== 'all' && !empty($postItems)) {
                    $itemCount = count($postItems);
                    $itemText = $itemCount === 1 ? __('item', 'notifal') : __('items', 'notifal');

                    if ($itemsVisibility === 'exclude') {
                        /* translators: 1: number of items, 2: "item" or "items" */
                        $text .= '('. sprintf(__(' All Items Except %1$d %2$s', 'notifal'), $itemCount, $itemText) . ')';
                    } else {
                        /* translators: 1: number of items, 2: "item" or "items" */
                        $text .= '('. sprintf(__(' Only %1$d %2$s', 'notifal'), $itemCount, $itemText) . ')';
                    }
                }

                return $text;

            case 'url_match':
                $paramName = $ruleData['param_name'] ?? '';
                if ($paramName !== '') {
                    $operator = $ruleData['operator'] ?? 'equals';
                    $operatorLabel = ucfirst(str_replace('_', ' ', $operator));
                    if (in_array($operator, ['exists', 'not_exists'], true)) {
                        return sprintf(
                            /* translators: 1: parameter name, 2: operator */
                            __('URL parameter [%1$s] %2$s', 'notifal'),
                            $paramName,
                            $operatorLabel
                        );
                    }
                    $value = $ruleData['value'] ?? '';
                    return sprintf(
                        /* translators: 1: parameter name, 2: operator, 3: value */
                        __('URL parameter [%1$s] %2$s [%3$s]', 'notifal'),
                        $paramName,
                        $operatorLabel,
                        $value
                    );
                }
                $keywords = $ruleData['keywords'] ?? [];
                $mode = $ruleData['mode'] ?? 'contains';
                $count = is_array($keywords) ? count($keywords) : 0;
                return sprintf(
                    /* translators: 1: mode (Contains/Equal/etc.), 2: count */
                    __('URL %1$s (%2$d)', 'notifal'),
                    ucfirst($mode),
                    $count
                );

            case WooCommerceCartDisplayRulesService::RULE_TYPE:
                // @since 2.3.5 Human-readable cart rule summary.
                return WooCommerceCartDisplayRulesService::generateSummary($ruleData);

            default:
                return $config['label'];
        }
    }

    /**
     * Get the default combination logic.
     *
     * @return string
     * @since 2.0.0
     */
    public static function getDefaultCombinationLogic(): string
    {
        return 'OR';
    }

    /**
     * Get available combination logic options.
     *
     * @return array
     * @since 2.0.0
     */
    public static function getCombinationLogicOptions(): array
    {
        return [
            'OR' => __('OR - Show if ANY rule matches', 'notifal'),
            'AND' => __('AND - Show if ALL rules match', 'notifal'),
        ];
    }

    /**
     * Visibility mode options for display rules (show vs hide when rules match).
     *
     * @return array<string, string> Option value => label.
     * @since 2.3.5
     */
    public static function getVisibilityModeOptions(): array
    {
        return [
            DisplayRulesDataNormalizer::VISIBILITY_SHOW_IF => __('Show if', 'notifal'),
            DisplayRulesDataNormalizer::VISIBILITY_HIDE_IF => __("Don't show if", 'notifal'),
        ];
    }

    /**
     * Get rule type options for UI select field
     *
     * @return array Array of rule type options formatted for FieldRenderer::select
     * @since 2.0.0
     */
    public static function getRuleTypeOptions(): array
    {
        $supportedTypes = self::getSupportedRuleTypes();
        $isProActive = self::isProFeatureAllowed();
        $proLabel = !$isProActive ? ' (PRO)' : '';

        $options = [];

        foreach ($supportedTypes as $ruleType => $config) {
            $option = [
                'value' => $ruleType,
                'label' => $config['label']
            ];

            if (!self::isLiteRuleType($ruleType) || isset($config['pro_feature'])) {
                $option['label'] .= $proLabel;
                $option['data-pro-feature'] = $ruleType;
                $option['disabled'] = !$isProActive;
            }

            $options[] = $option;
        }

        return $options;
    }

    /**
     * Sanitize display rules settings
     *
     * @since 2.0.0
     * @param array $settings Raw settings data
     * @return array Sanitized settings
     */
    public static function sanitizeSettings(array $settings): array
    {
        $supportedTypes = self::getSupportedRuleTypes();
        $items = DisplayRulesDataNormalizer::extractItems($settings);
        $sanitizedItems = [];

        foreach ($items as $item) {
            $ruleType = $item['type'] ?? '';
            $ruleData = $item['data'] ?? [];

            if (!isset($supportedTypes[$ruleType])) {
                continue;
            }

            $sanitizedItems[] = [
                'id'   => isset($item['id']) ? sanitize_key((string) $item['id']) : DisplayRulesDataNormalizer::generateRuleId(),
                'type' => $ruleType,
                'data' => self::sanitizeRuleData($ruleType, $ruleData),
            ];
        }

        $sanitized = DisplayRulesDataNormalizer::wrapItems($sanitizedItems);

        return apply_filters(FilterHooks::ONPAGE_DISPLAY_RULES_SANITIZED_SETTINGS, $sanitized, $settings);
    }

    /**
     * Sanitize individual rule data
     *
     * @since 2.0.0
     * @param string $ruleType Rule type
     * @param array $ruleData Rule data
     * @return array Sanitized rule data
     */
    private static function sanitizeRuleData(string $ruleType, array $ruleData): array
    {
        $sanitized = [];

        switch ($ruleType) {
            case 'pages':
                $sanitized['visibility'] = Helper::sanitizeInput($ruleData['visibility'] ?? 'all', 'text');
                $sanitized['post_types'] = self::sanitizePostTypeSlugs($ruleData['post_types'] ?? []);
                $sanitized['items_visibility'] = Helper::sanitizeInput($ruleData['items_visibility'] ?? 'all', 'text');
                $sanitized['post_items'] = self::sanitizePostIds($ruleData['post_items'] ?? []);
                break;

            case 'posts':
                $sanitized['visibility'] = Helper::sanitizeInput($ruleData['visibility'] ?? 'all', 'text');
                $sanitized['post_types'] = self::sanitizePostTypeSlugs($ruleData['post_types'] ?? []);
                $sanitized['items_visibility'] = Helper::sanitizeInput($ruleData['items_visibility'] ?? 'all', 'text');
                $sanitized['post_items'] = self::sanitizePostIds($ruleData['post_items'] ?? []);
                break;

            case 'products':
                $sanitized['mode'] = Helper::sanitizeInput($ruleData['mode'] ?? 'exclude', 'text');
                $sanitized['targets'] = self::sanitizePostIds($ruleData['targets'] ?? []);
                break;

            case 'post_type':
                $sanitized['visibility'] = Helper::sanitizeInput($ruleData['visibility'] ?? 'all', 'text');
                $sanitized['post_types'] = self::sanitizePostTypeSlugs($ruleData['post_types'] ?? []);
                $sanitized['items_visibility'] = Helper::sanitizeInput($ruleData['items_visibility'] ?? 'all', 'text');
                $sanitized['post_items'] = self::sanitizePostIds($ruleData['post_items'] ?? []);
                break;

            case 'categories': {
                $allowedCategoryModes = ['all_archives', 'exclude', 'specific'];
                $mode = Helper::sanitizeInput($ruleData['mode'] ?? 'all_archives', 'text');
                $sanitized['mode'] = in_array($mode, $allowedCategoryModes, true) ? $mode : 'all_archives';
                $allowedPostTypeVisibility = ['all', 'exclude', 'specific'];
                $postTypesVisibility = Helper::sanitizeInput($ruleData['post_types_visibility'] ?? 'specific', 'text');
                $sanitized['post_types_visibility'] = in_array($postTypesVisibility, $allowedPostTypeVisibility, true)
                    ? $postTypesVisibility
                    : 'specific';
                $sanitized['post_types'] = self::sanitizePostTypeSlugs($ruleData['post_types'] ?? []);
                $sanitized['targets'] = self::sanitizeTermIds($ruleData['targets'] ?? []);
                break;
            }

            case 'url_match': {
                $sanitized['mode'] = Helper::sanitizeInput($ruleData['mode'] ?? 'contains', 'text');
                $sanitized['keywords'] = self::sanitizeUrlPatterns($ruleData['keywords'] ?? []);
                $paramName = isset($ruleData['param_name']) ? sanitize_text_field($ruleData['param_name']) : '';
                if ($paramName !== '') {
                    $allowedOperators = ['equals', 'contains', 'not_equals', 'not_contains', 'exists', 'not_exists'];
                    $operator = Helper::sanitizeInput($ruleData['operator'] ?? 'equals', 'text');
                    $sanitized['param_name'] = $paramName;
                    $sanitized['operator'] = in_array($operator, $allowedOperators, true) ? $operator : 'equals';
                    $sanitized['value'] = sanitize_text_field($ruleData['value'] ?? '');
                }
                break;
            }

            case 'users':
                $sanitized['user_type'] = Helper::sanitizeInput($ruleData['user_type'] ?? 'guest', 'text');
                $sanitized['limit_by_roles'] = (bool) ($ruleData['limit_by_roles'] ?? false);
                $sanitized['roles'] = self::sanitizeUserRoles($ruleData['roles'] ?? []);
                // @since 2.3.5 Visit-history filter (evaluated client-side when not "any").
                $visitorType = Helper::sanitizeInput($ruleData['visitor_type'] ?? 'any', 'text');
                $sanitized['visitor_type'] = in_array($visitorType, self::USER_VISITOR_TYPE_OPTIONS, true)
                    ? $visitorType
                    : 'any';
                break;

            case WooCommerceCartDisplayRulesService::RULE_TYPE:
                // @since 2.3.5 WooCommerce cart display rule sanitization.
                return WooCommerceCartDisplayRulesService::sanitizeRule($ruleData);
        }

        return $sanitized;
    }

    /**
     * Sanitize post IDs array
     *
     * @param array $postIds Post IDs
     * @return array Sanitized post IDs
     * @since 2.0.0
     */
    private static function sanitizePostIds(array $postIds): array
    {
        return array_map('absint', array_filter($postIds, 'is_numeric'));
    }

    /**
     * Sanitize term IDs array
     *
     * @param array $termIds Term IDs
     * @return array Sanitized term IDs
     * @since 2.0.0
     */
    private static function sanitizeTermIds(array $termIds): array
    {
        return array_map('absint', array_filter($termIds, 'is_numeric'));
    }

    /**
     * Sanitize URL patterns array
     *
     * @param array $patterns URL patterns
     * @return array Sanitized URL patterns
     * @since 2.0.0
     */
    private static function sanitizeUrlPatterns(array $patterns): array
    {
        return array_map(function($pattern) {
            return sanitize_text_field($pattern);
        }, array_filter($patterns, 'is_string'));
    }

    /**
     * Sanitize user roles array
     *
     * @param array $roles User roles
     * @return array Sanitized user roles
     * @since 2.0.0
     */
    private static function sanitizeUserRoles(array $roles): array
    {
        return array_map(function($role) {
            return sanitize_text_field($role);
        }, array_filter($roles, 'is_string'));
    }

    /**
     * Sanitize post type slugs array
     *
     * @param array $slugs Post type slugs
     * @return array Sanitized post type slugs
     * @since 2.0.0
     */
    private static function sanitizePostTypeSlugs(array $slugs): array
    {
        return array_map(function($slug) {
            return sanitize_text_field($slug);
        }, array_filter($slugs, 'is_string'));
    }
}
