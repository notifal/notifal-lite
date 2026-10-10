<?php

namespace Notifal\Modules\Forms\Application\Services;

defined('ABSPATH') || exit;

/**
 * Scans template content for connected Form CPT ids (data-form-id).
 *
 * Works on HTML Builder strings and nested Elementor arrays so export
 * can collect every form referenced by a template regardless of builder.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class ConnectedFormContentScanner
{
    /**
     * Collect unique positive Form CPT ids from template content.
     *
     * @since 3.0.0
     * @param mixed $content Template content (HTML string or Elementor array).
     * @return int[] Unique form post IDs greater than zero.
     */
    public function extractFormIds($content): array
    {
        // Gather raw attribute values then normalize to unique positive ints.
        $rawIds = [];
        $this->collectRawIds($content, $rawIds);

        $ids = [];
        foreach ($rawIds as $rawId) {
            $formId = absint($rawId);
            if ($formId > 0 && !in_array($formId, $ids, true)) {
                $ids[] = $formId;
            }
        }

        return $ids;
    }

    /**
     * Remap data-form-id attribute values using an old-id => new-id map.
     *
     * Unknown old ids fall back to 0 so markup stays unbound instead of
     * pointing at a Form CPT that does not exist on the destination site.
     *
     * @since 3.0.0
     * @param mixed            $content Template content (string or array).
     * @param array<int, int>  $idMap   Map of exported form id => imported form id.
     * @return mixed Content with remapped data-form-id values.
     */
    public function remapFormIds($content, array $idMap)
    {
        // Strings (HTML Builder / block HTML) are rewritten via regex.
        if (is_string($content)) {
            return $this->remapIdsInString($content, $idMap);
        }

        // Elementor (and similar) trees are walked recursively.
        if (is_array($content)) {
            $remapped = [];
            foreach ($content as $key => $value) {
                $remapped[$key] = $this->remapFormIds($value, $idMap);
            }

            return $remapped;
        }

        // Non-string scalars are left untouched.
        return $content;
    }

    /**
     * Recursively collect raw data-form-id values from content.
     *
     * @since 3.0.0
     * @param mixed    $content Content node.
     * @param string[] $rawIds  Collected raw attribute values (by ref).
     * @return void
     */
    private function collectRawIds($content, array &$rawIds): void
    {
        if (is_string($content)) {
            // Match data-form-id="123" or data-form-id='123' in HTML fragments.
            if (preg_match_all('/\bdata-form-id\s*=\s*(["\'])(\d+)\1/i', $content, $matches)) {
                foreach ($matches[2] as $matchedId) {
                    $rawIds[] = (string) $matchedId;
                }
            }

            return;
        }

        if (!is_array($content)) {
            return;
        }

        foreach ($content as $value) {
            $this->collectRawIds($value, $rawIds);
        }
    }

    /**
     * Replace data-form-id values inside an HTML string.
     *
     * @since 3.0.0
     * @param string          $html  HTML fragment.
     * @param array<int, int> $idMap Exported id => imported id.
     * @return string Remapped HTML.
     */
    private function remapIdsInString(string $html, array $idMap): string
    {
        // Skip work when there is nothing to rewrite.
        if ($html === '' || strpos($html, 'data-form-id') === false) {
            return $html;
        }

        // Rewrite every data-form-id attribute using the import map.
        $updated = preg_replace_callback(
            '/\bdata-form-id\s*=\s*(["\'])(\d+)\1/i',
            static function (array $matches) use ($idMap): string {
                $quote = $matches[1];
                $oldId = absint($matches[2]);
                // Mapped ids become the new CPT; anything else becomes unbound (0).
                $newId = isset($idMap[$oldId]) ? absint($idMap[$oldId]) : 0;

                return 'data-form-id=' . $quote . (string) $newId . $quote;
            },
            $html
        );

        return is_string($updated) ? $updated : $html;
    }
}
