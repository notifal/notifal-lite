<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Tag;

use Notifal\Core\Foundation\Container;
use Notifal\Domain\Tags\TagManager;
use Notifal\Domain\Tags\Services\TagDetector;
use Notifal\Domain\Tags\Enums\TagCategory;

defined('ABSPATH') || exit;

/**
 * Service to detect which tag categories are used in template content.
 *
 * @package Notifal\Modules\OnPageNotification\Application\Services
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class TagCategoryDetector
{
    /**
     * @var TagManager
     */
    private TagManager $tagManager;

    /**
     * TagCategoryDetector constructor.
     *
     * @since 2.0.0
     */
    public function __construct()
    {
        $this->tagManager = Container::getInstance()->get(TagManager::class);
    }

    /**
     * Detect which tag categories are used in the given template content.
     *
     * @param string $templateContent The template content to analyze.
     * @return array Array of detected categories.
     * @since 2.0.0
     */
    public function detectCategories(string $templateContent): array
    {
        if (empty($templateContent)) {
            return [];
        }

        // Check for order context first (takes priority)
        if ($this->hasOrderContext($templateContent)) {
            return [TagCategory::ORDERS];
        }

        // Check for sale/product context
        if ($this->hasSaleContext($templateContent)) {
            return [TagCategory::PRODUCTS];
        }

        // Fallback to individual tag detection
        return $this->detectCategoriesByIndividualTags($templateContent);
    }

    /**
     * Detect categories by analyzing individual tags using the Tags system.
     *
     * @param string $templateContent The template content to analyze.
     * @return array Array of detected categories.
     * @since 2.0.0
     */
    private function detectCategoriesByIndividualTags(string $templateContent): array
    {
        $detectedCategories = [];

        // Extract all tag keys from template content using regex
        $tagPattern = '/\{([^}]+)\}/';
        preg_match_all($tagPattern, $templateContent, $matches);

        if (empty($matches[1])) {
            return [];
        }

        $foundTagKeys = $matches[1];

        // Check each found tag against registered tags in the Tags system
        foreach ($foundTagKeys as $tagKey) {
            $tag = $this->tagManager->get($tagKey);
            
            if ($tag) {
                $category = $tag->getCategory();
                if (!in_array($category, $detectedCategories, true)) {
                    $detectedCategories[] = $category;
                }
            }
        }

        return $detectedCategories;
    }


    /**
     * Check if template content contains order-related context.
     *
     * @param string $content Template content to analyze.
     * @return bool True if content has order context.
     * @since 2.0.0
     */
    private function hasOrderContext(string $content): bool
    {
        return TagDetector::hasOrderTags($content);
    }

    /**
     * Check if template content contains product-related context.
     *
     * @param string $content Template content to analyze.
     * @return bool True if content has product context.
     * @since 2.0.0
     */
    private function hasSaleContext(string $content): bool
    {
        return TagDetector::hasProductTags($content);
    }

    /**
     * Get restriction types that should be hidden based on detected categories.
     *
     * @param array $detectedCategories Array of detected tag categories.
     * @return array Array of restriction types to hide.
     * @since 2.0.0
     * @since 2.4.4 Hides the cart information card when no cart tags are detected.
     */
    public function getHiddenRestrictions(array $detectedCategories): array
    {
        $hiddenRestrictions = [];

        // If no product tags detected, hide product restrictions
        if (!in_array(TagCategory::PRODUCTS, $detectedCategories, true)) {
            $hiddenRestrictions[] = 'product';
        }

        // If no order tags detected, hide order restrictions
        if (!in_array(TagCategory::ORDERS, $detectedCategories, true)) {
            $hiddenRestrictions[] = 'order';
        }

        // If no user tags detected, hide user restrictions
        if (!in_array(TagCategory::USERS, $detectedCategories, true)) {
            $hiddenRestrictions[] = 'user';
        }

        // If no cart tags detected, hide the cart information card
        if (!in_array(TagCategory::CART, $detectedCategories, true)) {
            $hiddenRestrictions[] = 'cart';
        }

        // If no post tags detected, hide post restrictions
        if (!in_array(TagCategory::POSTS, $detectedCategories, true)) {
            $hiddenRestrictions[] = 'post';
        }

        // If no page tags detected, hide page restrictions
        if (!in_array(TagCategory::PAGES, $detectedCategories, true)) {
            $hiddenRestrictions[] = 'page';
        }

        // Comment restrictions are handled by Pro plugin
        $proHiddenRestrictions = apply_filters('notifal_pro_get_hidden_comment_restrictions', $detectedCategories);
        $hiddenRestrictions = array_merge($hiddenRestrictions, $proHiddenRestrictions);

        // If no custom post type tags detected, hide custom post type restrictions
        // Custom post types are handled dynamically - check if any non-core categories are detected
        $hasCustomPostTypeTags = false;
        $coreCategories = [
            TagCategory::PRODUCTS,
            TagCategory::ORDERS,
            TagCategory::USERS,
            TagCategory::CART,
            TagCategory::POSTS,
            TagCategory::PAGES,
            TagCategory::COMMENTS,
            TagCategory::GENERAL,
        ];

        foreach ($detectedCategories as $category) {
            if (!in_array($category, $coreCategories, true)) {
                $hasCustomPostTypeTags = true;
                break;
            }
        }

        if (!$hasCustomPostTypeTags) {
            $hiddenRestrictions[] = 'custom_posttype';
        }

        return $hiddenRestrictions;
    }
} 
