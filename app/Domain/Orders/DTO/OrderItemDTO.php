<?php

namespace Notifal\Domain\Orders\DTO;

defined('ABSPATH') || exit;

/**
 * Class OrderItemDTO
 *
 * Represents a single item (product) within an order for tag resolution.
 *
 * @package Notifal\Domain\Orders\DTO
 * @author Hossein <hossein@notifal.com>
 * @since 2.0.0
 */
class OrderItemDTO
{
    /**
     * The unique ID of the product.
     *
     * @var int
     */
    private int $productId;

    /**
     * The product name.
     *
     * @var string
     */
    private string $name;

    /**
     * The quantity of the product ordered.
     *
     * @var int
     */
    private int $quantity;

    /**
     * The raw price of the product (without wc_price formatting).
     *
     * @var float
     */
    private float $rawPrice;

    /**
     * OrderItemDTO constructor.
     *
     * @param int    $productId  The unique product ID.
     * @param string $name       The product name.
     * @param int    $quantity   Quantity ordered.
     * @param float  $rawPrice   Price per unit.
     *
     * @since 2.0.0
     */
    public function __construct(
        int $productId,
        string $name,
        int $quantity,
        float $rawPrice
    )
    {
        $this->productId = $productId;
        $this->name      = $name;
        $this->quantity  = $quantity;
        $this->rawPrice  = $rawPrice;
    }

    /**
     * Get the product ID.
     *
     * @return int
     * @since 2.0.0
     */
    public function getProductId(): int
    {
        return $this->productId;
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
     * Get the quantity of the product.
     *
     * @return int
     * @since 2.0.0
     */
    public function getQuantity(): int
    {
        return $this->quantity;
    }

    /**
     * Get the formatted price (WooCommerce format).
     *
     * @return string
     * @since 2.0.0
     */
    public function getPrice(): string
    {
        return wc_price($this->rawPrice);
    }

    /**
     * Get the raw price value without formatting.
     *
     * @return float
     * @since 2.0.0
     */
    public function getRawPrice(): float
    {
        return $this->rawPrice;
    }
}
