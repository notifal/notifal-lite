<?php

namespace Notifal\Shared\AdminUI\Fields;

use Notifal\Domain\Tags\Tag;
use Notifal\Domain\Tags\TagsHelper;
use Notifal\Shared\AdminUI\Fields\FieldRenderer;
use Notifal\Shared\Utils\LinkManager;

defined('ABSPATH') || exit;

/**
 * Class TagsRenderer
 *
 * Provides rendering logic for displaying tags
 * in admin panels, Elementor, Gutenberg, and other UI components.
 *
 * @package Notifal\Shared\AdminUI\Fields
 * @since 2.0.0
 */
class TagsRenderer
{
    /**
     * Render the enhanced tags block with search, categories, and improved UX.
     *
     * @param Tag[] $tags List of Tag objects to display.
     * @param array $options Optional configuration:
     *                       - 'show_info' (bool) Show info alert. Default true.
     *                       - 'show_warning' (bool) Show warning alert. Default true.
     *                       - 'show_search' (bool) Show search box. Default true.
     *                       - 'show_categories' (bool) Show category tabs. Default true.
     *                       - 'container_class' (string) Extra CSS class for container.
     * @return string Rendered HTML output.
     * @since 2.0.0
     */
    public static function render(array $tags, array $options = []): string
    {
        if (empty($tags)) {
            return '';
        }

        $showInfo        = $options['show_info'] ?? true;
        $showWarning     = $options['show_warning'] ?? true;
        $showSearch      = $options['show_search'] ?? true;
        $showCategories  = $options['show_categories'] ?? true;
        $containerCls    = esc_attr($options['container_class'] ?? 'notifal-tags-container');

        // Group tags by category
        $tagsByCategory = TagsHelper::groupByCategory($tags);
        
        $uniqueId = 'tags-' . uniqid();

        ob_start();
        ?>
        <?php if ($showInfo): ?>
        <div class="notifal-message notifal-panel-alert-info">
            <?php esc_html_e('Use these tags to customize your text. Click on a tag to copy.', 'notifal'); ?>
            <a href="<?php echo esc_url(LinkManager::tagsDoc('notifal','elementor.panel.tags_tooltip')); ?>"
               target="_blank"
               rel="noopener noreferrer"
               class="notifal-link">
                <?php esc_html_e('View guide', 'notifal'); ?>
            </a>
        </div>
        <?php endif; ?>

        <?php if ($showWarning): ?>
        <div class="notifal-message notifal-panel-alert-danger">
            <?php esc_html_e("Preview data may not match actual values. It's for visualization only.", 'notifal'); ?>
        </div>
        <?php endif; ?>

        <div class="notifal-tags-header">
            <h4 class="notifal-tags-title"><?php esc_html_e('Available Tags', 'notifal'); ?></h4>

            <?php if ($showSearch): ?>
            <div class="notifal-search-wrapper">
                <input type="text"
                       class="notifal-search-input"
                       id="notifal-search-<?php echo esc_attr($uniqueId); ?>"
                       placeholder="<?php esc_attr_e('Search tags...', 'notifal'); ?>"
                       autocomplete="off">
                <span class="notifal-search-icon notifal-icon notifal-icon-search notifal-icon.size-16"></span>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($showCategories && count($tagsByCategory) > 1): ?>
        <div class="notifal-category-tabs" data-container="<?php echo esc_attr($uniqueId); ?>">
            <button class="notifal-category-tab active"
                    data-category="all">
                <?php esc_html_e('All', 'notifal'); ?>
                <span class="notifal-category-count">(<?php echo count($tags); ?>)</span>
            </button>
            <?php foreach ($tagsByCategory as $category => $categoryTags): ?>
                <button class="notifal-category-tab"
                        data-category="<?php echo esc_attr($category); ?>">
                    <?php echo esc_html(self::getCategoryDisplayName($category)); ?>
                    <span class="notifal-category-count">(<?php echo count($categoryTags); ?>)</span>
                </button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="notifal-tags-container <?php echo $containerCls; ?>"
             id="notifal-tags-<?php echo esc_attr($uniqueId); ?>"
             data-search-input="notifal-search-<?php echo esc_attr($uniqueId); ?>">

            <?php foreach ($tagsByCategory as $category => $categoryTags): ?>
                <div class="notifal-tags-category"
                     data-category="<?php echo esc_attr($category); ?>">
                    <div class="notifal-category-header">
                        <h5 class="notifal-category-title"><?php echo esc_html(self::getCategoryDisplayName($category)); ?></h5>
                        <span class="notifal-category-badge"><?php echo count($categoryTags); ?> tags</span>
                    </div>
                    <div class="notifal-tags-grid">
                        <?php foreach ($categoryTags as $tag): ?>
                            <?php
                            $isDynamic = $tag->isDynamic();
                            $tooltipText = $isDynamic
                                ? $tag->getDescription() . ' ' . __( 'Replace {key} to use. Click for available keys.', 'notifal' )
                                : $tag->getDescription();

                            // Determine entity type for dynamic tags
                            $entityType = $isDynamic ? self::determineEntityType($tag->getKey()) : '';
                            ?>
                            <span class="notifal-tooltip-inline notifal-tag<?php echo $isDynamic ? ' notifal-tag-dynamic' : ''; ?>"
                                  data-category="<?php echo esc_attr($category); ?>"
                                  data-tag-text="<?php echo esc_attr($tag->getKey()); ?>"
                                  data-label="<?php echo esc_attr($tag->getLabel()); ?>"
                                  <?php if ($isDynamic): ?>
                                  data-entity-type="<?php echo esc_attr($entityType); ?>"
                                  data-tag-key="<?php echo esc_attr($tag->getKey()); ?>"
                                  <?php endif; ?>>
                                <span class="notifal-tag-text">{<?php echo esc_html($tag->getKey()); ?>}</span>
                                <?php if ($isDynamic): ?>
                                    <span class="notifal-dynamic-icon"></span>
                                <?php endif; ?>
                                <?php FieldRenderer::tooltip($tooltipText, [], true); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="notifal-no-results" style="display: none;">
                <div class="notifal-no-results-content">
                    <span class="notifal-no-results-icon notifal-icon notifal-icon-search notifal-icon.size-32"></span>
                    <p><?php esc_html_e('No tags found matching your search.', 'notifal'); ?></p>
                </div>
            </div>
        </div>

        <?php
        return ob_get_clean();
    }

    /**
     * Determine the entity type for a dynamic tag based on its key prefix.
     *
     * @param string $tagKey The tag key to analyze.
     * @return string The entity type ('user', 'order', 'product', or empty string).
     * @since 2.0.0
     */
    private static function determineEntityType(string $tagKey): string
    {
        if (strpos($tagKey, 'user_meta_') === 0) {
            return 'user';
        }

        if (strpos($tagKey, 'order_meta_') === 0 ||
            strpos($tagKey, 'order_billing_') === 0 ||
            strpos($tagKey, 'order_shipping_') === 0) {
            return 'order';
        }

        if (strpos($tagKey, 'product_meta_') === 0) {
            return 'product';
        }

        return '';
    }

    /**
     * Get human-readable display name for a category.
     *
     * @param string $category The category key.
     * @return string The display name.
     * @since 2.0.0
     */
    private static function getCategoryDisplayName(string $category): string
    {
        $categoryNames = [
            'users' => __('User Tags', 'notifal'),
            'products' => __('Product Tags', 'notifal'),
            'orders' => __('Order Tags', 'notifal'),
            'posts' => __('Post Tags', 'notifal'),
            'pages' => __('Page Tags', 'notifal'),
            // 'comments' => __('Comment Tags', 'notifal'), // Moved to Notifal Pro
            'general' => __('General Tags', 'notifal'),
        ];

        if (isset($categoryNames[$category])) {
            return $categoryNames[$category];
        }
        
        // For custom post types, create a more user-friendly display name
        $displayName = ucwords(str_replace(['_', '-'], ' ', $category));
        /* translators: %s: category or post type display name (e.g. Product, Page, Custom Post Type) */
        return sprintf(__('%s Tags', 'notifal'), $displayName);
    }
}
