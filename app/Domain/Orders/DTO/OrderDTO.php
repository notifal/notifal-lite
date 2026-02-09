<?php

namespace Notifal\Domain\Orders\DTO;

use DateTime;
use Notifal\Domain\Orders\Services\OrderMetaResolver;

defined('ABSPATH') || exit;

/**
 * Class OrderDTO
 *
 * Represents a simplified order object for tag resolution.
 *
 * @package Notifal\Domain\Orders\DTO
 * @author Hossein <hossein@notifal.com>
 * @since 2.0.0
 */
class OrderDTO
{
    /**
     * Unique identifier of the order.
     *
     * @var int
     */
    private int $id;

    /**
     * Order creation datetime.
     *
     * @var DateTime
     */
    private DateTime $createdAt;

    /**
     * List of order items (products).
     *
     * @var OrderItemDTO[]
     */
    private array $items = [];

    /**
     * Order meta resolver for HPOS compatibility.
     *
     * @var OrderMetaResolver
     */
    private OrderMetaResolver $metaResolver;

    /**
     * OrderDTO constructor.
     *
     * @param int                  $id            Unique order ID.
     * @param DateTime             $createdAt     Order creation datetime.
     * @param OrderItemDTO[]       $items         Array of order items (optional).
     * @param OrderMetaResolver    $metaResolver  Meta resolver instance (optional).
     *
     * @since 2.0.0
     */
    public function __construct(
        int $id,
        DateTime $createdAt,
        array $items = [],
        OrderMetaResolver $metaResolver = null
    ) {
        $this->id           = $id;
        $this->createdAt    = $createdAt;
        $this->items        = $items;
        $this->metaResolver = $metaResolver ?? new OrderMetaResolver();
    }

    /**
     * Get the unique order ID.
     *
     * @return int
     * @since 2.0.0
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Get order creation datetime.
     *
     * @return DateTime
     * @since 2.0.0
     */
    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    /**
     * Get all items (products) in the order.
     *
     * @return OrderItemDTO[]
     * @since 2.0.0
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * Get the order created date.
     *
     * @return string Order created date in Y-m-d H:i:s format
     * @since 2.0.0
     */
    public function getCreatedDate(): string
    {
        return $this->createdAt->format('Y-m-d H:i:s');
    }

    /**
     * Get the order completed date from WooCommerce meta.
     *
     * @return string|null Order completed date in Y-m-d H:i:s format or null if not completed
     * @since 2.0.0
     */
    public function getCompletedDate(): ?string
    {
        return $this->formatWooCommerceDate($this->getMeta('_date_completed'));
    }

    /**
     * Get the order paid date from WooCommerce meta.
     *
     * @return string|null Order paid date in Y-m-d H:i:s format or null if not paid
     * @since 2.0.0
     */
    public function getPaidDate(): ?string
    {
        return $this->formatWooCommerceDate($this->getMeta('_date_paid'));
    }

    /**
     * Get the order modified date.
     *
     * This method works with both legacy storage (WordPress posts)
     * and HPOS (High-performance order storage).
     *
     * @return string|null Order modified date in Y-m-d H:i:s format
     * @since 2.0.0
     */
    public function getModifiedDate(): ?string
    {
        return $this->formatWooCommerceDate($this->getMeta('_date_modified'));
    }

    /**
     * Get the order shipping date (if available).
     *
     * @return string|null Order shipping date in Y-m-d H:i:s format or null if not shipped
     * @since 2.0.0
     */
    public function getShippedDate(): ?string
    {
        // Check for common shipping date meta keys
        $shippingMetaKeys = ['_date_shipped', '_shipping_date', '_shipped_date'];

        foreach ($shippingMetaKeys as $metaKey) {
            $formattedDate = $this->formatWooCommerceDate($this->getMeta($metaKey));
            if ($formattedDate !== null) {
                return $formattedDate;
            }
        }

        return null;
    }

    /**
     * Get a custom meta field for this order.
     *
     * This method works with both legacy storage (WordPress posts)
     * and HPOS (High-performance order storage).
     *
     * @param string $key Meta key.
     * @return mixed|null Meta value or null if not found.
     * @since 2.0.0
     */
    public function getMeta(string $key)
    {
        return $this->metaResolver->resolve($this->id, $key);
    }

    /**
     * Format a WooCommerce date meta value to Y-m-d H:i:s format.
     *
     * @param mixed $dateValue Raw date value from meta.
     * @return string|null Formatted date string or null if empty.
     * @since 2.0.0
     */
    private function formatWooCommerceDate($dateValue): ?string
    {
        if (empty($dateValue)) {
            return null;
        }

        // WooCommerce stores dates as timestamps
        if (is_numeric($dateValue)) {
            return date('Y-m-d H:i:s', (int) $dateValue);
        }

        // If it's already a date string, return as is
        return $dateValue;
    }
}
