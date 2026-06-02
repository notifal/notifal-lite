<?php

namespace Notifal\Domain\Tags;

use Notifal\Domain\Products\ProductFetcherInterface;
use Notifal\Domain\Tags\Enums\TagCategory;
use Notifal\Domain\Tags\Services\DateFormatterService;
use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Domain\Tags\TagManager;
use Notifal\Infrastructure\WordPress\Support\PluginDetector;

defined('ABSPATH') || exit;

/**
 * Class RegisterTags
 *
 * Registers all default tags provided by Notifal.
 *
 * @package Notifal\Domain\Tags
 * @author Hossein <hossein@notifal.com>
 * @since 2.0.0
 */
class RegisterTags
{
    /**
     * Register all default tags to the TagManager.
     *
     * @param TagManager $manager
     * @param DateFormatterService $dateFormatterService
     * @since 2.0.0
     */
    public static function register(TagManager $manager, DateFormatterService $dateFormatterService): void
    {
        // -----------------------
        // User Tags
        // -----------------------


        // Static User Fields
        $manager->registerTag(new Tag(
            'user_id',
            __('User ID', 'notifal'),
            function ($context) {
                return isset($context['user']) ? $context['user']->getId() : '';
            },
            TagCategory::USERS,
            __('Displays the unique identifier of the user.', 'notifal')
        ));

        $manager->registerTag(new Tag(
            'user_first_name',
            __('User First Name', 'notifal'),
            function ($context) {
                return isset($context['user']) ? $context['user']->getFirstName() : '';
            },
            TagCategory::USERS,
            __('Displays the first name of the user.', 'notifal')
        ));

        $manager->registerTag(new Tag(
            'user_last_name',
            __('User Last Name', 'notifal'),
            function ($context) {
                return isset($context['user']) ? $context['user']->getLastName() : '';
            },
            TagCategory::USERS,
            __('Displays the last name of the user.', 'notifal')
        ));

        $manager->registerTag(new Tag(
            'user_email',
            __('User Email', 'notifal'),
            function ($context) {
                return isset($context['user']) ? $context['user']->getEmail() : '';
            },
            TagCategory::USERS,
            __('Displays the email address of the user.', 'notifal')
        ));

        $manager->registerTag(new Tag(
            'user_username',
            __('User Username', 'notifal'),
            function ($context) {
                return isset($context['user']) ? $context['user']->getUsername() : '';
            },
            TagCategory::USERS,
            __('Displays the username (login name) of the user.', 'notifal')
        ));

        $manager->registerTag(new Tag(
            'user_url',
            __('User Website', 'notifal'),
            function ($context) {
                return isset($context['user']) ? $context['user']->getUrl() : '';
            },
            TagCategory::USERS,
            __('Displays the website URL of the user.', 'notifal')
        ));

        $manager->registerTag(new Tag(
            'user_nicename',
            __('User Nicename', 'notifal'),
            function ($context) {
                return isset($context['user']) ? $context['user']->getNicename() : '';
            },
            TagCategory::USERS,
            __('Displays the nicename (slug) of the user.', 'notifal')
        ));

        $manager->registerTag(new Tag(
            'user_registered_date_{key}',
            __('User Registration Date', 'notifal'),
            function ($context, $tagKey) use ($dateFormatterService) {
                if (!isset($context['user'])) {
                    return '';
                }
                $registered = $context['user']->getRegistered();

                if ($registered) {
                    return $dateFormatterService->formatDate($registered, $tagKey);
                }

                return '';
            },
            TagCategory::USERS,
            __('Displays the user registration date. Supports custom format: {user_registered_date_Y/m/d} or relative: {user_registered_date_diff}', 'notifal')
        ));

        $manager->registerTag(new Tag(
            'user_display_name',
            __('User Display Name', 'notifal'),
            function ($context) {
                return isset($context['user']) ? $context['user']->getDisplayName() : '';
            },
            TagCategory::USERS,
            __('Displays the display name of the user.', 'notifal')
        ));

        // Dynamic User Meta Fields
        $manager->registerTag(new Tag(
            'user_meta_{key}',
            __('User Meta Field', 'notifal'),
            function ($context, $tagKey) {
                preg_match('/user_meta_(.+)/', $tagKey, $matches);
                $metaKey = $matches[1] ?? '';

                if (isset($context['user'])) {
                    $value = $context['user']->getMeta($metaKey);
                    
                    // Use preview fallback if value is empty (for better preview experience)
                    if (empty($value) && self::isPreviewMode($context)) {
                        return self::getUserMetaFallback($metaKey);
                    }
                    
                    return $value;
                }

                return '';
            },
            TagCategory::USERS,
            __('Displays a custom meta field from the user.', 'notifal')
        ));

        // -----------------------
        // Post Tags
        // -----------------------

        $manager->registerTag(new Tag(
            'post_id',
            __('Post ID', 'notifal'),
            function ($context) {
                return isset($context['post']) ? $context['post']->ID : get_the_ID();
            },
            TagCategory::POSTS,
            __('Displays the unique identifier of the post.', 'notifal')
        ));

        $manager->registerTag(new Tag(
            'post_title',
            __('Post Title', 'notifal'),
            function ($context) {
                return isset($context['post']) ? $context['post']->post_title : get_the_title();
            },
            TagCategory::POSTS,
            __('Displays the title of the post.', 'notifal')
        ));

        $manager->registerTag(new Tag(
            'post_content',
            __('Post Content', 'notifal'),
            function ($context) {
                $content = isset($context['post']) ? $context['post']->post_content : get_the_content();
                return wp_strip_all_tags($content);
            },
            TagCategory::POSTS,
            __('Displays the content of the post.', 'notifal')
        ));

        $manager->registerTag(new Tag(
            'post_excerpt',
            __('Post Excerpt', 'notifal'),
            function ($context) {
                return isset($context['post']) ? $context['post']->post_excerpt : get_the_excerpt();
            },
            TagCategory::POSTS,
            __('Displays the excerpt of the post.', 'notifal')
        ));

        $manager->registerTag(new Tag(
            'post_author',
            __('Post Author', 'notifal'),
            function ($context) {
                $authorId = isset($context['post']) ? $context['post']->post_author : get_the_author_meta('ID');
                return get_the_author_meta('display_name', $authorId);
            },
            TagCategory::POSTS,
            __('Displays the author name of the post.', 'notifal')
        ));

        $manager->registerTag(new Tag(
            'post_published_date_{key}',
            __('Post Published Date', 'notifal'),
            function ($context, $tagKey) use ($dateFormatterService) {
                $date = isset($context['post']) ? $context['post']->post_date : get_the_date('Y-m-d H:i:s');

                if ($date) {
                    return $dateFormatterService->formatDate($date, $tagKey);
                }

                return '';
            },
            TagCategory::POSTS,
            __('Displays the publication date of the post. Supports custom format: {post_published_date_Y/m/d} or relative: {post_published_date_diff}', 'notifal')
        ));

        $manager->registerTag(new Tag(
            'post_modified_date_{key}',
            __('Post Modified Date', 'notifal'),
            function ($context, $tagKey) use ($dateFormatterService) {
                $date = isset($context['post']) ? $context['post']->post_modified : get_the_modified_date('Y-m-d H:i:s');

                if ($date) {
                    return $dateFormatterService->formatDate($date, $tagKey);
                }

                return '';
            },
            TagCategory::POSTS,
            __('Displays the last modified date of the post. Supports custom format: {post_modified_date_Y/m/d} or relative: {post_modified_date_diff}', 'notifal')
        ));

        $manager->registerTag(new Tag(
            'post_created_date_{key}',
            __('Post Created Date', 'notifal'),
            function ($context, $tagKey) use ($dateFormatterService) {
                $date = isset($context['post']) ? $context['post']->post_date : get_the_date('Y-m-d H:i:s');

                if ($date) {
                    return $dateFormatterService->formatDate($date, $tagKey);
                }

                return '';
            },
            TagCategory::POSTS,
            __('Displays the created date of the post (same as publish date). Supports custom format: {post_created_date_Y/m/d} or relative: {post_created_date_diff}', 'notifal')
        ));

        $manager->registerTag(new Tag(
            'post_url',
            __('Post URL', 'notifal'),
            function ($context) {
                $postId = isset($context['post']) ? $context['post']->ID : get_the_ID();
                return get_permalink($postId);
            },
            TagCategory::POSTS,
            __('Displays the URL of the post.', 'notifal')
        ));

        // Dynamic Post Meta Fields
        $manager->registerTag(new Tag(
            'post_meta_{key}',
            __('Post Meta Field', 'notifal'),
            function ($context, $tagKey) {
                preg_match('/post_meta_(.+)/', $tagKey, $matches);
                $metaKey = $matches[1] ?? '';

                if (isset($context['post'])) {
                    $value = get_post_meta($context['post']->ID, $metaKey, true);

                    // Use preview fallback if value is empty (for better preview experience)
                    if (empty($value) && self::isPreviewMode($context)) {
                        return self::getPostMetaFallback($metaKey);
                    }

                    return $value;
                }

                return '';
            },
            TagCategory::POSTS,
            __('Displays a custom meta field from the post.', 'notifal')
        ));

        // -----------------------
        // Page Tags
        // -----------------------

        $manager->registerTag(new Tag(
            'page_id',
            __('Page ID', 'notifal'),
            function ($context) {
                return isset($context['page']) ? $context['page']->ID : get_the_ID();
            },
            TagCategory::PAGES,
            __('Displays the unique identifier of the page.', 'notifal')
        ));

        $manager->registerTag(new Tag(
            'page_title',
            __('Page Title', 'notifal'),
            function ($context) {
                return isset($context['page']) ? $context['page']->post_title : get_the_title();
            },
            TagCategory::PAGES,
            __('Displays the title of the page.', 'notifal')
        ));

        $manager->registerTag(new Tag(
            'page_content',
            __('Page Content', 'notifal'),
            function ($context) {
                $content = isset($context['page']) ? $context['page']->post_content : get_the_content();
                return wp_strip_all_tags($content);
            },
            TagCategory::PAGES,
            __('Displays the content of the page.', 'notifal')
        ));

        $manager->registerTag(new Tag(
            'page_url',
            __('Page URL', 'notifal'),
            function ($context) {
                $pageId = isset($context['page']) ? $context['page']->ID : get_the_ID();
                return get_permalink($pageId);
            },
            TagCategory::PAGES,
            __('Displays the URL of the page.', 'notifal')
        ));

        $manager->registerTag(new Tag(
            'page_published_date_{key}',
            __('Page Published Date', 'notifal'),
            function ($context, $tagKey) use ($dateFormatterService) {
                $date = isset($context['page']) ? $context['page']->post_date : get_the_date('Y-m-d H:i:s');

                if ($date) {
                    return $dateFormatterService->formatDate($date, $tagKey);
                }

                return '';
            },
            TagCategory::PAGES,
            __('Displays the publication date of the page. Supports custom format: {page_published_date_Y/m/d} or relative: {page_published_date_diff}', 'notifal')
        ));

        $manager->registerTag(new Tag(
            'page_modified_date_{key}',
            __('Page Modified Date', 'notifal'),
            function ($context, $tagKey) use ($dateFormatterService) {
                $date = isset($context['page']) ? $context['page']->post_modified : get_the_modified_date('Y-m-d H:i:s');

                if ($date) {
                    return $dateFormatterService->formatDate($date, $tagKey);
                }

                return '';
            },
            TagCategory::PAGES,
            __('Displays the last modified date of the page. Supports custom format: {page_modified_date_Y/m/d} or relative: {page_modified_date_diff}', 'notifal')
        ));

        $manager->registerTag(new Tag(
            'page_parent_title',
            __('Page Parent Title', 'notifal'),
            function ($context) {
                $pageId = isset($context['page']) ? $context['page']->ID : get_the_ID();
                $parentId = wp_get_post_parent_id($pageId);
                return $parentId ? get_the_title($parentId) : '';
            },
            TagCategory::PAGES,
            __('Displays the title of the parent page.', 'notifal')
        ));

        // Dynamic Page Meta Fields
        $manager->registerTag(new Tag(
            'page_meta_{key}',
            __('Page Meta Field', 'notifal'),
            function ($context, $tagKey) {
                preg_match('/page_meta_(.+)/', $tagKey, $matches);
                $metaKey = $matches[1] ?? '';

                if (isset($context['page'])) {
                    $value = get_post_meta($context['page']->ID, $metaKey, true);

                    // Use preview fallback if value is empty (for better preview experience)
                    if (empty($value) && self::isPreviewMode($context)) {
                        return self::getPageMetaFallback($metaKey);
                    }

                    return $value;
                }

                return '';
            },
            TagCategory::PAGES,
            __('Displays a custom meta field from the page.', 'notifal')
        ));

        // -----------------------
        // Comment Tags 
        // -----------------------
        
        
        // Apply filter to allow Pro plugin to register comment tags
        do_action('notifal_register_comment_tags', $manager, $dateFormatterService);


        // -----------------------
        // Order Tags
        // -----------------------
        // Static Order Fields
        $manager->registerTag(new Tag(
            'order_id',
            __('Order ID', 'notifal'),
            function ($context) {
                return isset($context['order']) ? $context['order']->getId() : '';
            },
            TagCategory::ORDERS,
            __('Displays the unique identifier of the order.', 'notifal')
        ));

        //Dynamic Meta Fields
        $manager->registerTag(new Tag(
            'order_meta_{key}',
            __('Order Meta Field', 'notifal'),
            function ($context, $tagKey) {
                preg_match('/order_meta_(.+)/', $tagKey, $matches);
                $metaKey = $matches[1] ?? '';

                if (isset($context['order'])) {
                    $value = $context['order']->getMeta($metaKey);
                    
                    // Use preview fallback if value is empty (for better preview experience)
                    if (empty($value) && self::isPreviewMode($context)) {
                        return self::getOrderMetaFallback($metaKey);
                    }
                    
                    return $value;
                }

                return '';
            },
            TagCategory::ORDERS,
            __('Displays any custom meta field from the order, including billing and shipping fields.', 'notifal')
        ));


        // -----------------------
        // Product Tags
        // -----------------------

        // Static Product Fields
        $manager->registerTag(new Tag(
            'product_id',
            __('Product ID', 'notifal'),
            function ($context) {
                // Priority 1: For order-based contexts with explicit selected item
                if (isset($context['selected_order_item'])) {
                    return $context['selected_order_item']->getProductId();
                }

                // Priority 2: Use product from context (respects filters)
                if (isset($context['product'])) {
                    return $context['product']->getId();
                }

                // Priority 3: Fallback to order items if no product context
                if (isset($context['order'])) {
                    $items = $context['order']->getItems();
                    if (!empty($items)) {
                        // Use deterministic selection based on order ID to ensure consistency
                        $order = $context['order'];
                        $orderIdSeed = $order->getId() % count($items);
                        $selectedItem = array_values($items)[$orderIdSeed];
                        return $selectedItem->getProductId();
                    }
                }

                return '';
            },
            TagCategory::PRODUCTS,
            __('Displays the unique ID of the product.', 'notifal')
        ));

        $manager->registerTag(new Tag(
            'product_name',
            __('Product Name', 'notifal'),
            function ($context) {
                // Priority 1: For order-based contexts with explicit selected item (from order primary context)
                if (isset($context['selected_order_item'])) {
                    return $context['selected_order_item']->getName();
                }

                // Priority 2: Use product from context (respects filters)
                // This handles user+product, post+product, etc. scenarios
                if (isset($context['product'])) {
                    return $context['product']->getName();
                }

                // Priority 3: Fallback to order items if no product context
                // This is for pure order templates without product tags
                if (isset($context['order'])) {
                    $order = $context['order'];
                    $items = $order->getItems();
                    if (!empty($items)) {
                        // Use deterministic selection based on order ID to ensure consistency
                        $orderIdSeed = $order->getId() % count($items);
                        $selectedItem = array_values($items)[$orderIdSeed];
                        return $selectedItem->getName();
                    }
                }

                // Fallback for templates without proper context building
                $templateContent = $context['template_content'] ?? '';

                // Context-aware product selection (only if no product provided)
                if (self::hasOrderContext($templateContent)) {
                    // Get product from order items
                    $productName = self::getProductFromOrderContext($context);
                    if ($productName) {
                        return $productName;
                    }
                }

                if (self::hasSaleContext($templateContent)) {
                    // Get random sale product
                    $productName = self::getRandomSaleProductName();
                    if ($productName) {
                        return $productName;
                    }
                }
                return '';
            },
            TagCategory::PRODUCTS,
            __('Displays the name of the product.', 'notifal')
        ));

        $manager->registerTag(new Tag(
            'product_link',
            __('Product Link', 'notifal'),
            function ($context) {
                return isset($context['product']) && $context['product']->getLink()
                    ? $context['product']->getLink()
                    : '';
            },
            TagCategory::PRODUCTS,
            __('Displays the permalink of the product.', 'notifal')
        ));

        $manager->registerTag(new Tag(
            'product_discount_amount',
            __('Product Discount Amount', 'notifal'),
            function ($context) {
                $regular = isset($context['product']) ? $context['product']->getRegularPrice() : null;
                $sale    = isset($context['product']) ? $context['product']->getSalePrice() : null;
                if ($regular && $sale) {
                    $regularFloat = (float) wc_clean(str_replace(get_woocommerce_currency_symbol(), '', $regular));
                    $saleFloat    = (float) wc_clean(str_replace(get_woocommerce_currency_symbol(), '', $sale));
                    $discount = $regularFloat - $saleFloat;
                    $result = $discount > 0 ? wc_price($discount) : '';
                } else {
                    $result = '';
                }

                // Use preview fallback if result is empty and we're in preview mode
                if (empty($result) && self::isPreviewMode($context)) {
                    return wc_price(0); // $0.00 discount as fallback
                }

                return $result;
            },
            TagCategory::PRODUCTS,
            __('Displays the discount amount of the product.', 'notifal')
        ));

        $manager->registerTag(new Tag(
            'product_discount_percent',
            __('Product Discount Percent', 'notifal'),
            function ($context) {
                $regular = isset($context['product']) ? $context['product']->getRegularPrice() : null;
                $sale    = isset($context['product']) ? $context['product']->getSalePrice() : null;
                $result = ($regular && $sale)
                    ? round((($regular - $sale) / $regular) * 100) . '%'
                    : '';

                // Use preview fallback if result is empty and we're in preview mode
                if (empty($result) && self::isPreviewMode($context)) {
                    return '0%'; // 0% discount as fallback
                }

                return $result;
            },
            TagCategory::PRODUCTS,
            __('Displays the discount percentage of the product.', 'notifal')
        ));

        // dynamic product meta
        $manager->registerTag(new Tag(
            'product_meta_{key}',
            __('Product Meta Field', 'notifal'),
            function ($context, $tagKey) {
                preg_match('/product_meta_(.+)/', $tagKey, $matches);
                $metaKey = $matches[1] ?? '';

                if (isset($context['product'])) {
                    $value = $context['product']->getMeta($metaKey);
                    
                    // Enhanced preview fallback logic
                    if (empty($value) && self::isPreviewMode($context)) {
                        return self::getProductMetaFallback($metaKey, $context['product']);
                    }
                    
                    // Format price fields using WooCommerce formatting
                    return self::formatProductMetaValue($metaKey, $value);
                }

                return '';
            },
            TagCategory::PRODUCTS,
            __('Displays a custom meta field from the product.', 'notifal')
        ));

        // -----------------------
        // Product Date Tags
        // -----------------------

        $manager->registerTag(new Tag(
            'product_publish_date_{key}',
            __('Product Publish Date', 'notifal'),
            function ($context, $tagKey) use ($dateFormatterService) {
                if (isset($context['product'])) {
                    $publishDate = $context['product']->getPublishDate();
                    if ($publishDate) {
                        return $dateFormatterService->formatDate($publishDate, $tagKey);
                    }
                }

                // Use preview fallback if no date and we're in preview mode
                if (self::isPreviewMode($context)) {
                    $fallbackDate = strtotime('-5 days'); // 5 days ago
                    return $dateFormatterService->formatDate($fallbackDate, $tagKey);
                }

                return '';
            },
            TagCategory::PRODUCTS,
            __('Displays the product publish date. Supports custom format: {product_publish_date_Y/m/d} or relative: {product_publish_date_diff}', 'notifal')
        ));

        $manager->registerTag(new Tag(
            'product_modified_date_{key}',
            __('Product Modified Date', 'notifal'),
            function ($context, $tagKey) use ($dateFormatterService) {
                if (isset($context['product'])) {
                    $modifiedDate = $context['product']->getModifiedDate();
                    if ($modifiedDate) {
                        return $dateFormatterService->formatDate($modifiedDate, $tagKey);
                    }
                }

                // Use preview fallback if no date and we're in preview mode
                if (self::isPreviewMode($context)) {
                    $fallbackDate = strtotime('-1 day'); // 1 day ago
                    return $dateFormatterService->formatDate($fallbackDate, $tagKey);
                }

                return '';
            },
            TagCategory::PRODUCTS,
            __('Displays the product modified date. Supports custom format: {product_modified_date_Y/m/d} or relative: {product_modified_date_diff}', 'notifal')
        ));

        $manager->registerTag(new Tag(
            'product_created_date_{key}',
            __('Product Created Date', 'notifal'),
            function ($context, $tagKey) use ($dateFormatterService) {
                if (isset($context['product'])) {
                    $createdDate = $context['product']->getCreatedDate();
                    if ($createdDate) {
                        return $dateFormatterService->formatDate($createdDate, $tagKey);
                    }
                }

                // Use preview fallback if no date and we're in preview mode
                if (self::isPreviewMode($context)) {
                    $fallbackDate = strtotime('-5 days'); // Same as publish date
                    return $dateFormatterService->formatDate($fallbackDate, $tagKey);
                }

                return '';
            },
            TagCategory::PRODUCTS,
            __('Displays the product created date (same as publish date). Supports custom format: {product_created_date_Y/m/d} or relative: {product_created_date_diff}', 'notifal')
        ));

        // -----------------------
        // Order Date Tags
        // -----------------------

        $manager->registerTag(new Tag(
            'order_created_date_{key}',
            __('Order Created Date', 'notifal'),
            function ($context, $tagKey) use ($dateFormatterService) {
                if (isset($context['order'])) {
                    $createdDate = $context['order']->getCreatedDate();
                    if ($createdDate) {
                        return $dateFormatterService->formatDate($createdDate, $tagKey);
                    }
                }
                return '';
            },
            TagCategory::ORDERS,
            __('Displays the order created date. Supports custom format: {order_created_date_Y/m/d} or relative: {order_created_date_diff}', 'notifal')
        ));

        $manager->registerTag(new Tag(
            'order_completed_date_{key}',
            __('Order Completed Date', 'notifal'),
            function ($context, $tagKey) use ($dateFormatterService) {
                if (isset($context['order'])) {
                    $completedDate = $context['order']->getCompletedDate();
                    if ($completedDate) {
                        return $dateFormatterService->formatDate($completedDate, $tagKey);
                    }
                }
                return '';
            },
            TagCategory::ORDERS,
            __('Displays the order completed date. Supports custom format: {order_completed_date_Y/m/d} or relative: {order_completed_date_diff}', 'notifal')
        ));

        $manager->registerTag(new Tag(
            'order_paid_date_{key}',
            __('Order Paid Date', 'notifal'),
            function ($context, $tagKey) use ($dateFormatterService) {
                if (isset($context['order'])) {
                    $paidDate = $context['order']->getPaidDate();
                    if ($paidDate) {
                        return $dateFormatterService->formatDate($paidDate, $tagKey);
                    }
                }
                return '';
            },
            TagCategory::ORDERS,
            __('Displays the order paid date. Supports custom format: {order_paid_date_Y/m/d} or relative: {order_paid_date_diff}', 'notifal')
        ));

        $manager->registerTag(new Tag(
            'order_modified_date_{key}',
            __('Order Modified Date', 'notifal'),
            function ($context, $tagKey) use ($dateFormatterService) {
                if (isset($context['order'])) {
                    $modifiedDate = $context['order']->getModifiedDate();
                    if ($modifiedDate) {
                        return $dateFormatterService->formatDate($modifiedDate, $tagKey);
                    }
                }
                return '';
            },
            TagCategory::ORDERS,
            __('Displays the order modified date. Supports custom format: {order_modified_date_Y/m/d} or relative: {order_modified_date_diff}', 'notifal')
        ));

        $manager->registerTag(new Tag(
            'order_shipped_date_{key}',
            __('Order Shipped Date', 'notifal'),
            function ($context, $tagKey) use ($dateFormatterService) {
                if (isset($context['order'])) {
                    $shippedDate = $context['order']->getShippedDate();
                    if ($shippedDate) {
                        return $dateFormatterService->formatDate($shippedDate, $tagKey);
                    }
                }
                return '';
            },
            TagCategory::ORDERS,
            __('Displays the order shipped date (if available). Supports custom format: {order_shipped_date_Y/m/d} or relative: {order_shipped_date_diff}', 'notifal')
        ));

        // -----------------------
        // WooCommerce Cart Tags
        // -----------------------
        if (PluginDetector::isWooCommerceActive()) {
            RegisterWooCommerceCartTags::register($manager);
        }

        /**
         * Action: Allow developers to register custom tags.
         *
         * @param TagManager $manager The TagManager instance.
         * @since 2.0.0
         */
        do_action(ActionHooks::TAG_REGISTER, $manager);
    }

    /**
     * Check if template content contains order-related context.
     *
     * @param string $content Template content to analyze.
     * @return bool True if content has order context.
     * @author Hossein <hossein@notifal.com>
     * @since 2.0.0
     */
    private static function hasOrderContext(string $content): bool
    {
        return preg_match('/\{(order_id|order_meta_|order_billing_|order_shipping_)/i', $content) > 0;
    }

    /**
     * Check if template content contains sale-related context.
     *
     * @param string $content Template content to analyze.
     * @return bool True if content has sale context.
     * @author Hossein <hossein@notifal.com>
     * @since 2.0.0
     */
    private static function hasSaleContext(string $content): bool
    {
        return preg_match('/\{(product_meta_sale_price|product_meta_regular_price|product_discount_)/i', $content) > 0;
    }

    /**
     * Get a product from the order's items using context-aware selection.
     *
     * Uses deterministic selection for consistency, except when filtering by specific products
     * where random selection from filtered products provides better user experience.
     *
     * @param array $context Context containing order object.
     * @return string Product name from order or empty string.
     * @author Hossein <hossein@notifal.com>
     * @since 2.0.0
     */
    private static function getProductFromOrderContext(array $context): string
    {
        if (!isset($context['order'])) {
            return '';
        }

        $order = $context['order'];
        $items = $order->getItems();
        
        if (empty($items)) {
            return '';
        }

        // Check if we're in a "products filter" context by looking at order metadata
        // or by checking if items are already filtered to specific products
        $isProductsFilter = isset($context['filtered_by_products']) && $context['filtered_by_products'] === true;
        
        if ($isProductsFilter) {
            // For products filter, use random selection from filtered products for variety
            $randomItem = $items[array_rand($items)];
            return $randomItem->getName();
        } else {
            // For other contexts, use deterministic selection based on order ID to ensure consistency
            // This ensures the same product is selected as in the Product Image Widget
            $orderIdSeed = $order->getId() % count($items);
            $selectedItem = array_values($items)[$orderIdSeed];
            return $selectedItem->getName();
        }
    }

    /**
     * Get a random product that is on sale.
     *
     * @return string Product name from sale products or empty string.
     * @author Hossein <hossein@notifal.com>
     * @since 2.0.0
     */
    private static function getRandomSaleProductName(): string
    {
        if (!function_exists('notifal_app')) {
            return '';
        }

        try {
            /** @var ProductFetcherInterface $fetcher */
            $fetcher = notifal_app(ProductFetcherInterface::class);
            $dto = $fetcher->getRandom(['on_sale' => true]);

            return $dto ? $dto->getName() : '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Check if the current request is in preview mode.
     *
     * @param array $context The context array.
     * @return bool True if in preview mode, false otherwise.
     * @author Hossein <hossein@notifal.com>
     * @since 2.0.0
     */
    private static function isPreviewMode(array $context): bool
    {
        return isset($context['is_preview']) && $context['is_preview'] === true;
    }

    /**
     * Get a fallback value for product meta fields in preview mode.
     *
     * @param string $metaKey The meta key to get fallback for.
     * @param mixed $productDTO The ProductDTO object from context.
     * @return string The fallback value.
     * @author Hossein <hossein@notifal.com>
     * @since 2.0.0
     */
    private static function getProductMetaFallback(string $metaKey, $productDTO = null): string
    {
        // Try to get the regular price from the ProductDTO
        $regularPrice = null;
        if ($productDTO && method_exists($productDTO, 'getRegularPrice')) {
            $regularPrice = $productDTO->getRegularPrice();
        }
        
        // If no regular price available, use default fallback
        $defaultRegularPrice = $regularPrice ?: 99.99;
        
        // WooCommerce standard product meta keys only.
        $fallbacks = [
            '_sale_price'    => wc_price($defaultRegularPrice),
            '_regular_price' => wc_price($defaultRegularPrice),
            '_price'         => wc_price($defaultRegularPrice),
            '_sku'           => 'PREVIEW-SKU-123',
            '_weight'        => '1.5',
            '_length'        => '10',
            '_width'         => '5',
            '_height'        => '3',
            '_stock'         => '25',
            '_stock_status'  => 'instock',
            '_sale_price_dates_from' => date('Y-m-d H:i:s', strtotime('-2 days')),
            '_sale_price_dates_to'   => date('Y-m-d H:i:s', strtotime('+5 days')),
        ];
        
        return $fallbacks[$metaKey] ?? __('Preview Value', 'notifal');
    }

    /**
     * Get a fallback value for user meta fields in preview mode.
     *
     * @param string $metaKey The meta key.
     * @return string The fallback value.
     * @author Hossein <hossein@notifal.com>
     * @since 2.0.0
     */
    private static function getUserMetaFallback(string $metaKey): string
    {
        // WordPress core user profile meta only.
        $fallbacks = [
            'first_name' => __('John', 'notifal'),
            'last_name' => __('Doe', 'notifal'),
            'nickname' => __('Johnny', 'notifal'),
            'description' => __('A sample user description for preview purposes.', 'notifal'),
        ];

        return $fallbacks[$metaKey] ?? __('Preview Value', 'notifal');
    }

    /**
     * Get a fallback value for order meta fields in preview mode.
     *
     * @param string $metaKey The meta key.
     * @return string The fallback value.
     * @author Hossein <hossein@notifal.com>
     * @since 2.0.0
     */
    private static function getOrderMetaFallback(string $metaKey): string
    {
        switch ($metaKey) {
            case 'billing_first_name':
                return __('First Name', 'notifal');
            case 'billing_last_name':
                return __('Last Name', 'notifal');
            case 'billing_company':
                return __('Company', 'notifal');
            case 'billing_email':
                return __('Email', 'notifal');
            case 'billing_phone':
                return __('Phone', 'notifal');
            case 'billing_country':
                return __('Country', 'notifal');
            case 'billing_address_1':
                return __('Address 1', 'notifal');
            case 'billing_address_2':
                return __('Address 2', 'notifal');
            case 'billing_city':
                return __('City', 'notifal');
            case 'billing_state':
                return __('State', 'notifal');
            case 'billing_postcode':
                return __('Postcode', 'notifal');
            case 'shipping_first_name':
                return __('First Name', 'notifal');
            case 'shipping_last_name':
                return __('Last Name', 'notifal');
            case 'shipping_company':
                return __('Company', 'notifal');
            case 'shipping_email':
                return __('Email', 'notifal');
            case 'shipping_phone':
                return __('Phone', 'notifal');
            case 'shipping_country':
                return __('Country', 'notifal');
            case 'shipping_address_1':
                return __('Address 1', 'notifal');
            case 'shipping_address_2':
                return __('Address 2', 'notifal');
            case 'shipping_city':
                return __('City', 'notifal');
            case 'shipping_state':
                return __('State', 'notifal');
            case 'shipping_postcode':
                return __('Postcode', 'notifal');
            
            // Date fields
            case '_date_completed':
            case 'date_completed':
                return strtotime('-2 days'); // 2 days ago timestamp
            case '_date_paid':
            case 'date_paid':
                return strtotime('-3 days'); // 3 days ago timestamp
            case '_date_shipped':
            case 'date_shipped':
            case '_shipping_date':
            case 'shipping_date':
                return strtotime('-1 day'); // 1 day ago timestamp
                
            default:
                return 'N/A';
        }
    }

    /**
     * Get a fallback value for post meta fields in preview mode.
     *
     * @param string $metaKey The meta key.
     * @return string The fallback value.
     * @author Hossein <hossein@notifal.com>
     * @since 2.0.0
     */
    private static function getPostMetaFallback(string $metaKey): string
    {
        // WordPress core post meta used in content/display (featured image).
        $fallbacks = [
            '_thumbnail_id' => '123',
        ];

        return $fallbacks[$metaKey] ?? __('Preview Value', 'notifal');
    }

    /**
     * Get a fallback value for page meta fields in preview mode.
     *
     * @param string $metaKey The meta key.
     * @return string The fallback value.
     * @author Hossein <hossein@notifal.com>
     * @since 2.0.0
     */
    private static function getPageMetaFallback(string $metaKey): string
    {
        // WordPress core page meta used in content/display.
        $fallbacks = [
            '_thumbnail_id' => '456',
            '_wp_page_template' => 'page.php',
        ];

        return $fallbacks[$metaKey] ?? __('Preview Value', 'notifal');
    }

    /**
     * Formats a product meta value based on its key.
     *
     * @param string $metaKey The meta key.
     * @param mixed $value The raw meta value.
     * @return string The formatted value.
     * @author Hossein <hossein@notifal.com>
     * @since 2.0.0
     */
    private static function formatProductMetaValue(string $metaKey, $value): string
    {
        // Price fields that should be formatted as currency
        $priceFields = [
            '_sale_price',
            '_regular_price',
            '_price'
        ];
        
        if (in_array($metaKey, $priceFields, true) && is_numeric($value) && $value > 0) {
            return wc_price((float) $value);
        }
        
        return (string) $value;
    }
}
