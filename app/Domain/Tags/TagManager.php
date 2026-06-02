<?php

namespace Notifal\Domain\Tags;

use Notifal\Domain\Tags\Exceptions\InvalidTagException;
use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Domain\Settings\Services\SettingsService;
use Notifal\Domain\Settings\Constants\SettingsKeys;
use Notifal\Domain\Tags\TagsHelper;
use Notifal\Infrastructure\WordPress\Support\PluginDetector;

defined('ABSPATH') || exit;

/**
 * Class TagManager
 *
 * Handles registration and rendering of all tags.
 *
 * @package Notifal\Domain\Tags
 * @author Hossein <hossein@notifal.com>
 * @since 2.0.0
 */
class TagManager
{
    /**
     * @var Tag[] List of registered tags, keyed by their identifier
     */
    private array $tags = [];

    /**
     * @var SettingsService Settings service instance
     */
    private SettingsService $settingsService;

    /**
     * TagManager constructor.
     *
     * @param SettingsService $settingsService Settings service for tag filtering
     * @since 2.0.0
     */
    public function __construct(SettingsService $settingsService)
    {
        $this->settingsService = $settingsService;
    }


    /**
     * Register a new tag.
     *
     * @param Tag $tag
     * @throws InvalidTagException
     * @since 2.0.0
     */
    public function registerTag(Tag $tag): void
    {
        $key = $tag->getKey();

        if (isset($this->tags[$key])) {
            throw new InvalidTagException(
                sprintf(__('Tag with key "%s" is already registered.', 'notifal'), sanitize_text_field($key))
            );
        }

        $this->tags[$key] = $tag;

        /**
         * Action: A new tag has been registered
         * @since 2.0.0
         */
        do_action(ActionHooks::ACTION_ON_TAG_REGISTERED, $tag);
    }


    /**
     * Get a registered tag by its key.
     *
     * @param string $key
     * @return Tag|null
     * @since 2.0.0
     */
    public function get(string $key): ?Tag
    {
        return $this->tags[$key] ?? null;
    }

    /**
     * Get all registered tags.
     *
     * @return Tag[]
     * @since 2.0.0
     */
    public function all(): array
    {
        return $this->tags;
    }

    /**
     * Get all registered tags filtered by settings.
     *
     * Returns only tags from categories that are enabled in settings.
     *
     * @return Tag[]
     * @since 2.0.0
     */
    public function allFiltered(): array
    {
        $filtered = [];
        $categoryStatus = [];

        foreach ($this->tags as $tag) {
            $category = $tag->getCategory();
            
            if (!isset($categoryStatus[$category])) {
                $categoryStatus[$category] = $this->isTagCategoryEnabled($category);
            }

            if ($categoryStatus[$category]) {
                $filtered[] = $tag;
            }
        }
        
        return $filtered;
    }

    /**
     * Check if a tag category is enabled based on settings.
     *
     * Maps tag categories to their corresponding setting keys and checks if enabled.
     * Supports Core WordPress tags, plugin-dependent tags, and custom post type tags.
     *
     * @param string $category Tag category to check
     * @return bool True if category is enabled
     * @since 2.0.0
     */
    private function isTagCategoryEnabled(string $category): bool
    {
        // Use centralized category mapping from SettingsKeys
        $categoryMap = SettingsKeys::getCategoryMapping();

        if (isset($categoryMap[$category])) {
            $isEnabled = $this->settingsService->get($categoryMap[$category], true);

            // For plugin-dependent categories, also check plugin availability
            if ($isEnabled && in_array($category, ['products', 'orders', 'cart'], true)) {
                return PluginDetector::isWooCommerceActive();
            }

            return $isEnabled;
        }

        // Handle custom post type categories dynamically
        // Custom post types follow pattern: {post_type}_tags_enabled
        if ($this->isCustomPostTypeCategory($category)) {
            // Check if Notifal Pro is active and connected (required for generated tags)
            // Use secure hook that only the legitimate pro plugin can provide
            if (!apply_filters('notifal_pro_comment_tags_allowed', false)) {
                return false;
            }

            $settingKey = $category . '_tags_enabled';
            // Default to true for custom post type categories when first created
            // This ensures newly generated tags are visible by default
            return $this->settingsService->get($settingKey, true);
        }

        // Unknown categories are disabled by default
        return false;
    }

    /**
     * Check if a category represents a custom post type.
     *
     * Custom post type categories are those not in the core WordPress or WooCommerce categories.
     * They should have generated tags available to be considered valid.
     *
     * @param string $category Category to check
     * @return bool True if it's a custom post type category
     * @since 2.0.0
     */
    private function isCustomPostTypeCategory(string $category): bool
    {
        // Core and plugin categories that are NOT custom post types
        $coreCategories = ['users', 'posts', 'pages', 'comments', 'products', 'orders', 'cart'];

        if (in_array($category, $coreCategories)) {
            return false;
        }

        // Check if there are generated tags for this post type
        $generatedPostTypes = $this->settingsService->get('generated_posttype_list', []);
        return in_array($category, $generatedPostTypes);
    }

    /**
     * Render content by replacing tags with their resolved values.
     *
     * @param string $content The content containing tag placeholders to replace.
     * @param array  $context Context data for tag resolution (order, product, user objects).
     * @return string Content with all tags replaced by their resolved values.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public function render(string $content, array $context = []): string
    {
        // Add template content to context for context-aware tag resolution
        $context['template_content'] = $content;

        // Action: Before rendering all tags
        do_action(ActionHooks::ACTION_BEFORE_TAGMANAGER_RENDER, $this, $content, $context);

        // Replace all static and dynamic tags in the content
        foreach ($this->tags as $tag) {
            if (method_exists($tag, 'isDynamic') && $tag->isDynamic()) {
                $this->processDynamicTag($tag, $content, $context);
            } else {
                $this->processStaticTag($tag, $content, $context);
            }
        }

        // Filter: Allow modification of the final content after rendering
        $content = apply_filters(FilterHooks::FILTER_MODIFY_TAGS_LIST, $content, $context);

        // Action: After rendering all tags
        do_action(ActionHooks::ACTION_AFTER_TAGMANAGER_RENDER, $this, $content, $context);

        return $content;
    }


    /**
     * Check if a tag with the given key exists.
     *
     * @param string $key
     * @return bool
     * @since 2.0.0
     */
    public function has(string $key): bool
    {
        return isset($this->tags[$key]);
    }


    /**
     * Resolve all registered tags for the given context.
     *
     * @param array $context Context data (e.g., product, user).
     * @return array Array of tag keys and their resolved values.
     * @since 2.0.0
     */
    public function resolveForContext(array $context = []): array
    {
        $resolved = [];

        foreach ($this->tags as $tag) {
            $resolved[$tag->getKey()] = $tag->resolve($context);
        }

        return $resolved;
    }

    /**
     * Process a dynamic tag by finding all matching instances and replacing them.
     *
     * @param Tag    $tag     The dynamic tag to process.
     * @param string &$content The content to modify (passed by reference).
     * @param array  $context Context data for tag resolution.
     * @return void
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private function processDynamicTag(Tag $tag, string &$content, array $context): void
    {
        $tagTemplate = $tag->getKey(); // e.g., "order_meta_{key}"
        $prefix = str_replace('{key}', '', $tagTemplate); // e.g., "order_meta_"

        // Find all tags that start with this prefix using more precise pattern
        $pattern = '/\{' . preg_quote($prefix, '/') . '([a-zA-Z0-9_]+)\}/';

        $content = preg_replace_callback($pattern, function($matches) use ($tag, $context, $prefix) {
            $fullMatch = $matches[0]; // e.g., "{order_meta_billing_first_name}"
            $dynamicKey = $matches[1]; // e.g., "billing_first_name"
            $actualTagKey = $prefix . $dynamicKey; // e.g., "order_meta_billing_first_name"

            // Resolve the tag with the full key
            $value = $tag->resolve($context, $actualTagKey);

            // Sanitize value to prevent HTML injection while preserving intended formatting
            return TagsHelper::sanitizeTagValue($value);

        }, $content);
    }

    /**
     * Process a static tag by replacing its placeholder with the resolved value.
     *
     * @param Tag    $tag     The static tag to process.
     * @param string &$content The content to modify (passed by reference).
     * @param array  $context Context data for tag resolution.
     * @return void
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private function processStaticTag(Tag $tag, string &$content, array $context): void
    {
        // Use more precise replacement to avoid accidental matches
        $pattern = '/\{' . preg_quote($tag->getKey(), '/') . '\}/';

        $content = preg_replace_callback($pattern, function($matches) use ($tag, $context) {
            $value = $tag->resolve($context);

            // Sanitize value to prevent HTML injection while preserving intended formatting
            return TagsHelper::sanitizeTagValue($value);

        }, $content);
    }

}
