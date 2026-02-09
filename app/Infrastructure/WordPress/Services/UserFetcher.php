<?php

namespace Notifal\Infrastructure\WordPress\Services;

use Notifal\Domain\Users\UserFetcherInterface;
use Notifal\Domain\Users\DTO\UserDTO;
use Notifal\Shared\Helpers\UserHelper;
use Notifal\Shared\Utils\FilterHelper;
use Notifal\Shared\Utils\Helper;
use WP_User;
use WP_User_Query;

defined('ABSPATH') || exit;

/**
 * Class UserFetcher
 *
 * Fetches user data from WordPress.
 * Acts as a data adapter between Notifal and WordPress user system.
 *
 * @package Notifal\Infrastructure\WordPress\Services
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class UserFetcher implements UserFetcherInterface
{
    /**
     * Find a specific user by their ID.
     *
     * @param int $id User ID.
     * @return UserDTO|null Null if user not found.
     * @since 2.0.0
     */
    public function findById(int $id): ?UserDTO
    {
        $user = get_user_by('ID', $id);
        
        if (!$user instanceof WP_User) {
            return null;
        }

        return $this->buildUserDTO($user);
    }

    /**
     * Search users by various criteria.
     *
     * @param string $search Search term to match against ID, username, email, first name, last name, display name.
     * @param int $limit Maximum number of results to return.
     * @return UserDTO[] Array of matching users.
     * @since 2.0.0
     */
    public function search(string $search, int $limit = 20): array
    {
        $search = Helper::sanitizeInput($search, 'text');

        if (empty($search) || strlen($search) < 2) {
            return [];
        }

        // Check if search is numeric (user ID)
        if (is_numeric($search)) {
            $user = $this->findById((int) $search);
            return $user ? [$user] : [];
        }

        // Check if search looks like an email
        if (is_email($search)) {
            $user = get_user_by('email', $search);
            return $user ? [$this->buildUserDTO($user)] : [];
        }

        // Search by username, first name, last name, display name
        $query = new WP_User_Query([
            'search' => '*' . $search . '*',
            'search_columns' => ['user_login', 'user_nicename', 'user_email', 'display_name'],
            'number' => $limit,
            'orderby' => 'display_name',
            'order' => 'ASC',
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => 'first_name',
                    'value' => $search,
                    'compare' => 'LIKE'
                ],
                [
                    'key' => 'last_name',
                    'value' => $search,
                    'compare' => 'LIKE'
                ],
                [
                    'key' => 'nickname',
                    'value' => $search,
                    'compare' => 'LIKE'
                ]
            ]
        ]);

        $users = $query->get_results();
        $results = [];

        foreach ($users as $user) {
            $results[] = $this->buildUserDTO($user);
        }

        return $results;
    }

    /**
     * Get a random user (for demo or preview purposes).
     *
     * @param array $filters Optional filters to apply
     * @return UserDTO|null Null if no user found.
     * @since 2.0.0
     */
    public function getRandom(array $filters = []): ?UserDTO
    {
        $args = [
            'number' => 1,
            'orderby' => 'rand',
            'role__not_in' => ['administrator'], // Exclude admins for security
        ];

        // Apply custom filters if provided
        $args = $this->applyFilters($args, $filters);

        $query = new WP_User_Query($args);

        if (empty($query->get_results())) {
            return null;
        }

        return $this->buildUserDTO($query->get_results()[0]);
    }

    /**
     * Retrieve multiple random users for pool-based caching.
     *
     * @param int $count Number of users to fetch (default: 20)
     * @param array $filters Optional filters to apply
     * @return UserDTO[] Array of UserDTO objects
     * @since 2.0.0
     */
    public function getRandomPool(int $count = 20, array $filters = []): array
    {
        // Ensure we don't fetch too many users (performance limit)
        $count = max(1, min($count, 50));
        
        $args = [
            'number' => $count,
            'orderby' => 'rand',
            'role__not_in' => ['administrator'], // Exclude admins for security
        ];

        // Apply custom filters if provided
        $args = $this->applyFilters($args, $filters);

        $query = new WP_User_Query($args);

        if (empty($query->get_results())) {
            return [];
        }

        $users = [];
        foreach ($query->get_results() as $user) {
            $users[] = $this->buildUserDTO($user);
        }

        return $users;
    }

    /**
     * Get the currently logged-in user (for preview purposes).
     *
     * @return UserDTO|null Null if no user is logged in.
     * @since 2.0.0
     */
    public function getCurrent(): ?UserDTO
    {
        $currentUserId = UserHelper::getCurrentUserId();
        
        if (!$currentUserId) {
            return null;
        }

        return $this->findById($currentUserId);
    }

    /**
     * Apply custom filters to user query arguments.
     *
     * @param array $args Base query arguments
     * @param array $filters Filter configuration
     * @return array Modified query arguments
     * @since 2.0.0
     */
    private function applyFilters(array $args, array $filters): array
    {
        if (empty($filters)) {
            return $args;
        }

        // Role filter
        if (isset($filters['roles']) && !empty($filters['roles'])) {
            $args['role__in'] = $filters['roles'];
        }

        // Specific users filter
        if (isset($filters['users']) && !empty($filters['users'])) {
            $args['include'] = $filters['users'];
        }

        // Custom filter
        if (isset($filters['custom_filter']) && !empty($filters['custom_filter'])) {
            $args = $this->applyCustomFilter($args, $filters['custom_filter']);
        }

        return $args;
    }

    /**
     * Apply custom meta filter to user query.
     *
     * @param array $args Query arguments
     * @param string $customFilter Custom filter string
     * @return array Modified query arguments
     * @since 2.0.0
     */
    private function applyCustomFilter(array $args, string $customFilter): array
    {
        $metaQueries = FilterHelper::parseCustomFilter($customFilter);

        if (empty($metaQueries)) {
            return $args;
        }

        // Initialize meta_query if not set
        if (!isset($args['meta_query'])) {
            $args['meta_query'] = [];
        }

        // If there's only one condition, add it directly
        if (count($metaQueries) === 1) {
            $args['meta_query'][] = $metaQueries[0];
        } else {
            // Multiple conditions with relation
            $args['meta_query'][] = $metaQueries;
        }

        return $args;
    }

    /**
     * Build a UserDTO from WordPress user object.
     *
     * @param WP_User $user WordPress user object.
     * @return UserDTO
     * @since 2.0.0
     */
    private function buildUserDTO(WP_User $user): UserDTO
    {
        return new UserDTO(
            $user->ID,
            get_user_meta($user->ID, 'first_name', true) ?: '',
            get_user_meta($user->ID, 'last_name', true) ?: '',
            $user->user_email ?: '',
            $user->user_login ?: '',
            $user->user_url ?: '',
            $user->user_nicename ?: '',
            $user->user_registered ?: '',
            $user->display_name ?: ''
        );
    }
} 