<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Settings;

use Notifal\Modules\OnPageNotification\Application\Traits\SettingsServiceTrait;

defined('ABSPATH') || exit;

/**
 * Class ContentSourceFilterBuilder
 *
 * Builds filters for different content types from content source settings.
 * Uses composition with specialized filter builders for each content type.
 * Handles both legacy single filters and new multiple filters with AND/OR logic.
 *
 * @package Notifal\Modules\OnPageNotification\Application\Services
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class ContentSourceFilterBuilder
{
    use SettingsServiceTrait;

    /**
     * @var OrderFilterBuilder
     */
    private OrderFilterBuilder $orderFilterBuilder;

    /**
     * @var ProductFilterBuilder
     */
    private ProductFilterBuilder $productFilterBuilder;

    /**
     * @var UserFilterBuilder
     */
    private UserFilterBuilder $userFilterBuilder;

    /**
     * @var PostFilterBuilder
     */
    private PostFilterBuilder $postFilterBuilder;

    /**
     * @var PageFilterBuilder
     */
    private PageFilterBuilder $pageFilterBuilder;

    /**
     * @var CustomPostTypeFilterBuilder
     */
    private CustomPostTypeFilterBuilder $customPostTypeFilterBuilder;

    /**
     * ContentSourceFilterBuilder constructor.
     */
    public function __construct()
    {
        $this->orderFilterBuilder = new OrderFilterBuilder();
        $this->productFilterBuilder = new ProductFilterBuilder();
        $this->userFilterBuilder = new UserFilterBuilder();
        $this->postFilterBuilder = new PostFilterBuilder();
        $this->pageFilterBuilder = new PageFilterBuilder();
        $this->customPostTypeFilterBuilder = new CustomPostTypeFilterBuilder();
    }

    /**
     * Build order filters from content source settings.
     * Supports both legacy single filters and new multiple filters with AND/OR logic.
     *
     * @param array $settings Content source settings
     * @return array Order filters
     * @since 2.0.0
     */
    public function buildOrderFilters(array $settings): array
    {
        return $this->orderFilterBuilder->buildFilters($settings);
    }

    /**
     * Build product filters from content source settings.
     * Supports both legacy single filters and new multiple filters with AND/OR logic.
     *
     * @param array $settings Content source settings
     * @return array Product filters
     * @since 2.0.0
     */
    public function buildProductFilters(array $settings): array
    {
        return $this->productFilterBuilder->buildFilters($settings);
    }

    /**
     * Build user filters from content source settings.
     * Supports both legacy single filters and new multiple filters with AND/OR logic.
     *
     * @param array $settings Content source settings
     * @return array User filters
     * @since 2.0.0
     */
    public function buildUserFilters(array $settings): array
    {
        return $this->userFilterBuilder->buildFilters($settings);
    }

    /**
     * Build post filters from content source settings.
     * Supports both legacy single filters and new multiple filters with AND/OR logic.
     *
     * @param array $settings Content source settings
     * @return array Post filters
     * @since 2.0.0
     */
    public function buildPostFilters(array $settings): array
    {
        return $this->postFilterBuilder->buildFilters($settings);
    }

    /**
     * Build page filters from content source settings.
     * Supports both legacy single filters and new multiple filters with AND/OR logic.
     *
     * @param array $settings Content source settings
     * @return array Page filters
     * @since 2.0.0
     */
    public function buildPageFilters(array $settings): array
    {
        return $this->pageFilterBuilder->buildFilters($settings);
    }

    /**
     * Build comment filters from content source settings.
     * Supports both legacy single filters and new multiple filters with AND/OR logic.
     * Comment filtering is only available in Notifal Pro.
     *
     * @param array $settings Content source settings
     * @return array Comment filters or empty array if pro not active
     * @since 2.0.0
     */
    public function buildCommentFilters(array $settings): array
    {
        // Comment filters are only available in pro version
        if (!$this->isProFeatureAllowed()) {
            return [];
        }

        // Delegate to pro plugin via filter hook
        return apply_filters('notifal_pro_build_comment_filters', [], $settings);
    }

    /**
     * Build custom post type filters from content source settings.
     * Supports both legacy single filters and new multiple filters with AND/OR logic.
     *
     * @param string $postType The custom post type name
     * @param array $settings Content source settings
     * @return array Custom post type filters
     * @since 2.0.0
     */
    public function buildCustomPostTypeFilters(string $postType, array $settings): array
    {
        return $this->customPostTypeFilterBuilder->buildFilters($postType, $settings);
    }

    /**
     * Parse multiple filters from form data for a specific category.
     *
     * @param array $formData Form data
     * @param string $category Filter category (product, order, user, post, page, comment, custom_posttype)
     * @return array Parsed multiple filters
     * @since 2.0.0
     */
    public function parseMultipleFilters(array $formData, string $category): array
    {
        $filters = [
            'multiple_filters' => false,
            'logic' => 'AND',
            'conditionsUsed' => false,
            'conditions' => []
        ];

        // Check if multiple filters are enabled for this category
        $logicKey = "{$category}_filters_logic";
        if (isset($formData[$logicKey])) {
            $filters['multiple_filters'] = true;
            $filters['logic'] = sanitize_text_field($formData[$logicKey]);
        }

        // Parse filter conditions
        $conditions = [];
        foreach ($formData as $key => $value) {
            if (preg_match("/^filter_(\w+)_type$/", $key, $matches)) {
                $filterId = $matches[1];
                $filterType = sanitize_text_field($value);

                if (!empty($filterType) && $this->isFilterTypeInCategory($filterType, $category)) {
                    $condition = [
                        'id' => $filterId,
                        'type' => $filterType,
                        'enabled' => true,
                        'data' => $this->parseFilterConditionData($formData, $filterId, $filterType, $category)
                    ];

                    $conditions[] = $condition;
                }
            }
        }

        $filters['conditions'] = $conditions;
        $filters['conditionsUsed'] = !empty($conditions);

        return $filters;
    }

    /**
     * Check if a filter type is valid for a given category.
     *
     * @param string $filterType Filter type
     * @param string $category Category (product, order, user, post, page, comment, custom_posttype)
     * @return bool Whether the filter type is valid for the category
     * @since 2.0.0
     */
    private function isFilterTypeInCategory(string $filterType, string $category): bool
    {
        $validTypes = [
            'product' => ['categories', 'specific', 'sale', 'featured', 'date_range', 'custom_meta'],
            'order' => ['status', 'date_range', 'products', 'custom_meta', 'custom_filter'],
            'user' => ['roles', 'specific', 'custom_meta', 'registration_date'],
            'post' => ['categories', 'specific', 'status', 'author', 'date_range', 'custom_meta'],
            'page' => ['specific', 'status', 'author', 'template', 'date_range', 'custom_meta'],
            'comment' => $this->isProFeatureAllowed() ? ['status', 'post_type', 'author', 'date_range', 'custom_meta'] : [],
            'custom_posttype' => ['categories', 'specific', 'status', 'author', 'date_range', 'custom_meta']
        ];

        return in_array($filterType, $validTypes[$category] ?? [], true);
    }

    /**
     * Parse filter condition data from form fields.
     *
     * @param array $formData Form data
     * @param string $filterId Filter ID
     * @param string $filterType Filter type
     * @param string $category Category
     * @return array Parsed filter data
     * @since 2.0.0
     */
    private function parseFilterConditionData(array $formData, string $filterId, string $filterType, string $category): array
    {
        $data = [];

        // Common fields that might exist for any filter type
        $commonFields = [
            'meta_key', 'operator', 'value', 'custom_filter',
            'range', 'start_date', 'end_date', 'date_type'
        ];

        foreach ($commonFields as $field) {
            $key = "filter_{$filterId}_{$field}";
            if (isset($formData[$key])) {
                $data[$field] = $formData[$key];
            }
        }

        // Category-specific field parsing
        switch ($category) {
            case 'product':
                if ($filterType === 'categories' && isset($formData["filter_{$filterId}_categories"])) {
                    $data['categories'] = array_map('intval', $formData["filter_{$filterId}_categories"]);
                } elseif ($filterType === 'specific' && isset($formData["filter_{$filterId}_products"])) {
                    $data['products'] = array_map('intval', $formData["filter_{$filterId}_products"]);
                }
                break;

            case 'order':
                if ($filterType === 'status' && isset($formData["filter_{$filterId}_statuses"])) {
                    $data['statuses'] = array_map('sanitize_text_field', $formData["filter_{$filterId}_statuses"]);
                } elseif ($filterType === 'products' && isset($formData["filter_{$filterId}_products"])) {
                    $data['products'] = array_map('intval', $formData["filter_{$filterId}_products"]);
                }
                break;

            case 'user':
                if ($filterType === 'roles' && isset($formData["filter_{$filterId}_roles"])) {
                    $data['roles'] = array_map('sanitize_text_field', $formData["filter_{$filterId}_roles"]);
                } elseif ($filterType === 'specific' && isset($formData["filter_{$filterId}_users"])) {
                    $data['users'] = array_map('intval', $formData["filter_{$filterId}_users"]);
                }
                break;

            case 'post':
                if ($filterType === 'categories' && isset($formData["filter_{$filterId}_categories"])) {
                    $data['categories'] = array_map('intval', $formData["filter_{$filterId}_categories"]);
                } elseif ($filterType === 'specific' && isset($formData["filter_{$filterId}_posts"])) {
                    $data['posts'] = array_map('intval', $formData["filter_{$filterId}_posts"]);
                } elseif ($filterType === 'status' && isset($formData["filter_{$filterId}_statuses"])) {
                    $data['statuses'] = array_map('sanitize_text_field', $formData["filter_{$filterId}_statuses"]);
                } elseif ($filterType === 'author' && isset($formData["filter_{$filterId}_authors"])) {
                    $data['authors'] = array_map('intval', $formData["filter_{$filterId}_authors"]);
                }
                break;

            case 'page':
                if ($filterType === 'specific' && isset($formData["filter_{$filterId}_pages"])) {
                    $data['pages'] = array_map('intval', $formData["filter_{$filterId}_pages"]);
                } elseif ($filterType === 'status' && isset($formData["filter_{$filterId}_statuses"])) {
                    $data['statuses'] = array_map('sanitize_text_field', $formData["filter_{$filterId}_statuses"]);
                } elseif ($filterType === 'author' && isset($formData["filter_{$filterId}_authors"])) {
                    $data['authors'] = array_map('intval', $formData["filter_{$filterId}_authors"]);
                } elseif ($filterType === 'template' && isset($formData["filter_{$filterId}_templates"])) {
                    $data['templates'] = array_map('sanitize_text_field', $formData["filter_{$filterId}_templates"]);
                }
                break;

            case 'custom_posttype':
                if ($filterType === 'categories' && isset($formData["filter_{$filterId}_categories"])) {
                    $data['categories'] = array_map('intval', $formData["filter_{$filterId}_categories"]);
                } elseif ($filterType === 'specific' && isset($formData["filter_{$filterId}_items"])) {
                    $data['items'] = array_map('intval', $formData["filter_{$filterId}_items"]);
                } elseif ($filterType === 'status' && isset($formData["filter_{$filterId}_statuses"])) {
                    $data['statuses'] = array_map('sanitize_text_field', $formData["filter_{$filterId}_statuses"]);
                } elseif ($filterType === 'author' && isset($formData["filter_{$filterId}_authors"])) {
                    $data['authors'] = array_map('intval', $formData["filter_{$filterId}_authors"]);
                }
                break;
        }

        return $data;
    }

    /**
     * Sanitize multiple filters configuration.
     *
     * @param array $filters Multiple filters configuration
     * @return array Sanitized multiple filters
     * @since 2.0.0
     */
    public function sanitizeMultipleFilters(array $filters): array
    {
        $sanitized = [
            'multiple_filters' => (bool) ($filters['multiple_filters'] ?? false),
            'logic' => sanitize_text_field($filters['logic'] ?? 'AND'),
            'conditions' => []
        ];

        if (!empty($filters['conditions']) && is_array($filters['conditions'])) {
            foreach ($filters['conditions'] as $condition) {
                if (is_array($condition)) {
                    $sanitizedCondition = [
                        'id' => sanitize_text_field($condition['id'] ?? ''),
                        'type' => sanitize_text_field($condition['type'] ?? ''),
                        'enabled' => (bool) ($condition['enabled'] ?? true),
                        'data' => []
                    ];

                    if (!empty($condition['data']) && is_array($condition['data'])) {
                        foreach ($condition['data'] as $key => $value) {
                            if ($key === 'custom_filter') {
                                // Use a more lenient sanitization for custom_filter
                                $sanitizedCondition['data'][$key] = trim(stripslashes($value));
                            } elseif (is_array($value)) {
                                $sanitizedCondition['data'][$key] = array_map('sanitize_text_field', $value);
                            } else {
                                $sanitizedCondition['data'][$key] = sanitize_text_field($value);
                            }
                        }
                    }

                    $sanitized['conditions'][] = $sanitizedCondition;
                }
            }
        }

        return $sanitized;
    }

    /**
     * Check if pro features are allowed (user has active pro license).
     * Uses secure hooks that can only be provided by the legitimate pro plugin.
     *
     * @return bool True if pro features are allowed
     * @since 2.0.0
     */
    private function isProFeatureAllowed(): bool
    {
        return $this->checkProFeatureAllowed('notifal_pro_content_source_features');
    }
}