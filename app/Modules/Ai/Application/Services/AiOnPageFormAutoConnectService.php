<?php

namespace Notifal\Modules\Ai\Application\Services;

use Notifal\Domain\Settings\Services\SettingsService;
use Notifal\Modules\Forms\Application\Services\FormFieldSanitizer;
use Notifal\Modules\Forms\Application\Services\FormLicenseGuard;
use Notifal\Modules\Forms\Application\Services\FormPresentationSettings;
use Notifal\Modules\Forms\Application\Services\FormService;
use Notifal\Modules\Forms\Application\Services\FormsSettingsService;
use Notifal\Modules\Forms\Application\Services\HtmlSchemaParser;
use Notifal\Modules\Forms\Config\AttributeContract;
use Notifal\Modules\Forms\Infrastructure\WordPress\Repositories\FormFieldsRepository;
use Notifal\Modules\Forms\Infrastructure\WordPress\Repositories\FormMetaRepository;

defined('ABSPATH') || exit;

/**
 * Creates Form CPTs for unbound AI forms and rewrites data-form-id before OnPage import.
 *
 * Mirrors HTML Builder autoConnectUnboundFormsAfterAi for Setup Wizard and OnPage AI modal.
 *
 * @since 3.0.0
 * @updated 3.0.0 Fill empty AI form shells with email + submit markup before CPT bind.
 * @updated 3.0.0 Inject a complete Notifal form when signup copy exists without .notifal-form.
 * @updated 3.0.0 Reuse the free-tier Form CPT when create is blocked so data-form-id stays > 0.
 * @updated 3.0.0 Detect capture for inject via markup structure only (language-agnostic).
 * @author Hossein <hossein@notifal.com>
 */
class AiOnPageFormAutoConnectService
{
    /**
     * Bind every unbound .notifal-form in template HTML to a Form CPT.
     *
     * Creates a new Form when the license allows it. When the free-tier limit
     * blocks create, reuses the existing Form CPT and rewrites data-form-id so
     * the builder never keeps an unbound "Select a form first" shell.
     *
     * @since 3.0.0
     * @updated 3.0.0 Inject capture form markup when signup chrome exists without a form root.
     * @updated 3.0.0 Reuse existing Form CPT when FormLicenseGuard::canCreate() is false.
     * @param string $html            Sanitized template HTML.
     * @param string $fallbackTitle   Title used when markup has no nearby label.
     * @return array{html: string, connected: int, reused: int, skipped: int, failed: int, form_ids: int[], injected: bool, skip_reason: string}
     */
    public function connectUnboundForms(string $html, string $fallbackTitle = ''): array
    {
        // Default result keeps the original HTML when nothing can be bound.
        $result = [
            'html'        => $html,
            'connected'   => 0,
            'reused'      => 0,
            'skipped'     => 0,
            'failed'      => 0,
            'form_ids'    => [],
            'injected'    => false,
            'skip_reason' => '',
        ];

        // Skip empty fragments early.
        if ($html === '') {
            return $result;
        }

        // When signup copy exists without a Notifal form root, inject one before bind.
        $prepared = $this->ensureCaptureFormPresent($html);
        $result['injected'] = $prepared['injected'];
        $html = $prepared['html'];
        $result['html'] = $html;

        // Skip when the fragment still has no form roots.
        if (strpos($html, AttributeContract::CLASS_FORM) === false) {
            return $result;
        }

        // Forms module must be enabled and registered for CPT creates.
        if (!$this->isFormsModuleEnabled()) {
            $result['skip_reason'] = 'forms_disabled';

            return $result;
        }

        // Match HTML Builder create capability: editors who can import may bind forms.
        if (!current_user_can('edit_posts')) {
            $result['skip_reason'] = 'capability';

            return $result;
        }

        // Parse the full template so every unbound form root can be rewritten.
        $document = $this->createDocument($html);
        if (!$document instanceof \DOMDocument) {
            $result['skip_reason'] = 'dom_parse';

            return $result;
        }

        $xpath = new \DOMXPath($document);
        $formNodes = $xpath->query(
            '//*[contains(concat(" ", normalize-space(@class), " "), " '
            . AttributeContract::CLASS_FORM
            . ' ")]'
        );

        // Nothing to bind when the class match was a false positive.
        if (!$formNodes || $formNodes->length === 0) {
            return $result;
        }

        $parser = new HtmlSchemaParser();
        $formService = $this->buildFormService();
        $titleBase = sanitize_text_field($fallbackTitle);
        if ($titleBase === '') {
            $titleBase = __('Newsletter signup', 'notifal');
        }

        // Resolve once: free-tier reuse target when another Form CPT cannot be created.
        $reuseFormId = 0;
        $reuseFieldsSynced = false;

        // Process forms in document order so license limits stay predictable.
        $formIndex = 0;
        foreach ($formNodes as $formNode) {
            if (!$formNode instanceof \DOMElement) {
                continue;
            }

            // Already-connected forms keep their merchant-selected CPT id.
            $existingId = absint($formNode->getAttribute('data-form-id'));
            if ($existingId > 0) {
                continue;
            }

            // Empty AI form shells get a real email + submit so preview is not a placeholder.
            $this->ensureMinimumLeadCaptureMarkup($document, $formNode);

            // Parse fields/presentation from this form's outer markup only.
            $outerHtml = $this->outerHtml($document, $formNode);
            $schema = $parser->parse($outerHtml);
            $fields = is_array($schema['fields'] ?? null) ? $schema['fields'] : [];
            if ($fields === []) {
                $fields = $this->getDefaultFields();
            }

            $presentation = FormPresentationSettings::fromSchema(is_array($schema) ? $schema : []);
            $title = $this->deriveFormTitle($formNode, $titleBase, $formIndex);

            // Prefer create; when the free limit blocks create, reuse the existing Form CPT.
            $saveFormId = 0;
            $isReuse = false;
            if (!FormLicenseGuard::canCreate()) {
                if ($reuseFormId <= 0) {
                    $reuseFormId = FormLicenseGuard::getReusableFormId();
                }
                if ($reuseFormId <= 0) {
                    $result['skipped']++;
                    $result['skip_reason'] = 'license_limit_no_reuse';
                    continue;
                }
                $saveFormId = $reuseFormId;
                $isReuse = true;
            }

            // Additional unbound roots on free tier only need the shared id bound.
            if ($isReuse && $reuseFieldsSynced) {
                $formNode->setAttribute('data-form-id', (string) $reuseFormId);
                $result['connected']++;
                $result['reused']++;
                if (!in_array($reuseFormId, $result['form_ids'], true)) {
                    $result['form_ids'][] = $reuseFormId;
                }
                $formIndex++;
                continue;
            }

            try {
                // Trusted import path: mint a save nonce for the current admin user.
                $formId = $formService->save(
                    array_merge(
                        [
                            'nonce'       => wp_create_nonce('notifal_form_save'),
                            'form_id'     => $saveFormId,
                            'form_title'  => $title,
                            'form_fields' => $fields,
                        ],
                        $presentation
                    )
                );
            } catch (\Throwable $exception) {
                $result['failed']++;
                if ($result['skip_reason'] === '') {
                    $result['skip_reason'] = 'save_exception';
                }
                continue;
            }

            // Bind the live template markup to the created or reused Form CPT.
            if ($formId > 0) {
                $formNode->setAttribute('data-form-id', (string) $formId);
                $result['connected']++;
                if (!in_array($formId, $result['form_ids'], true)) {
                    $result['form_ids'][] = $formId;
                }
                if ($isReuse) {
                    $result['reused']++;
                    $reuseFormId = $formId;
                    $reuseFieldsSynced = true;
                }
            } else {
                $result['failed']++;
                if ($result['skip_reason'] === '') {
                    $result['skip_reason'] = 'save_returned_zero';
                }
            }

            $formIndex++;
        }

        // Serialize the updated fragment back to HTML for the importer.
        $result['html'] = $this->serializeBodyInnerHtml($document);

        return $result;
    }

    /**
     * Inject a complete Notifal form when capture markup exists without a form root.
     *
     * @since 3.0.0
     * @updated 3.0.0 Rely on markup signals only (no English copy lists).
     * @param string $html Template HTML fragment.
     * @return array{html: string, injected: bool}
     */
    private function ensureCaptureFormPresent(string $html): array
    {
        // Already has a form root: leave placement to the AI / prior bind path.
        if (strpos($html, AttributeContract::CLASS_FORM) !== false) {
            return [
                'html'     => $html,
                'injected' => false,
            ];
        }

        // Only inject when structural capture chrome is already present without .notifal-form.
        if (!$this->htmlImpliesLeadCapture($html)) {
            return [
                'html'     => $html,
                'injected' => false,
            ];
        }

        // Append or place a complete unbound form so CPT creation and data-form-id rewrite can run.
        $formHtml = $this->buildMinimumLeadCaptureFormHtml();

        // Prefer inserting before a trailing decorative aside so the form stays in the content column.
        if (preg_match('/<aside\b/i', $html)) {
            $updated = preg_replace('/(<aside\b)/i', $formHtml . '$1', $html, 1);
            $html = is_string($updated) ? $updated : ($html . $formHtml);
        } else {
            $html .= $formHtml;
        }

        return [
            'html'     => $html,
            'injected' => true,
        ];
    }

    /**
     * Whether template HTML implies lead capture via language-agnostic markup.
     *
     * Copy-only signup phrases are intentionally ignored: gateway Pass 2 validation
     * enforces complete forms from structured collect_leads / use_case signals.
     *
     * @since 3.0.0
     * @updated 3.0.0 Markup-first only (email inputs, forms, mailto); no English needles.
     * @param string $html Template HTML.
     * @return bool
     */
    private function htmlImpliesLeadCapture(string $html): bool
    {
        if ($html === '') {
            return false;
        }

        // Native form tags already model capture UI.
        if (preg_match('/<form\b/i', $html)) {
            return true;
        }

        // Email field chrome (type, Forms data attribute, or name=email).
        if (preg_match('/type\s*=\s*(["\'])email\1/i', $html)) {
            return true;
        }
        if (preg_match('/data-field-type\s*=\s*(["\'])email\1/i', $html)) {
            return true;
        }
        if (preg_match('/<input\b[^>]*name\s*=\s*(["\'])email\1/i', $html)) {
            return true;
        }

        return false;
    }

    /**
     * Build a complete unbound Notifal form fragment for AI import injection.
     *
     * @since 3.0.0
     * @return string Safe HTML fragment (no scripts).
     */
    private function buildMinimumLeadCaptureFormHtml(): string
    {
        $success = esc_attr(__('Thanks! You are on the list.', 'notifal'));
        $error = esc_attr(__('Something went wrong. Please try again.', 'notifal'));
        $emailLabel = esc_html__('Email', 'notifal');
        $submitLabel = esc_html__('Get Early Access', 'notifal');

        return '<form class="notifal-form" data-notifal-widget="form" data-form-id="0"'
            . ' data-form-multi-step="false" data-success-behavior="message"'
            . ' data-success-message="' . $success . '"'
            . ' data-error-message="' . $error . '"'
            . ' data-primary-color="#386FA4" data-submitter-email-field="email" data-captcha="inherit">'
            . '<label class="notifal-form-field-wrap">'
            . '<span class="notifal-form-label">' . $emailLabel . '</span>'
            . '<input class="notifal-form-field" type="email" name="email" placeholder="you@example.com"'
            . ' data-field-type="email" data-field-name="email" data-label="Email" data-required="true" />'
            . '</label>'
            . '<button type="submit" class="notifal-form-nav" data-form-action="submit">'
            . $submitLabel
            . '</button>'
            . '<div class="notifal-form-success" data-form-result="success" hidden>'
            . '<p class="notifal-form-success-message">' . esc_html(__('Thanks! You are on the list.', 'notifal')) . '</p>'
            . '</div>'
            . '<div class="notifal-form-error" data-form-result="failed" hidden role="alert">'
            . '<p class="notifal-form-error-message">' . esc_html(__('Something went wrong. Please try again.', 'notifal')) . '</p>'
            . '</div>'
            . '</form>';
    }

    /**
     * Whether the Forms addon kill switch is on.
     *
     * @since 3.0.0
     * @return bool
     */
    private function isFormsModuleEnabled(): bool
    {
        // Resolve settings through the shared container when available.
        if (!function_exists('notifal_app')) {
            return true;
        }

        try {
            $settings = notifal_app(SettingsService::class);
            $formsSettings = new FormsSettingsService($settings);

            return $formsSettings->isEnabled();
        } catch (\Throwable $exception) {
            // Fail open only when settings cannot be resolved; CPT create still guards itself.
            return true;
        }
    }

    /**
     * Build FormService with the same repositories as admin AJAX.
     *
     * @since 3.0.0
     * @return FormService
     */
    private function buildFormService(): FormService
    {
        $sanitizer = new FormFieldSanitizer();
        $fields = new FormFieldsRepository($sanitizer);

        return new FormService(new FormMetaRepository(), $fields);
    }

    /**
     * Inject email + submit markup when AI left an empty .notifal-form shell.
     *
     * Prevents the builder "Select a form first" placeholder when fields are missing
     * after import. Existing field controls are left untouched.
     *
     * @since 3.0.0
     * @param \DOMDocument $document Owner document.
     * @param \DOMElement  $formNode Form root element.
     * @return void
     */
    private function ensureMinimumLeadCaptureMarkup(\DOMDocument $document, \DOMElement $formNode): void
    {
        // Skip when the AI already authored at least one field control.
        $xpath = new \DOMXPath($document);
        $fields = $xpath->query(
            './/*[contains(concat(" ", normalize-space(@class), " "), " notifal-form-field ")]',
            $formNode
        );
        if ($fields && $fields->length > 0) {
            return;
        }

        // Also treat native email inputs inside the form as sufficient markup.
        $nativeEmail = $xpath->query('.//input[@type="email"]', $formNode);
        if ($nativeEmail && $nativeEmail->length > 0) {
            return;
        }

        // Ensure presentation attributes exist so the Forms runtime can bootstrap.
        if ('' === trim((string) $formNode->getAttribute('data-notifal-widget'))) {
            $formNode->setAttribute('data-notifal-widget', 'form');
        }
        if ('' === trim((string) $formNode->getAttribute('data-form-id'))) {
            $formNode->setAttribute('data-form-id', '0');
        }
        if ('' === trim((string) $formNode->getAttribute('data-submitter-email-field'))) {
            $formNode->setAttribute('data-submitter-email-field', 'email');
        }
        if ('' === trim((string) $formNode->getAttribute('data-success-behavior'))) {
            $formNode->setAttribute('data-success-behavior', 'message');
        }
        if ('' === trim((string) $formNode->getAttribute('data-success-message'))) {
            $formNode->setAttribute(
                'data-success-message',
                __('Thanks! You are on the list.', 'notifal')
            );
        }

        // Clear placeholder-only text nodes before appending real controls.
        while ($formNode->firstChild) {
            $formNode->removeChild($formNode->firstChild);
        }

        // Email field wrap.
        $emailWrap = $document->createElement('label');
        $emailWrap->setAttribute('class', 'notifal-form-field-wrap');

        $emailLabel = $document->createElement('span', __('Email', 'notifal'));
        $emailLabel->setAttribute('class', 'notifal-form-label');
        $emailWrap->appendChild($emailLabel);

        $emailInput = $document->createElement('input');
        $emailInput->setAttribute('class', 'notifal-form-field');
        $emailInput->setAttribute('type', 'email');
        $emailInput->setAttribute('name', 'email');
        $emailInput->setAttribute('placeholder', 'you@example.com');
        $emailInput->setAttribute('data-field-type', 'email');
        $emailInput->setAttribute('data-field-name', 'email');
        $emailInput->setAttribute('data-label', 'Email');
        $emailInput->setAttribute('data-required', 'true');
        $emailWrap->appendChild($emailInput);
        $formNode->appendChild($emailWrap);

        // Submit CTA matching Forms starter markup conventions.
        $submit = $document->createElement('button', __('Get Early Access', 'notifal'));
        $submit->setAttribute('type', 'submit');
        $submit->setAttribute('class', 'notifal-form-nav');
        $submit->setAttribute('data-form-action', 'submit');
        $formNode->appendChild($submit);
    }

    /**
     * Default Name + Email fields when AI markup has no parseable controls.
     *
     * @since 3.0.0
     * @return array<int, array<string, mixed>>
     */
    private function getDefaultFields(): array
    {
        return [
            [
                'name'     => 'name',
                'type'     => 'text',
                'label'    => __('Name', 'notifal'),
                'required' => true,
            ],
            [
                'name'     => 'email',
                'type'     => 'email',
                'label'    => __('Email', 'notifal'),
                'required' => true,
            ],
        ];
    }

    /**
     * Derive an admin-friendly Form CPT title from markup or fallback.
     *
     * @since 3.0.0
     * @param \DOMElement $formNode Form root element.
     * @param string      $base     Notification / fallback title.
     * @param int         $index    Zero-based unbound form index.
     * @return string
     */
    private function deriveFormTitle(\DOMElement $formNode, string $base, int $index): string
    {
        // Prefer an explicit submit label when AI authored one.
        $owner = $formNode->ownerDocument;
        if ($owner instanceof \DOMDocument) {
            $xpath = new \DOMXPath($owner);
            $submit = $xpath->query(
                './/*[@data-form-action="submit"] | .//button[@type="submit"]',
                $formNode
            );
            if ($submit && $submit->length > 0 && $submit->item(0) instanceof \DOMElement) {
                $label = sanitize_text_field(trim((string) $submit->item(0)->textContent));
                if ($label !== '') {
                    $title = sprintf(
                        /* translators: %s: submit button label */
                        __('Form: %s', 'notifal'),
                        $label
                    );

                    return $this->boundedTitle($title, $index);
                }
            }
        }

        // Fall back to the notification title with an optional index suffix.
        return $this->boundedTitle($base, $index);
    }

    /**
     * Keep Form CPT titles within a sensible admin length.
     *
     * @since 3.0.0
     * @param string $title Raw title.
     * @param int    $index Zero-based index for multi-form templates.
     * @return string
     */
    private function boundedTitle(string $title, int $index): string
    {
        $clean = sanitize_text_field($title);
        if ($clean === '') {
            $clean = __('Newsletter signup', 'notifal');
        }
        if ($index > 0) {
            $clean .= ' (' . (string) ($index + 1) . ')';
        }

        return function_exists('mb_substr')
            ? mb_substr($clean, 0, 120)
            : substr($clean, 0, 120);
    }

    /**
     * Load HTML into a DOMDocument for form rewriting.
     *
     * @since 3.0.0
     * @param string $html Template HTML fragment.
     * @return \DOMDocument|null
     */
    private function createDocument(string $html): ?\DOMDocument
    {
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML(
            '<?xml encoding="utf-8" ?><html><body><div id="notifal-ai-form-bind-root">'
            . $html
            . '</div></body></html>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $loaded ? $document : null;
    }

    /**
     * Serialize a single element's outer HTML.
     *
     * @since 3.0.0
     * @param \DOMDocument $document Owner document.
     * @param \DOMElement  $element  Target element.
     * @return string
     */
    private function outerHtml(\DOMDocument $document, \DOMElement $element): string
    {
        return (string) $document->saveHTML($element);
    }

    /**
     * Return the inner HTML of the bind root after rewrites.
     *
     * @since 3.0.0
     * @param \DOMDocument $document Parsed document.
     * @return string
     */
    private function serializeBodyInnerHtml(\DOMDocument $document): string
    {
        // Prefer XPath because getElementById is unreliable without a DTD.
        $xpath = new \DOMXPath($document);
        $roots = $xpath->query('//*[@id="notifal-ai-form-bind-root"]');
        $root = ($roots && $roots->length > 0) ? $roots->item(0) : null;
        if (!$root instanceof \DOMElement) {
            return '';
        }

        $html = '';
        foreach ($root->childNodes as $child) {
            $html .= (string) $document->saveHTML($child);
        }

        return $html;
    }
}
