<?php

namespace Notifal\Infrastructure\WordPress\Services;

use Notifal\Domain\Orders\DTO\OrderDTO;
use Notifal\Domain\Orders\OrderFetcherInterface;

defined('ABSPATH') || exit;

/**
 * Class NullOrderFetcher
 *
 * Null Object implementation for OrderFetcherInterface.
 * Used when WooCommerce is not active to prevent fatal errors.
 * Returns null/empty arrays gracefully instead of throwing errors.
 *
 * @package Notifal\Infrastructure\WordPress\Services
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class NullOrderFetcher implements OrderFetcherInterface
{

    /**
     * Always returns null as no WooCommerce orders are available.
     *
     * @param array $filters Optional filters to apply (ignored)
     * @return OrderDTO|null Always returns null
     * @since 2.0.0
     */
    public function getRandom(array $filters = []): ?OrderDTO
    {
        return null;
    }

    /**
     * Always returns empty array as no WooCommerce orders are available.
     *
     * @param int $count Number of orders to fetch (ignored)
     * @param array $filters Optional filters to apply (ignored)
     * @return OrderDTO[] Always returns empty array
     * @since 2.0.0
     */
    public function getRandomPool(int $count = 20, array $filters = []): array
    {
        return [];
    }

    /**
     * Always returns null as no WooCommerce orders are available.
     *
     * @param int $id Order ID (ignored)
     * @return OrderDTO|null Always returns null
     * @since 2.0.0
     */
    public function findById(int $id): ?OrderDTO
    {
        return null;
    }
}
