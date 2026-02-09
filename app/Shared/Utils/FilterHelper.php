<?php

namespace Notifal\Shared\Utils;
use Notifal\Shared\Utils\Helper;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Helper class for parsing and applying custom filters.
 *
 * Provides common functionality for parsing custom filter strings used in
 * both user and content filtering operations.
 *
 * @package Notifal\Shared\Utils
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class FilterHelper
{
    /**
     * Parse custom filter string into meta query array.
     *
     * Supports logical operators (AND/OR) and various comparison operators.
     *
     * @param string $customFilter Custom filter string
     * @return array Meta query array
     * @since 2.0.0
     */
    public static function parseCustomFilter(string $customFilter): array
    {
        if (empty($customFilter)) {
            return [];
        }

        // Decode HTML entities first
        $customFilter = html_entity_decode($customFilter, ENT_QUOTES, 'UTF-8');

        // Handle logical operators (convert text to symbols for consistency)
        $customFilter = str_replace([' AND ', ' and '], ' && ', $customFilter);
        $customFilter = str_replace([' OR ', ' or '], ' || ', $customFilter);

        // Determine if we have multiple conditions with logical operators
        $hasAnd = strpos($customFilter, '&&') !== false;
        $hasOr = strpos($customFilter, '||') !== false;

        if (!$hasAnd && !$hasOr) {
            // Single condition
            $condition = self::parseSingleCondition(trim($customFilter));
            return $condition ? [$condition] : [];
        }

        if ($hasAnd && $hasOr) {
            // Complex conditions with both AND and OR - need to handle precedence
            return self::parseComplexConditions($customFilter);
        }

        // Simple multiple conditions with only AND or OR
        $operator = $hasAnd ? '&&' : '||';
        $relation = $hasAnd ? 'AND' : 'OR';
        $conditions = explode($operator, $customFilter);

        $metaQueries = ['relation' => $relation];

        foreach ($conditions as $condition) {
            $parsedCondition = self::parseSingleCondition(trim($condition));
            if ($parsedCondition) {
                $metaQueries[] = $parsedCondition;
            }
        }

        return count($metaQueries) > 1 ? $metaQueries : [];
    }

    /**
     * Parse a single condition into meta query format.
     *
     * @param string $condition Single condition string
     * @return array|null Meta query condition or null if invalid
     * @since 2.0.0
     */
    private static function parseSingleCondition(string $condition): ?array
    {
        // Supported operators (order matters - check longer operators first)
        $operators = ['>=', '<=', '!=', '>', '<', '=', 'LIKE', 'NOT LIKE'];

        foreach ($operators as $operator) {
            $pos = strpos($condition, $operator);
            if ($pos !== false) {
                $metaKey = trim(substr($condition, 0, $pos));
                $metaValue = trim(substr($condition, $pos + strlen($operator)));

                if (empty($metaKey)) {
                    continue;
                }

                // Convert WordPress meta query compare format
                $compare = $operator;
                if ($operator === '=') {
                    $compare = '=';
                } elseif ($operator === '!=') {
                    $compare = '!=';
                }

                // Handle different value types
                $metaValue = self::sanitizeMetaValue($metaValue);

                return [
                    'key' => Helper::sanitizeInput($metaKey, 'text'),
                    'value' => $metaValue,
                    'compare' => $compare,
                    'type' => self::determineMetaType($metaValue, $operator)
                ];
            }
        }

        // Fallback: try simple key:value format for backward compatibility
        if (strpos($condition, ':') !== false) {
            [$metaKey, $metaValue] = explode(':', $condition, 2);
            $metaKey = trim($metaKey);
            $metaValue = trim($metaValue);

            if (!empty($metaKey) && !empty($metaValue)) {
                return [
                    'key' => Helper::sanitizeInput($metaKey, 'text'),
                    'value' => self::sanitizeMetaValue($metaValue),
                    'compare' => '='
                ];
            }
        }

        return null;
    }

    /**
     * Parse complex conditions with both AND and OR operators.
     *
     * @param string $customFilter Complex filter string
     * @return array Meta query array
     * @since 2.0.0
     */
    private static function parseComplexConditions(string $customFilter): array
    {
        // Split by OR first (lower precedence)
        $orGroups = explode('||', $customFilter);

        if (count($orGroups) === 1) {
            // No OR operators, just handle AND
            $andConditions = explode('&&', $customFilter);
            $metaQueries = ['relation' => 'AND'];

            foreach ($andConditions as $condition) {
                $parsedCondition = self::parseSingleCondition(trim($condition));
                if ($parsedCondition) {
                    $metaQueries[] = $parsedCondition;
                }
            }

            return count($metaQueries) > 1 ? $metaQueries : [];
        }

        // Handle OR groups, each may contain AND conditions
        $metaQueries = ['relation' => 'OR'];

        foreach ($orGroups as $orGroup) {
            $orGroup = trim($orGroup);

            if (strpos($orGroup, '&&') !== false) {
                // This OR group has AND conditions
                $andConditions = explode('&&', $orGroup);
                $andGroup = ['relation' => 'AND'];

                foreach ($andConditions as $condition) {
                    $parsedCondition = self::parseSingleCondition(trim($condition));
                    if ($parsedCondition) {
                        $andGroup[] = $parsedCondition;
                    }
                }

                if (count($andGroup) > 1) {
                    $metaQueries[] = $andGroup;
                }
            } else {
                // Single condition in OR group
                $parsedCondition = self::parseSingleCondition($orGroup);
                if ($parsedCondition) {
                    $metaQueries[] = $parsedCondition;
                }
            }
        }

        return count($metaQueries) > 1 ? $metaQueries : [];
    }

    /**
     * Sanitize meta value based on its type.
     *
     * @param string $value Raw meta value
     * @return mixed Sanitized meta value
     * @since 2.0.0
     */
    private static function sanitizeMetaValue($value)
    {
        // Remove quotes if present
        $value = trim($value, '"\'');

        // Check if it's a number
        if (is_numeric($value)) {
            return strpos($value, '.') !== false ? (float) $value : (int) $value;
        }

        // Return as sanitized string
        return Helper::sanitizeInput($value, 'text');
    }

    /**
     * Determine meta type for WordPress meta query.
     *
     * @param mixed $value Meta value
     * @param string $operator Comparison operator
     * @return string Meta type
     * @since 2.0.0
     */
    private static function determineMetaType($value, string $operator): string
    {
        // For numeric comparisons, use NUMERIC type
        if (in_array($operator, ['>', '<', '>=', '<=']) || is_numeric($value)) {
            return 'NUMERIC';
        }

        // For LIKE operations, use CHAR type
        if (in_array($operator, ['LIKE', 'NOT LIKE'])) {
            return 'CHAR';
        }

        // Default to CHAR for text comparisons
        return 'CHAR';
    }

    /**
     * Evaluate custom filter against an object (post, product, order, etc.).
     *
     * This method evaluates a custom filter string against an object's meta data
     * to determine if the object matches the filter criteria.
     *
     * @param string $customFilter Custom filter string to evaluate
     * @param int $objectId Object ID (post ID, product ID, order ID, etc.)
     * @param string $objectType Object type ('product', 'shop_order', 'post', etc.)
     * @return bool True if object matches filter, false otherwise
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function evaluateCustomFilterForObject(string $customFilter, int $objectId, string $objectType = 'post'): bool
    {
        if (empty($customFilter)) {
            return true;
        }

        // Parse the custom filter
        $metaQueries = self::parseCustomFilter($customFilter);

        if (empty($metaQueries)) {
            return true;
        }

        // Evaluate the meta queries
        return self::evaluateMetaQueries($metaQueries, $objectId, $objectType);
    }

    /**
     * Evaluate meta queries against an object.
     *
     * @param array $metaQueries Parsed meta query array
     * @param int $objectId Object ID
     * @param string $objectType Object type
     * @return bool True if object matches all queries
     * @since 2.0.0
     */
    private static function evaluateMetaQueries(array $metaQueries, int $objectId, string $objectType): bool
    {
        if (empty($metaQueries)) {
            return true;
        }

        $relation = $metaQueries['relation'] ?? 'AND';
        $relation = strtoupper($relation);

        $results = [];

        foreach ($metaQueries as $key => $query) {
            // Skip the relation key
            if ($key === 'relation') {
                continue;
            }

            // Handle nested queries (sub-groups)
            if (isset($query['relation'])) {
                $results[] = self::evaluateMetaQueries($query, $objectId, $objectType);
                continue;
            }

            // Evaluate single condition
            if (isset($query['key'])) {
                $results[] = self::evaluateSingleCondition($query, $objectId, $objectType);
            }
        }

        // Apply relation logic
        if ($relation === 'OR') {
            // For OR, at least one condition must be true
            return !empty($results) && in_array(true, $results, true);
        } else {
            // For AND, all conditions must be true
            return !empty($results) && !in_array(false, $results, true);
        }
    }

    /**
     * Evaluate a single meta condition against an object.
     *
     * @param array $condition Single meta condition
     * @param int $objectId Object ID
     * @param string $objectType Object type
     * @return bool True if condition is met
     * @since 2.0.0
     */
    private static function evaluateSingleCondition(array $condition, int $objectId, string $objectType): bool
    {
        $metaKey = $condition['key'] ?? '';
        $expectedValue = $condition['value'] ?? '';
        $compare = $condition['compare'] ?? '=';
        $type = $condition['type'] ?? 'CHAR';

        if (empty($metaKey)) {
            return false;
        }

        // Get the actual meta value from the object
        $actualValue = get_post_meta($objectId, $metaKey, true);

        // If meta doesn't exist, treat as empty string
        if ($actualValue === false || $actualValue === null) {
            $actualValue = '';
        }

        // Type casting based on meta type
        if ($type === 'NUMERIC') {
            $actualValue = is_numeric($actualValue) ? (float) $actualValue : 0;
            $expectedValue = is_numeric($expectedValue) ? (float) $expectedValue : 0;
        }

        // Evaluate based on comparison operator
        switch ($compare) {
            case '=':
                return $actualValue == $expectedValue;
            
            case '!=':
                return $actualValue != $expectedValue;
            
            case '>':
                return $actualValue > $expectedValue;
            
            case '<':
                return $actualValue < $expectedValue;
            
            case '>=':
                return $actualValue >= $expectedValue;
            
            case '<=':
                return $actualValue <= $expectedValue;
            
            case 'LIKE':
                return stripos((string)$actualValue, (string)$expectedValue) !== false;
            
            case 'NOT LIKE':
                return stripos((string)$actualValue, (string)$expectedValue) === false;
            
            default:
                return false;
        }
    }
}
