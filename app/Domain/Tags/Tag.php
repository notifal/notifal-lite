<?php

namespace Notifal\Domain\Tags;

use Notifal\Domain\Tags\Enums\TagCategory;
use Notifal\Domain\Tags\Exceptions\InvalidTagException;
use Closure;
use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;

defined('ABSPATH') || exit;

/**
 * Class Tag
 *
 * Represents a single tag definition.
 *
 * @package Notifal\Domain\Tags
 * @author Hossein <hossein@notifal.com>
 * @since 2.0.0
 */
class Tag
{
    /**
     * @var string Unique identifier for the tag, e.g., "product_name"
     */
    private string $key;

    /**
     * @var string Human-readable name for admin UI, e.g., "Product Name"
     */
    private string $label;

    /**
     * @var string Optional description for the tag
     */
    private string $description;

    /**
     * @var string Category of the tag, e.g., "Products", "Sales"
     */
    private string $category;

    /**
     * @var Closure Resolver that returns the value of this tag
     */
    private Closure $resolver;

    /**
     * Tag constructor.
     *
     * @param string  $key
     * @param string  $label
     * @param Closure $resolver
     * @param string  $category
     * @param string  $description
     *
     * @throws InvalidTagException
     * @since 2.0.0
     */
    public function __construct(
        string $key,
        string $label,
        Closure $resolver,
        string $category = 'General',
        string $description = ''
    ) {
        if (empty($key)) {
            throw new InvalidTagException(
                sprintf(__('Tag key "%s" is invalid.', 'notifal'), sanitize_text_field($key))
            );
        }

        $this->key = $key;
        $this->label = $label;
        $this->resolver = $resolver;
        $this->category = $category;
        $this->description = $description;
    }

    /**
     * Get the tag key (identifier).
     *
     * @return string
     * @since 2.0.0
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * Get the human-readable label.
     *
     * @return string
     * @since 2.0.0
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * Get the tag category.
     *
     * Validates the category against allowed categories and returns
     * the category or falls back to GENERAL for invalid ones.
     *
     * @return string
     * @since 2.0.0
     */
    public function getCategory(): string
    {
        $validCategories = TagCategory::all();

        // Category is valid if it exists in the allowed categories list
        if (in_array($this->category, $validCategories, true)) {
            return $this->category;
        }

        // For custom post type categories, allow them even if not predefined
        // This enables extensibility for custom post types added via settings
        return $this->category;
    }

    /**
     * Get the tag description.
     *
     * @return string
     * @since 2.0.0
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Check if the tag is dynamic (contains {key} placeholder).
     *
     * @return bool
     * @since 2.0.0
     */
    public function isDynamic(): bool
    {
        return strpos($this->getKey(), '{key}') !== false;
    }


    /**
     * Resolve the tag value using the provided context.
     *
     * @param array $context
     * @param string|null $actualKey The actual tag key for dynamic tags (e.g., order_meta_billing_first_name)
     * @return string
     * @since 2.0.0
     */
    public function resolve(array $context = [], ?string $actualKey = null): string
    {
        // Action: Before resolving a tag value
        do_action(ActionHooks::ACTION_BEFORE_TAG_RESOLVE, $this, $context);

        // For dynamic tags, use the actual key; for static, use the default
        if ($this->isDynamic() && $actualKey !== null) {
            $value = call_user_func($this->resolver, $context, $actualKey);
        } elseif ($this->isDynamic()) {
            $value = call_user_func($this->resolver, $context, $this->key);
        } else {
            $value = call_user_func($this->resolver, $context);
        }

        // Filter: Allow modification of the resolved value
        $value = apply_filters(FilterHooks::FILTER_MODIFY_TAG_VALUE, $value, $this, $context);

        // Action: After resolving a tag value
        do_action(ActionHooks::ACTION_AFTER_TAG_RESOLVE, $this, $value, $context);

        return (string) ($value ?? '');
    }

}
