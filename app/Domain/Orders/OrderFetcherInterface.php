<?php

namespace Notifal\Domain\Orders;

use Notifal\Domain\Orders\DTO\OrderDTO;

defined('ABSPATH') || exit;

/**
 * Interface OrderFetcherInterface
 *
 * Contract for fetching order data.
 * Allows for different implementations (WooCommerce, API, etc).
 *
 * @package Notifal\Domain\Orders
 * @author Hossein <hossein@notifal.com>
 * @since 2.0.0
 */
interface OrderFetcherInterface
{
    /**
     * Retrieve a single random order.
     *
     * @param array $filters Optional filters to apply
     * @return OrderDTO|null Null if no order found.
     * @since 2.0.0
     */
    public function getRandom(array $filters = []): ?OrderDTO;

    /**
     * Retrieve multiple random orders for pool-based caching.
     *
     * @param int $count Number of orders to fetch (default: 20)
     * @param array $filters Optional filters to apply
     * @return OrderDTO[] Array of OrderDTO objects
     * @since 2.0.0
     */
    public function getRandomPool(int $count = 20, array $filters = []): array;

    /**
     * Find an order by its ID.
     *
     * @param int $id Order ID.
     * @return OrderDTO|null Null if order not found.
     * @since 2.0.0
     */
    public function findById(int $id): ?OrderDTO;
}
