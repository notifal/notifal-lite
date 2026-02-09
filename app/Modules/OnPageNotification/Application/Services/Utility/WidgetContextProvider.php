<?php

namespace Notifal\Modules\OnPageNotification\Application\Services\Utility;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;

defined('ABSPATH') || exit;

/**
 * Class WidgetContextProvider
 *
 * Provides context data to Elementor widgets and Block Editor blocks during frontend rendering.
 * Ensures widgets display data that matches the notification's content source settings.
 *
 * @package Notifal\Modules\OnPageNotification\Application\Services
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class WidgetContextProvider
{
    /**
     * @var array Current context data for widget rendering
     */
    private static $currentContext = null;

    /**
     * @var bool Whether context injection is active
     */
    private static $isActive = false;

    /**
     * Register hooks for context injection.
     *
     * @since 2.0.0
     */
    public function register(): void
    {
        // Hook into Elementor widget product data filter
        add_filter(FilterHooks::ELEMENTOR_RANDOM_PRODUCT_DATA, [$this, 'provideProductContext'], 10, 1);
        
        // Hook into Block Editor product data (we'll need to add this filter to blocks)
        add_filter('notifal_block_product_data', [$this, 'provideProductContext'], 10, 1);
        
        // Clean up context after rendering
        add_action(ActionHooks::ONPAGE_ELIGIBILITY_AFTER_PROCESS, [$this, 'clearContextAfterProcessing'], 10, 2);
    }

    /**
     * Set the current context for widget rendering.
     *
     * This should be called before rendering templates that contain widgets.
     *
     * @param array $context Context data containing product, order, user objects
     * @since 2.0.0
     */
    public static function setContext(array $context): void
    {
        self::$currentContext = $context;
        self::$isActive = true;
    }

    /**
     * Clear the current context after rendering.
     *
     * @since 2.0.0
     */
    public static function clearContext(): void
    {
        self::$currentContext = null;
        self::$isActive = false;
    }

    /**
     * Check if context injection is currently active.
     *
     * @return bool True if context is active, false otherwise
     * @since 2.0.0
     */
    public static function isActive(): bool
    {
        return self::$isActive && !empty(self::$currentContext);
    }

    /**
     * Get the current context data.
     *
     * @return array|null Current context or null if not set
     * @since 2.0.0
     */
    public static function getContext(): ?array
    {
        return self::$currentContext;
    }

    /**
     * Provide product context to Elementor widgets.
     *
     * This method intercepts the random product fetching in widgets
     * and provides the product from the current notification context.
     *
     * @param mixed $product Original product (usually random)
     * @return mixed Context product or original product
     * @since 2.0.0
     */
    public function provideProductContext($product)
    {
        // Only provide context during frontend notification rendering
        if (!self::isActive()) {
            return $product;
        }

        $context = self::getContext();
        
        // Return context product if available, otherwise fall back to original
        if (isset($context['product']) && $context['product']) {
            return $context['product'];
        }

        return $product;
    }

    /**
     * Get order context for widgets that might need it.
     *
     * @return mixed Order object or null
     * @since 2.0.0
     */
    public static function getOrderContext()
    {
        if (!self::isActive()) {
            return null;
        }

        $context = self::getContext();
        return $context['order'] ?? null;
    }

    /**
     * Get user context for widgets that might need it.
     *
     * @return mixed User object or null
     * @since 2.0.0
     */
    public static function getUserContext()
    {
        if (!self::isActive()) {
            return null;
        }

        $context = self::getContext();
        return $context['user'] ?? null;
    }


    /**
     * Hook cleanup method for after eligibility processing.
     *
     * @param array $eligibleNotifications
     * @param array $context
     * @since 2.0.0
     */
    public function clearContextAfterProcessing($eligibleNotifications = [], $context = []): void
    {
        self::clearContext();
    }
}

