<?php

namespace Notifal\Domain\Tags;

use Notifal\Domain\Tags\Enums\TagCategory;
use Notifal\Domain\Tags\Services\ContentSourceCountResolver;

defined('ABSPATH') || exit;

/**
 * Registers content-source counter tags for notifications and templates.
 *
 * @package Notifal\Domain\Tags
 * @since 2.3.7
 * @author Hossein <hossein@notifal.com>
 */
class RegisterContentSourceCounterTags
{
    /**
     * Register all content-source counter tags.
     *
     * @param TagManager $manager Tag manager instance.
     * @return void
     * @since 2.3.7
     */
    public static function register(TagManager $manager): void
    {
        // Total orders matching content source order restrictions.
        $manager->registerTag(new Tag(
            'order_counter',
            __('Order Counter', 'notifal'),
            function ($context) {
                return ContentSourceCountResolver::resolveOrder($context);
            },
            TagCategory::ORDERS,
            __('Displays the total number of orders matching the notification content source order restrictions (all orders, date range, status, products, custom meta, etc.).', 'notifal')
        ));

        // Total products matching content source product restrictions.
        $manager->registerTag(new Tag(
            'product_counter',
            __('Product Counter', 'notifal'),
            function ($context) {
                return ContentSourceCountResolver::resolveProduct($context);
            },
            TagCategory::PRODUCTS,
            __('Displays the total number of products matching the notification content source product restrictions (all products, categories, sale, featured, date range, etc.).', 'notifal')
        ));

        // Total posts matching content source post restrictions.
        $manager->registerTag(new Tag(
            'post_counter',
            __('Post Counter', 'notifal'),
            function ($context) {
                return ContentSourceCountResolver::resolvePost($context);
            },
            TagCategory::POSTS,
            __('Displays the total number of posts matching the notification content source post restrictions (all posts, categories, tags, author, date range, etc.).', 'notifal')
        ));

        // Total pages matching content source page restrictions.
        $manager->registerTag(new Tag(
            'page_counter',
            __('Page Counter', 'notifal'),
            function ($context) {
                return ContentSourceCountResolver::resolvePage($context);
            },
            TagCategory::PAGES,
            __('Displays the total number of pages matching the notification content source page restrictions (all pages, templates, author, date range, etc.).', 'notifal')
        ));

        // Total comments matching content source comment restrictions (Notifal Pro).
        $manager->registerTag(new Tag(
            'comment_counter',
            __('Comment Counter', 'notifal'),
            function ($context) {
                return ContentSourceCountResolver::resolveComment($context);
            },
            TagCategory::COMMENTS,
            __('Displays the total number of comments matching the notification content source comment restrictions. Requires Notifal Pro.', 'notifal')
        ));

        // Dynamic custom post type counter per registered post type slug.
        $manager->registerTag(new Tag(
            'custom_posttype_counter_{key}',
            __('Custom Post Type Counter', 'notifal'),
            function ($context, $tagKey) {
                preg_match('/custom_posttype_counter_(.+)/', $tagKey, $matches);
                $postType = $matches[1] ?? '';

                return ContentSourceCountResolver::resolveCustomPostType($context, $postType);
            },
            TagCategory::GENERAL,
            __('Displays the total number of items for a custom post type matching content source restrictions. Example: {custom_posttype_counter_book}', 'notifal')
        ));
    }
}
