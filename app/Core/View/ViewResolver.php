<?php

namespace Notifal\Core\View;

defined('ABSPATH') || exit;

/**
 * Class ViewResolver
 *
 * Resolves the path for module views in Presentation/{Context}/Views/.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class ViewResolver
{
    /**
     * Resolve the full path to a view file.
     *
     * @param string $view Dot notation path (e.g., "Templates.Admin.partials.header")
     * @return string
     * @throws \InvalidArgumentException| \RuntimeException
     */
    public function resolve(string $view): string
    {
        $parts = explode('.', $view);

        if (count($parts) < 3) {
            throw new \InvalidArgumentException(
                sprintf(
                /* translators: %s: view path example */
                    esc_html__('Invalid view path "%s". Expected format: Module.Context.View', 'notifal'),
                    esc_html($view)
                )
            );
        }

        $module   = $parts[0];                // e.g., Templates
        $context  = ucfirst($parts[1]);       // e.g., Admin or Frontend
        $viewPath = implode('/', array_slice($parts, 2)) . '.php';

        $moduleBase = NOTIFAL_PATH . "app/Modules/{$module}/Presentation/{$context}/Views/";
        $fullPath   = $moduleBase . ltrim($viewPath, '/');

        if (file_exists($fullPath)) {
            return $fullPath;
        }

        throw new \RuntimeException(
            sprintf(
            /* translators: %s: full path to view file */
                esc_html__('View file not found at path: %s', 'notifal'),
                esc_html($fullPath)
            )
        );
    }
}
