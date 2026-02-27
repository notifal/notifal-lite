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
 * Uses a stack-based approach so that overlapping or nested render()
 * calls (e.g. Elementor re-entering the renderer during widget
 * registration) each preserve their own context.  setContext() pushes
 * onto the stack, clearContext() pops, and getContext() reads the top.
 *
 * @package Notifal\Modules\OnPageNotification\Application\Services
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class WidgetContextProvider
{
    /**
     * Stack of context arrays.  The most recently pushed context is
     * the "active" one that widgets see via getContext().
     *
     * @var array[]
     */
    private static $contextStack = [];

    /**
     * Register hooks for context injection.
     *
     * @since 2.0.0
     */
    public function register(): void
    {
        // Hook into Elementor widget product data filter
        add_filter(FilterHooks::ELEMENTOR_RANDOM_PRODUCT_DATA, [$this, 'provideProductContext'], 10, 1);

        // Hook into Block Editor product data
        add_filter('notifal_block_product_data', [$this, 'provideProductContext'], 10, 1);

        // Clean up context after rendering
        add_action(ActionHooks::ONPAGE_ELIGIBILITY_AFTER_PROCESS, [$this, 'clearContextAfterProcessing'], 10, 2);
    }

    /**
     * Push a new context onto the stack for the current render pass.
     *
     * Every call to setContext() MUST be paired with a corresponding
     * clearContext() call (even on error paths) to keep the stack balanced.
     *
     * @param array $context Context data containing product, order, user objects.
     * @since 2.0.0
     */
    public static function setContext(array $context): void
    {
        self::$contextStack[] = $context;
    }

    /**
     * Pop the most recent context from the stack.
     *
     * If multiple render passes are active, only the innermost one is
     * removed; outer renders retain their context.
     *
     * @since 2.0.0
     */
    public static function clearContext(): void
    {
        array_pop(self::$contextStack);
    }

    /**
     * Check if context injection is currently active.
     *
     * @return bool True if at least one context is on the stack.
     * @since 2.0.0
     */
    public static function isActive(): bool
    {
        if (empty(self::$contextStack)) {
            return false;
        }

        $top = end(self::$contextStack);

        return !empty($top);
    }

    /**
     * Get the current (top-of-stack) context data.
     *
     * @return array|null Current context or null if stack is empty.
     * @since 2.0.0
     */
    public static function getContext(): ?array
    {
        if (empty(self::$contextStack)) {
            return null;
        }

        return end(self::$contextStack) ?: null;
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
        if (!self::isActive()) {
            return $product;
        }

        $context = self::getContext();

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
     * Drains the stack only when it is already empty, so we do not wipe context
     * for an outer render when this hook runs during a nested render (e.g. when
     * a cached/inner render completes and triggers the hook). Each render()
     * balances with clearContext() (pop); we only reset the array when no render
     * is active to avoid leaking context between evaluation cycles.
     *
     * @param array $eligibleNotifications
     * @param array $context
     * @since 2.0.0
     */
    public function clearContextAfterProcessing($eligibleNotifications = [], $context = []): void
    {
        $depth = count(self::$contextStack);
        if ($depth > 0) {
            return;
        }
        self::$contextStack = [];
    }

}
