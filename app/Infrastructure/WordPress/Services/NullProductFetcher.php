<?php

namespace Notifal\Infrastructure\WordPress\Services;

use Notifal\Domain\Products\DTO\ProductDTO;
use Notifal\Domain\Products\ProductFetcherInterface;

defined('ABSPATH') || exit;

/**
 * Class NullProductFetcher
 *
 * Null Object implementation for ProductFetcherInterface.
 * Used when WooCommerce is not active to prevent fatal errors.
 * Returns null/empty arrays gracefully instead of throwing errors.
 *
 * @package Notifal\Infrastructure\WordPress\Services
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class NullProductFetcher implements ProductFetcherInterface
{

    /**
     * Always returns null as no WooCommerce products are available.
     *
     * @param array $filters Optional filters to apply (ignored)
     * @return ProductDTO|null Always returns null
     * @since 2.0.0
     */
    public function getRandom(array $filters = []): ?ProductDTO
    {
        return null;
    }

    /**
     * Always returns empty array as no WooCommerce products are available.
     *
     * @param int $count Number of products to fetch (ignored)
     * @param array $filters Optional filters to apply (ignored)
     * @return ProductDTO[] Always returns empty array
     * @since 2.0.0
     */
    public function getRandomPool(int $count = 20, array $filters = []): array
    {
        return [];
    }

    /**
     * WooCommerce is inactive; no live sale validation applies.
     *
     * @param array $filters Filter configuration (ignored).
     * @return bool Always false.
     * @since 2.0.0
     */
    public function requiresLiveSaleValidation(array $filters): bool
    {
        return false;
    }

    /**
     * Returns the input unchanged when WooCommerce is unavailable.
     *
     * @param array $productDtos Pool entries (ignored shape when empty).
     * @return ProductDTO[]
     * @since 2.0.0
     */
    public function filterProductPoolToLiveSaleOnly(array $productDtos): array
    {
        return $productDtos;
    }

    /**
     * Always returns null as no WooCommerce products are available.
     *
     * @param int $id Product ID (ignored)
     * @return ProductDTO|null Always returns null
     * @since 2.0.0
     */
    public function findById(int $id): ?ProductDTO
    {
        return null;
    }
}
