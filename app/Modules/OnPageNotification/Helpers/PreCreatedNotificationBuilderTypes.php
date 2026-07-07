<?php

namespace Notifal\Modules\OnPageNotification\Helpers;

defined('ABSPATH') || exit;

/**
 * Pre-created notification builder type constants and validation.
 *
 * Centralizes import and template-request builder slugs used by the marketplace integration.
 *
 * @since 2.4.3
 * @author Hossein <hossein@notifal.com>
 */
class PreCreatedNotificationBuilderTypes
{
    /**
     * Elementor builder slug.
     *
     * @since 2.4.3
     * @var string
     */
    public const ELEMENTOR = 'elementor';

    /**
     * Block Editor builder slug.
     *
     * @since 2.4.3
     * @var string
     */
    public const BLOCK_EDITOR = 'block-editor';

    /**
     * HTML Builder slug (matches marketplace download route).
     *
     * @since 2.4.3
     * @var string
     */
    public const HTML_BUILDER = 'html-builder';

    /**
     * All supported import / request builder slugs.
     *
     * @since 2.4.3
     * @return string[]
     */
    public static function getImportFileTypes(): array
    {
        // Return a fresh array so callers cannot mutate class constants.
        return [
            self::ELEMENTOR,
            self::BLOCK_EDITOR,
            self::HTML_BUILDER,
        ];
    }

    /**
     * Whether the given slug is a valid import or template-request builder type.
     *
     * @since 2.4.3
     * @param string $builderType Builder slug from API or POST.
     * @return bool
     */
    public static function isValidImportFileType(string $builderType): bool
    {
        // Reject empty strings before comparing against the allowlist.
        if ($builderType === '') {
            return false;
        }

        return in_array($builderType, self::getImportFileTypes(), true);
    }
}
