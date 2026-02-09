<?php

namespace Notifal\Infrastructure\WordPress\Admin\Localization;

use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;

defined('ABSPATH') || exit;

/**
 * Class LangLoader
 *
 * Dynamically loads JS translation strings based on module or infrastructure component namespace.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class LangLoader
{
    /**
     * Loads JS translations for the calling module or infrastructure component.
     *
     * @param string $namespace Full namespace of the calling module or infrastructure component.
     * @param string|null $specificFile Optional specific translation file to load (e.g., 'import.php').
     *                                  If null, loads all translation files in the module/component's Lang/js directory.
     * @return array Array of translation strings.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function load(string $namespace, ?string $specificFile = null): array
    {
        $module = self::extractModuleName($namespace);

        if (!$module) {
            return [];
        }

        $basePath = self::getLangDirectoryPath($module);
        $files = self::getTranslationFiles($basePath, $specificFile);
        $translations = self::loadTranslationFiles($files);

        /**
         * Filters JS translation entries for the current module.
         *
         * Hook name: notifal_{module}_js_translations
         *
         * @param array $translations The translation array.
         * @param string $module The module name.
         * @param string|null $specificFile The specific file being loaded, if any.
         * @since 2.0.0
         * @author Hossein <hossein@notifal.com>
         */
        return apply_filters(
            sprintf(FilterHooks::MODULE_JS_TRANSLATIONS, strtolower($module)),
            $translations,
            $module,
            $specificFile
        );
    }

    /**
     * Extract module or infrastructure component name from namespace.
     *
     * @param string $namespace Full namespace of the calling module or infrastructure component.
     * @return string|null Module/component name or null if not found.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private static function extractModuleName(string $namespace): ?string
    {
        $parts = explode('\\', $namespace);

        // First try to find Modules
        $key = array_search('Modules', $parts, true);
        if ($key !== false && isset($parts[$key + 1])) {
            return $parts[$key + 1];
        }

        // If not found, try to find Infrastructure and extract component path
        $infraKey = array_search('Infrastructure', $parts, true);
        if ($infraKey !== false) {
            // For infrastructure, we need to build the path from Infrastructure onwards
            // Stop at layer directories (Presentation, Infrastructure, Domain, Application, etc.)
            // or implementation directories (Controllers, Services, etc.)
            $stopDirectories = ['Presentation', 'Infrastructure', 'Domain', 'Application', 'Services', 'Resources', 'Controllers', 'Services', 'Assets', 'Views'];

            $pathParts = [];
            for ($i = $infraKey + 1; $i < count($parts); $i++) {
                if (in_array($parts[$i], $stopDirectories, true)) {
                    break;
                }
                $pathParts[] = $parts[$i];
            }

            return implode('/', $pathParts);
        }

        return null;
    }

    /**
     * Get the language directory path for a module or infrastructure component.
     *
     * @param string $module Module name or infrastructure component path.
     * @return string Path to the language directory.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private static function getLangDirectoryPath(string $module): string
    {
        // Check if this is an infrastructure component (contains slashes)
        if (strpos($module, '/') !== false) {
            return NOTIFAL_APP_PATH . 'Infrastructure/' . $module . '/Resources/Lang/js/';
        }

        // Otherwise it's a module
        return NOTIFAL_MODULES_PATH . $module . '/Resources/Lang/js/';
    }

    /**
     * Get translation files to load.
     *
     * @param string $basePath Base path to translation files.
     * @param string|null $specificFile Specific file to load or null for all files.
     * @return array Array of file paths.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private static function getTranslationFiles(string $basePath, ?string $specificFile): array
    {
        if ($specificFile) {
            $filePath = $basePath . $specificFile;
            return file_exists($filePath) ? [$filePath] : [];
        }

        return glob($basePath . '*.php') ?: [];
    }

    /**
     * Load and merge translation files.
     *
     * @param array $files Array of file paths to load.
     * @return array Merged translation array.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private static function loadTranslationFiles(array $files): array
    {
        $translations = [];

        foreach ($files as $file) {
            $data = require $file;
            if (is_array($data)) {
                $translations = array_merge($translations, $data);
            }
        }

        return $translations;
    }
}
