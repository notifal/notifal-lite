<?php

namespace Notifal\Modules\Forms\Application\Services;

use Notifal\Modules\Forms\Config\AttributeContract;

defined('ABSPATH') || exit;

/**
 * Removes connected Notifal forms whose Form CPT no longer exists.
 *
 * Templates may keep a stale data-form-id after a Form post is deleted or
 * trashed. This guard strips those roots so builder and frontend never show
 * a connected form that cannot accept submissions.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class ConnectedFormMarkupGuard
{
    /**
     * Root wrapper id used when parsing HTML fragments.
     *
     * @var string
     */
    private const FRAGMENT_ROOT_ID = 'notifal-missing-form-root';

    /**
     * Per-request existence cache keyed by form id.
     *
     * @var array<int, bool>
     */
    private $existenceCache = [];

    /**
     * Whether a Form CPT id is usable as a template connection.
     *
     * @param int $formId Form CPT id from data-form-id.
     * @return bool True when the post exists, is notifal_form, and is not trash.
     * @since 3.0.0
     */
    public function formExists(int $formId): bool
    {
        // Unbound widgets use 0 and are intentionally kept.
        if ($formId <= 0) {
            return false;
        }

        // Reuse the same answer within one HTML pass.
        if (array_key_exists($formId, $this->existenceCache)) {
            return $this->existenceCache[$formId];
        }

        $post = get_post($formId);
        if (!$post instanceof \WP_Post) {
            $this->existenceCache[$formId] = false;
            return false;
        }

        // Wrong post type or soft-deleted Form cannot stay connected.
        if ($post->post_type !== 'notifal_form') {
            $this->existenceCache[$formId] = false;
            return false;
        }

        if ($post->post_status === 'trash' || $post->post_status === 'auto-draft') {
            $this->existenceCache[$formId] = false;
            return false;
        }

        $this->existenceCache[$formId] = true;

        return true;
    }

    /**
     * Strip every .notifal-form root whose data-form-id points at a missing Form.
     *
     * Unbound roots (data-form-id empty or 0) are left untouched so merchants
     * can still connect them in the builder.
     *
     * @param string $html Template HTML document or fragment.
     * @return array{html: string, removed_ids: int[]} Cleaned markup and removed ids.
     * @since 3.0.0
     */
    public function removeMissingConnectedForms(string $html): array
    {
        // Fast path when markup has no Notifal form roots.
        if ($html === '' || strpos($html, AttributeContract::CLASS_FORM) === false) {
            return [
                'html'         => $html,
                'removed_ids'  => [],
            ];
        }

        $parsed = $this->createDocument($html);
        if ($parsed === null) {
            return [
                'html'         => $html,
                'removed_ids'  => [],
            ];
        }

        $document = $parsed['document'];
        $xpath = new \DOMXPath($document);
        $forms = $xpath->query(
            '//*[contains(concat(" ", normalize-space(@class), " "), " '
            . AttributeContract::CLASS_FORM
            . ' ")]'
        );

        if (!$forms || $forms->length === 0) {
            return [
                'html'         => $html,
                'removed_ids'  => [],
            ];
        }

        // Collect first so live NodeList mutations stay safe.
        $toRemove = [];
        foreach ($forms as $form) {
            if (!$form instanceof \DOMElement) {
                continue;
            }

            $formId = absint($form->getAttribute('data-form-id'));
            // Keep unbound placeholders for the connect UX.
            if ($formId <= 0) {
                continue;
            }

            // Keep live Form CPT connections.
            if ($this->formExists($formId)) {
                continue;
            }

            $toRemove[] = [
                'node' => $form,
                'id'   => $formId,
            ];
        }

        if ($toRemove === []) {
            return [
                'html'         => $html,
                'removed_ids'  => [],
            ];
        }

        $removedIds = [];
        foreach ($toRemove as $item) {
            $node = $item['node'];
            if ($node instanceof \DOMElement && $node->parentNode) {
                $node->parentNode->removeChild($node);
            }
            $removedIds[] = (int) $item['id'];
        }

        return [
            'html'         => $this->serializeDocument($document, $parsed['wrapped']),
            'removed_ids'  => array_values(array_unique($removedIds)),
        ];
    }

    /**
     * Parse HTML as a full document or a fragment wrapper.
     *
     * @param string $html Raw HTML.
     * @return array{document: \DOMDocument, wrapped: bool}|null
     * @since 3.0.0
     */
    private function createDocument(string $html): ?array
    {
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $hasHtmlTag = (bool) preg_match('/<\s*html[\s>]/i', $html);
        $wrapped = !$hasHtmlTag;

        if ($hasHtmlTag) {
            // Preserve full builder documents (html/head/body).
            $loaded = $document->loadHTML(
                '<?xml encoding="utf-8" ?>' . $html,
                LIBXML_HTML_NODEFDTD
            );
        } else {
            // Wrap fragments the same way other Forms DOM helpers do.
            $loaded = $document->loadHTML(
                '<?xml encoding="utf-8" ?><html><body><div id="'
                . self::FRAGMENT_ROOT_ID
                . '">'
                . $html
                . '</div></body></html>',
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
            );
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            return null;
        }

        return [
            'document' => $document,
            'wrapped'  => $wrapped,
        ];
    }

    /**
     * Serialize the mutated document back to an HTML string.
     *
     * @param \DOMDocument $document Parsed document.
     * @param bool         $wrapped  Whether a fragment root was used.
     * @return string
     * @since 3.0.0
     */
    private function serializeDocument(\DOMDocument $document, bool $wrapped): string
    {
        if (!$wrapped) {
            $html = (string) $document->saveHTML();
            // Drop the encoding PI injected for UTF-8 safety.
            $html = preg_replace('/<\?xml[^>]*>\s*/i', '', $html);

            return is_string($html) ? $html : '';
        }

        $xpath = new \DOMXPath($document);
        $roots = $xpath->query('//*[@id="' . self::FRAGMENT_ROOT_ID . '"]');
        $root = ($roots && $roots->length > 0) ? $roots->item(0) : null;
        if (!$root instanceof \DOMElement) {
            return '';
        }

        $html = '';
        foreach ($root->childNodes as $child) {
            $html .= $document->saveHTML($child);
        }

        return $html;
    }
}
