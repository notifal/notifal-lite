<?php

namespace Notifal\Domain\Products\DTO;

defined('ABSPATH') || exit;

/**
 * Class ProductDTO
 *
 * Represents a WooCommerce product for tag resolution and template previews.
 * Includes only critical fields and a dynamic meta accessor for custom fields.
 *
 * @package Notifal\Domain\Products\DTO
 * @since 2.0.0
 */
class ProductDTO
{
    /**
     * Unique identifier of the product.
     *
     * @var int
     * @since 2.0.0
     */
    private int $id;

    /**
     * Product name.
     *
     * @var string
     * @since 2.0.0
     */
    private string $name;

    /**
     * The permalink (URL) to the product page.
     *
     * @var string|null
     * @since 2.0.0
     */
    private ?string $link;

    /**
     * Parent product ID used for aggregate WooCommerce meta (e.g. total_sales).
     *
     * @var int|null
     * @since 2.3.10
     */
    private ?int $parentProductId = null;

    /**
     * Variation ID used for per-variation meta such as prices.
     *
     * @var int|null
     * @since 2.3.10
     */
    private ?int $variationContextId = null;

    /**
     * ProductDTO constructor.
     *
     * @param int         $id   Unique product ID.
     * @param string      $name Product name.
     * @param string|null $link Product URL.
     *
     * @since 2.0.0
     */
    public function __construct(
        int $id,
        string $name,
        ?string $link = null
    ) {
        $this->id   = $id;
        $this->name = $name;
        $this->link = $link;
    }

    /**
     * Get the unique product ID.
     *
     * @return int
     * @since 2.0.0
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Get the product name.
     *
     * For variable products this returns the resolved variation label when one is set.
     *
     * @return string
     * @since 2.0.0
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the regular price of the product (raw float).
     *
     * @return float|null
     * @since 2.0.0
     */
    public function getRegularPrice(): ?float
    {
        $price = $this->getMeta('_regular_price');
        return $price !== '' ? (float) $price : null;
    }
    /**
     * Get the sale price of the product (raw float).
     *
     * @return float|null
     * @since 2.0.0
     */
    public function getSalePrice(): ?float
    {
        $price = $this->getMeta('_sale_price');
        return $price !== '' ? (float) $price : null;
    }

    /**
     * Get the product permalink (URL).
     *
     * @return string|null
     * @since 2.0.0
     */
    public function getLink(): ?string
    {
        return $this->link;
    }

    /**
     * Get the parent product ID for variable products.
     *
     * @return int Parent product ID (same as {@see getId()} for simple products).
     * @since 2.3.10
     */
    public function getParentProductId(): int
    {
        return $this->resolveParentProductId();
    }

    /**
     * Get the resolved variation ID used for prices, URLs, and add-to-cart.
     *
     * @return int|null Variation ID or null when the product is not variable.
     * @since 2.3.10
     */
    public function getVariationContextId(): ?int
    {
        return $this->variationContextId;
    }

    /**
     * Get the product publish date.
     *
     * @return string|null Product publish date in Y-m-d H:i:s format
     * @since 2.0.0
     */
    public function getPublishDate(): ?string
    {
        $post = get_post($this->id);
        return $post ? $post->post_date : null;
    }

    /**
     * Get the product modified date.
     *
     * @return string|null Product modified date in Y-m-d H:i:s format
     * @since 2.0.0
     */
    public function getModifiedDate(): ?string
    {
        $post = get_post($this->id);
        return $post ? $post->post_modified : null;
    }

    /**
     * Get the product created date (alias for publish date).
     *
     * @return string|null Product created date in Y-m-d H:i:s format
     * @since 2.0.0
     */
    public function getCreatedDate(): ?string
    {
        return $this->getPublishDate();
    }

    /**
     * Attach variable-product context so meta reads use the correct post IDs.
     *
     * @param int      $parentProductId    Parent product ID for aggregate meta.
     * @param int|null $variationContextId Variation ID for price-level meta.
     * @return void
     * @since 2.3.10
     */
    public function setProductContext(int $parentProductId, ?int $variationContextId = null): void
    {
        // Store the parent product ID for WooCommerce aggregate fields.
        $this->parentProductId = max(0, $parentProductId);

        // Store the variation context when prices should come from a child variation.
        $this->variationContextId = $variationContextId !== null && $variationContextId > 0
            ? $variationContextId
            : null;
    }

    /**
     * Get a custom meta field for this product.
     *
     * Variable products store sales counts on the parent while prices live on variations.
     *
     * @param string $key Meta key.
     * @return mixed Meta value or empty string when not found.
     * @since 2.0.0
     * @since 2.3.10 changed to resolve parent product ID and variation context ID for variable products.
     */
    public function getMeta(string $key)
    {
        // Resolve the parent product ID for aggregate WooCommerce meta.
        $parentId = $this->resolveParentProductId();

        // WooCommerce increments total_sales on the parent when a variation is purchased.
        if ($key === 'total_sales' && $parentId > 0) {
            return get_post_meta($parentId, $key, true);
        }

        // Price-related meta should be read from the sellable variation when available.
        if ($this->isVariationPriceMetaKey($key) && $this->variationContextId > 0) {
            return get_post_meta($this->variationContextId, $key, true);
        }

        // Default to the DTO product ID for direct lookups.
        $metaSourceId = $this->variationContextId > 0 ? $this->variationContextId : $this->id;
        $value        = get_post_meta($metaSourceId, $key, true);

        // Fall back to the parent when the variation does not define the requested key.
        if (($value === '' || $value === false) && $parentId > 0 && $parentId !== $metaSourceId) {
            return get_post_meta($parentId, $key, true);
        }

        return $value;
    }

    /**
     * Resolve the parent product ID for aggregate meta lookups.
     *
     * @return int Parent product ID or the current DTO ID for simple products.
     * @since 2.3.10
     */
    private function resolveParentProductId(): int
    {
        // Use the explicitly stored parent ID when ProductFetcher provided it.
        if ($this->parentProductId !== null && $this->parentProductId > 0) {
            return $this->parentProductId;
        }

        return $this->id;
    }

    /**
     * Determine whether a meta key belongs to variation-level pricing data.
     *
     * @param string $key Meta key.
     * @return bool
     * @since 2.3.10
     */
    private function isVariationPriceMetaKey(string $key): bool
    {
        // Match WooCommerce price meta keys stored on variation posts.
        return in_array($key, ['_regular_price', '_sale_price', '_price'], true);
    }
}
