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
     * Get a custom meta field for this product.
     *
     * @param string $key Meta key.
     * @return mixed|null Meta value or null if not found.
     * @since 2.0.0
     */
    public function getMeta(string $key)
    {
        return get_post_meta($this->id, $key, true);
    }
}
