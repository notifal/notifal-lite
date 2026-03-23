<?php

namespace Notifal\Domain\Products;

use Notifal\Domain\Products\DTO\ProductDTO;

defined('ABSPATH') || exit;

/**
 * Interface ProductFetcherInterface
 *
 * Contract for fetching product data.
 * Allows for different implementations (WooCommerce, API, etc).
 *
 * @package Notifal\Domain\Products
 * @author Hossein <hossein@notifal.com>
 * @since 2.0.0
 */
interface ProductFetcherInterface
{
    /**
     * Return one random product (for preview/demo/random display).
     *
     * @param array $filters Optional filters to apply
     * @return ProductDTO|null Null if no product is found.
     * @since 2.0.0
     */
    public function getRandom(array $filters = []): ?ProductDTO;

    /**
     * Return multiple random products for pool-based caching.
     *
     * @param int $count Number of products to fetch (default: 20)
     * @param array $filters Optional filters to apply
     * @return ProductDTO[] Array of ProductDTO objects
     * @since 2.0.0
     */
    public function getRandomPool(int $count = 20, array $filters = []): array;

    /**
     * Whether the filter set requires every pool member to pass WooCommerce live "on sale" checks.
     *
     * @param array $filters Filters from {@see ProductFilterBuilder} / legacy keys.
     * @return bool
     * @since 2.0.0
     */
    public function requiresLiveSaleValidation(array $filters): bool;

    /**
     * Keep only DTOs whose product still passes {@see \WC_Product::is_on_sale()} at read time.
     *
     * @param ProductDTO[] $productDtos Pool entries to filter.
     * @return ProductDTO[]
     * @since 2.0.0
     */
    public function filterProductPoolToLiveSaleOnly(array $productDtos): array;

    /**
     * Find a product by its ID.
     *
     * @param int $id Product ID.
     * @return ProductDTO|null Null if product not found.
     * @since 2.0.0
     */
    public function findById(int $id): ?ProductDTO;
}
