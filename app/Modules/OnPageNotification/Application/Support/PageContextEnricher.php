<?php



namespace Notifal\Modules\OnPageNotification\Application\Support;



defined('ABSPATH') || exit;



/**

 * Enriches frontend/API page context for display rules and smart targeting.

 *

 * @package Notifal\Modules\OnPageNotification\Application\Support

 * @since 2.3.7

 * @author Hossein <hossein@notifal.com>

 */

class PageContextEnricher

{

    /**

     * Fill missing post type and taxonomy fields from page ID and URL.

     *

     * @param array<string, mixed> $context Request context array.

     * @return array<string, mixed>

     * @since 2.3.7

     */

    public function enrich(array $context): array

    {

        // Resolve archive metadata from the visitor URL first.

        $context = $this->inferArchiveFromUrl($context);



        $pageId = absint($context['page_id'] ?? 0);

        if ($pageId <= 0) {

            // Still attach view flags when only URL/archive metadata is available.
            return PageContextHelper::attachSmartTargetingViewFlags($context);

        }

        // Preserve taxonomy archive context when category term IDs collide with post IDs.
        if (empty($context['post_type']) && !empty($context['categories'])) {
            $categoryIds = array_map('intval', (array) $context['categories']);
            if (in_array($pageId, $categoryIds, true)) {
                $context['post_type'] = 'category';
            }
        }

        // Resolve post type when the client did not send one.

        if (empty($context['post_type'])) {

            $context = $this->resolvePostTypeFromPageId($context, $pageId);

        }



        $postType = sanitize_key((string) ($context['post_type'] ?? ''));



        // Attach taxonomy terms for singular registered post types.

        if (PageContextHelper::isSingularContext($context)) {

            $context = $this->attachSingularTaxonomyTerms($context, $pageId, $postType);

        }



        // Category archive pages use the term ID as page_id.

        if ($postType === 'category' && empty($context['categories'])) {

            $context['categories'] = [$pageId];

        }



        // WooCommerce product category archives use the term ID as page_id.

        if ($postType === 'product_category' && empty($context['product_categories'])) {

            $context['product_categories'] = [$pageId];

        }

        // Resolve smart targeting flags only after post_type and page_id are finalized.
        return PageContextHelper::attachSmartTargetingViewFlags($context);

    }



    /**

     * Attach taxonomy term IDs for a singular post object.

     *

     * @param array<string, mixed> $context  Request context array.

     * @param int                  $pageId   Singular object ID.

     * @param string               $postType Registered post type slug.

     * @return array<string, mixed>

     * @since 2.3.7

     */

    private function attachSingularTaxonomyTerms(array $context, int $pageId, string $postType): array

    {

        // Blog categories remain available for legacy display rules.

        if ($postType === 'post' && empty($context['categories']) && taxonomy_exists('category')) {

            $termIds = wp_get_post_terms($pageId, 'category', ['fields' => 'ids']);

            if (!is_wp_error($termIds) && !empty($termIds)) {

                $context['categories'] = array_map('intval', $termIds);

            }

        }



        // WooCommerce product categories remain available for legacy display rules.

        if ($postType === 'product' && empty($context['product_categories']) && taxonomy_exists('product_cat')) {

            $termIds = wp_get_post_terms($pageId, 'product_cat', ['fields' => 'ids']);

            if (!is_wp_error($termIds) && !empty($termIds)) {

                $context['product_categories'] = array_map('intval', $termIds);

            }

        }



        // Collect all assigned taxonomy terms for the singular object.

        $taxonomies = get_object_taxonomies($postType, 'names');

        $objectTaxonomies = is_array($context['object_taxonomies'] ?? null) ? $context['object_taxonomies'] : [];



        foreach ($taxonomies as $taxonomy) {

            $taxonomy = sanitize_key((string) $taxonomy);

            if ($taxonomy === '' || !taxonomy_exists($taxonomy)) {

                continue;

            }



            if (!empty($objectTaxonomies[$taxonomy])) {

                continue;

            }



            $termIds = wp_get_post_terms($pageId, $taxonomy, ['fields' => 'ids']);

            if (is_wp_error($termIds) || empty($termIds)) {

                continue;

            }



            $objectTaxonomies[$taxonomy] = array_map('intval', $termIds);

        }



        if (!empty($objectTaxonomies)) {

            $context['object_taxonomies'] = $objectTaxonomies;

        }



        return $context;

    }



    /**

     * Resolve archive post type from page ID, preferring taxonomy terms over posts.

     *

     * @param array<string, mixed> $context Request context array.

     * @param int                  $pageId  Page or term ID.

     * @return array<string, mixed>

     * @since 2.3.7

     */

    private function resolvePostTypeFromPageId(array $context, int $pageId): array

    {

        // Prefer taxonomy archives when the URL or categories param indicates a term archive.
        if ($this->shouldResolvePageIdAsCategoryTerm($context, $pageId)) {
            $context['post_type'] = 'category';
            if (empty($context['categories'])) {
                $context['categories'] = [$pageId];
            }

            return $context;
        }

        // Prefer a published singular object when the ID matches a post/page/product/CPT.

        $postType = get_post_type($pageId);

        if (is_string($postType) && $postType !== '' && $postType !== 'revision') {

            $context['post_type'] = $postType;



            return $context;

        }



        // Fall back to taxonomy archives when the ID is a term rather than a post.

        $categoryTerm = get_term($pageId, 'category');

        if ($categoryTerm instanceof \WP_Term && !is_wp_error($categoryTerm)) {

            $context['post_type'] = 'category';

            if (empty($context['categories'])) {

                $context['categories'] = [(int) $categoryTerm->term_id];

            }



            return $context;

        }



        if (taxonomy_exists('product_cat')) {

            $productCategoryTerm = get_term($pageId, 'product_cat');

            if ($productCategoryTerm instanceof \WP_Term && !is_wp_error($productCategoryTerm)) {

                $context['post_type'] = 'product_category';

                if (empty($context['product_categories'])) {

                    $context['product_categories'] = [(int) $productCategoryTerm->term_id];

                }



                return $context;

            }

        }



        return $context;

    }

    /**
     * Determine whether a page ID should resolve as a blog category term archive.
     *
     * Term IDs can share numeric values with unrelated post IDs (e.g. WooCommerce variations).
     *
     * @param array<string, mixed> $context Request context array.
     * @param int                  $pageId  Page or term ID candidate.
     * @return bool
     * @since 2.3.7
     */
    private function shouldResolvePageIdAsCategoryTerm(array $context, int $pageId): bool
    {
        if ($pageId <= 0) {
            return false;
        }

        $categoryIds = array_map('intval', (array) ($context['categories'] ?? []));
        if (in_array($pageId, $categoryIds, true)) {
            return true;
        }

        $path = wp_parse_url((string) ($context['url'] ?? ''), PHP_URL_PATH);
        if (!is_string($path) || !preg_match('#/category/[^/]+#i', $path)) {
            return false;
        }

        $term = get_term($pageId, 'category');

        return $term instanceof \WP_Term && !is_wp_error($term);
    }



    /**

     * Infer taxonomy archive context from the visitor URL path.

     *

     * @param array<string, mixed> $context Request context array.

     * @return array<string, mixed>

     * @since 2.3.7

     */

    private function inferArchiveFromUrl(array $context): array

    {

        $url = (string) ($context['url'] ?? '');

        if ($url === '') {

            return $context;

        }



        $path = wp_parse_url($url, PHP_URL_PATH);

        if (!is_string($path) || $path === '') {

            return $context;

        }



        // Singular URLs should not be treated as taxonomy archives.

        if (empty($context['post_type']) && empty($context['page_id'])) {

            $singularPostId = url_to_postid($url);

            if ($singularPostId > 0) {

                $singularPostType = get_post_type($singularPostId);

                if (is_string($singularPostType) && $singularPostType !== '') {

                    $context['page_id'] = (int) $singularPostId;

                    $context['post_type'] = sanitize_key($singularPostType);

                }

            }

        }



        // Blog category archive: /category/decoration/

        if (empty($context['post_type']) && preg_match('#/category/([^/]+)/?#i', $path, $matches)) {

            $term = get_term_by('slug', sanitize_title($matches[1]), 'category');

            if ($term instanceof \WP_Term && !is_wp_error($term)) {

                $context['post_type'] = 'category';

                $context['page_id'] = (int) $term->term_id;

                $context['categories'] = [(int) $term->term_id];

            }

        }



        // WooCommerce product category archive: /product-category/slug/

        if (empty($context['post_type']) && taxonomy_exists('product_cat') && preg_match('#/product-category/([^/]+)/?#i', $path, $matches)) {

            $term = get_term_by('slug', sanitize_title($matches[1]), 'product_cat');

            if ($term instanceof \WP_Term && !is_wp_error($term)) {

                $context['post_type'] = 'product_category';

                $context['page_id'] = (int) $term->term_id;

                $context['product_categories'] = [(int) $term->term_id];

            }

        }

        return $context;

    }

}


