<?php

namespace Notifal\Modules\OnPageNotification\Application\Support;

defined('ABSPATH') || exit;

/**
 * Request-scoped holder for visitor page context during content source resolution.
 *
 * Allows Pro extensions to read the current page context
 * while building filters and pools without changing every service signature.
 *
 * @package Notifal\Modules\OnPageNotification\Application\Support
 * @since 2.3.7
 * @author Hossein <hossein@notifal.com>
 */
class ContentSourceRequestContext
{
    /**
     * Active page context for the current content source resolution pass.
     *
     * @var array<string, mixed>
     */
    private static array $pageContext = [];

    /**
     * Request-scoped ID of the pool entity selected during the latest render pass.
     *
     * @var int
     */
    private static int $lastSelectedPoolEntityId = 0;

    /**
     * Store page context for the current request pass.
     *
     * @param array<string, mixed> $pageContext Visitor page context.
     * @return void
     * @since 2.3.7
     */
    public static function setPageContext(array $pageContext): void
    {
        // Replace the in-memory context for this resolution pass.
        self::$pageContext = $pageContext;
    }

    /**
     * Read the active page context.
     *
     * @return array<string, mixed>
     * @since 2.3.7
     */
    public static function getPageContext(): array
    {
        // Return a copy-safe empty array when nothing is set.
        return self::$pageContext;
    }

    /**
     * Clear stored page context after resolution completes.
     *
     * @return void
     * @since 2.3.7
     */
    public static function reset(): void
    {
        // Drop context to avoid leaking between requests in long-running PHP workers.
        self::$pageContext = [];
    }

    /**
     * Remember the pool entity ID chosen during tag context resolution.
     *
     * @param int $entityId Selected entity ID.
     * @return void
     * @since 2.3.7
     */
    public static function setLastSelectedPoolEntityId(int $entityId): void
    {
        self::$lastSelectedPoolEntityId = max(0, $entityId);
    }

    /**
     * Read the pool entity ID chosen during the latest render pass.
     *
     * @return int
     * @since 2.3.7
     */
    public static function getLastSelectedPoolEntityId(): int
    {
        return self::$lastSelectedPoolEntityId;
    }

    /**
     * Clear the last selected pool entity marker before a new frontend payload build.
     *
     * @return void
     * @since 2.3.7
     */
    public static function resetLastSelectedPoolEntityId(): void
    {
        self::$lastSelectedPoolEntityId = 0;
    }
}
