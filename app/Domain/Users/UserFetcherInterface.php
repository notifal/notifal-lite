<?php

namespace Notifal\Domain\Users;

defined('ABSPATH') || exit;

/**
 * Interface UserFetcherInterface
 *
 * Contract for retrieving user data from any source (WordPress, API, etc.).
 *
 * @package Notifal\Domain\Users
 * @author Hossein <hossein@notifal.com>
 * @since 2.0.0
 */
interface UserFetcherInterface
{
    /**
     * Find a specific user by their ID.
     *
     * @param int $id User ID.
     * @return \Notifal\Domain\Users\DTO\UserDTO|null
     * @since 2.0.0
     */
    public function findById(int $id): ?DTO\UserDTO;

    /**
     * Search users by various criteria.
     *
     * @param string $search Search term to match against ID, username, email, first name, last name, display name.
     * @param int $limit Maximum number of results to return.
     * @return \Notifal\Domain\Users\DTO\UserDTO[] Array of matching users.
     * @since 2.0.0
     */
    public function search(string $search, int $limit = 20): array;

    /**
     * Get a random user (for demo or preview purposes).
     *
     * @param array $filters Optional filters to apply
     * @return \Notifal\Domain\Users\DTO\UserDTO|null
     * @since 2.0.0
     */
    public function getRandom(array $filters = []): ?DTO\UserDTO;

    /**
     * Retrieve multiple random users for pool-based caching.
     *
     * @param int $count Number of users to fetch (default: 20)
     * @param array $filters Optional filters to apply
     * @return \Notifal\Domain\Users\DTO\UserDTO[] Array of UserDTO objects
     * @since 2.0.0
     */
    public function getRandomPool(int $count = 20, array $filters = []): array;

    /**
     * Get the currently logged-in user (for preview purposes).
     *
     * @return \Notifal\Domain\Users\DTO\UserDTO|null Null if no user is logged in.
     * @since 2.0.0
     */
    public function getCurrent(): ?DTO\UserDTO;
}
