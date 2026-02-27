<?php

namespace Notifal\Modules\Templates\Application\Services;

use Notifal\Domain\Tags\Services\TagDetector;
use Notifal\Infrastructure\WordPress\Support\PluginDetector;

defined('ABSPATH') || exit;

/**
 * Resolves "auto" preview image source based on tags used in template content.
 *
 * When Preview Image Source is set to Auto, the effective source is determined
 * by which tags appear in the notification template: product/order → product image,
 * post → post featured image, page → page featured image, comment → product image
 * (if WooCommerce) or post featured image.
 *
 * @package Notifal\Modules\Templates\Application\Services
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class FeaturedImageAutoSourceResolver
{
    /**
     * Resolve effective image source from template content when source is "auto".
     *
     * Priority order based on used tags:
     * 1. Order/product tags → 'product'
     * 2. Post tags → 'post'
     * 3. Page tags → 'page'
     * 4. Comment tags → 'product' (if WooCommerce active) else 'post'
     * 5. No matching tags → 'post' (default)
     *
     * @param string $templateContent Raw template content (e.g. post_content with blocks or HTML).
     * @return string Effective source: 'product', 'post', or 'page'.
     * @since 2.0.0
     */
    public static function resolve(string $templateContent): string
    {
        $content = is_string($templateContent) ? $templateContent : '';

        $hasOrder   = TagDetector::hasOrderTags($content);
        $hasProduct = TagDetector::hasProductTags($content);
        $hasPost    = TagDetector::hasPostTags($content);
        $hasPage    = TagDetector::hasPageTags($content);
        $hasComment = TagDetector::hasCommentTags($content);

        $choice = 'post';

        if ($hasOrder || $hasProduct) {
            $choice = 'product';
        } elseif ($hasPost) {
            $choice = 'post';
        } elseif ($hasPage) {
            $choice = 'page';
        } elseif ($hasComment) {
            $choice = PluginDetector::isWooCommerceActive() ? 'product' : 'post';
        }

        return $choice;
    }
}
