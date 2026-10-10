<?php

namespace Notifal\Modules\Forms\Application\Services;

use Notifal\Modules\Forms\Application\Services\Security\RedirectAllowlist;
use Notifal\Modules\Forms\Application\Services\Security\SchemaTokenService;
use Notifal\Modules\Forms\Config\AttributeContract;
use Notifal\Modules\Forms\Infrastructure\WordPress\Repositories\FormFieldsRepository;
use Notifal\Modules\Forms\Infrastructure\WordPress\Repositories\FormMetaRepository;

defined('ABSPATH') || exit;

/**
 * Hydrates Notifal form markup at render time (nonce/honeypot/captcha/schema token).
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class FormRenderProcessor
{
    /**
     * @var HtmlSchemaParser
     */
    private $parser;

    /**
     * @var SchemaTokenService
     */
    private $tokens;

    /**
     * @var FormsSettingsService
     */
    private $settings;

    /**
     * @var FormFieldsRepository
     */
    private $formFields;

    /**
     * Form CPT meta (presentation, including per-form captcha).
     *
     * @var FormMetaRepository
     */
    private $formMeta;

    /**
     * @param HtmlSchemaParser          $parser     Parser.
     * @param SchemaTokenService        $tokens     Tokens.
     * @param FormsSettingsService      $settings   Settings.
     * @param FormFieldsRepository|null $formFields Optional CPT field definitions.
     * @param FormMetaRepository|null   $formMeta   Optional CPT presentation meta.
     * @since 3.0.0
     */
    public function __construct(
        HtmlSchemaParser $parser,
        SchemaTokenService $tokens,
        FormsSettingsService $settings,
        ?FormFieldsRepository $formFields = null,
        ?FormMetaRepository $formMeta = null
    ) {
        $this->parser = $parser;
        $this->tokens = $tokens;
        $this->settings = $settings;
        // Resolve lazily when the container does not inject the repository.
        $this->formFields = $formFields instanceof FormFieldsRepository
            ? $formFields
            : new FormFieldsRepository(new FormFieldSanitizer());
        // Presentation (captcha skip) lives on Form CPT meta.
        $this->formMeta = $formMeta instanceof FormMetaRepository
            ? $formMeta
            : new FormMetaRepository();
    }

    /**
     * Process all Notifal forms in rendered HTML.
     *
     * @param string $html             Template HTML.
     * @param array  $frontendContext  Renderer context (may include notification_id).
     * @return string
     * @since 3.0.0
     * @updated 3.0.0 Strip connected forms whose Form CPT was deleted before hydrate.
     */
    public function process(string $html, array $frontendContext = []): string
    {
        if (!$this->settings->isEnabled()) {
            return $html;
        }

        if ($html === '' || strpos($html, AttributeContract::CLASS_FORM) === false) {
            return $html;
        }

        // Drop stale connections so visitors never see a Form that no longer exists.
        $guard = new ConnectedFormMarkupGuard();
        $cleaned = $guard->removeMissingConnectedForms($html);
        $html = $cleaned['html'];

        // Bail when every form root was removed as missing.
        if ($html === '' || strpos($html, AttributeContract::CLASS_FORM) === false) {
            return $html;
        }

        $document = $this->createDocument($html);
        if (!$document) {
            return $html;
        }

        $xpath = new \DOMXPath($document);
        $forms = $xpath->query(
            '//*[contains(concat(" ", normalize-space(@class), " "), " '
            . AttributeContract::CLASS_FORM
            . ' ")]'
        );

        if (!$forms || $forms->length === 0) {
            return $html;
        }

        $notificationId = isset($frontendContext['notification_id'])
            ? absint($frontendContext['notification_id'])
            : 0;

        foreach ($forms as $form) {
            if (!$form instanceof \DOMElement) {
                continue;
            }

            $this->hydrateForm($document, $form, $notificationId);
        }

        $root = $document->getElementById('notifal-form-render-root');
        if (!$root) {
            return $html;
        }

        return $this->innerHtml($document, $root);
    }

    /**
     * Hydrate a single form element.
     *
     * @param \DOMDocument $document        Document.
     * @param \DOMElement  $form            Form element.
     * @param int          $notificationId  Notification ID.
     * @return void
     */
    private function hydrateForm(\DOMDocument $document, \DOMElement $form, int $notificationId): void
    {
        // Force AJAX-only submit.
        if (strtolower($form->tagName) === 'form') {
            $form->setAttribute('method', 'post');
            $form->setAttribute('action', '#');
            $form->setAttribute('novalidate', 'novalidate');
        }

        // Strip forbidden secret-like attributes if present.
        foreach (iterator_to_array($form->attributes ?? []) as $attr) {
            if ($attr && AttributeContract::isForbiddenAttribute($attr->name)) {
                $form->removeAttribute($attr->name);
            }
        }

        $redirect = RedirectAllowlist::sanitize($form->getAttribute('data-success-redirect'));
        if ($form->hasAttribute('data-success-redirect')) {
            if ($redirect === '') {
                $form->removeAttribute('data-success-redirect');
            } else {
                $form->setAttribute('data-success-redirect', $redirect);
            }
        }

        $formId = absint($form->getAttribute('data-form-id'));
        $form->setAttribute('data-notification-id', (string) $notificationId);

        // Mirror CPT field rules onto markup before schema fingerprinting.
        $this->applyUniqueFlagsFromCpt($form, $formId);
        $this->applyConditionalAttributesFromCpt($form, $formId);
        // CPT Basics captcha (skip / inherit) overrides stale template data-captcha.
        $this->applyCaptchaModeFromCpt($form, $formId);

        // Apply brand accent from data-primary-color onto the CSS variable.
        $this->applyPrimaryColor($form);

        // Build schema from current form subtree HTML.
        $fragment = $form->ownerDocument->saveHTML($form);
        $schema = $this->parser->parse((string) $fragment);
        if (!$schema) {
            return;
        }

        $schema['form_id'] = $formId;
        $fingerprint = $this->parser->fingerprint($schema);
        $token = $this->tokens->create($formId, $notificationId, $fingerprint);
        $form->setAttribute('data-schema-token', $token);

        // Store signed schema snapshot as a hidden JSON field for submit authority.
        $schemaInput = $document->createElement('input');
        $schemaInput->setAttribute('type', 'hidden');
        $schemaInput->setAttribute('name', 'notifal_form_schema');
        $schemaInput->setAttribute('class', 'notifal-form-schema');
        $schemaInput->setAttribute('value', wp_json_encode($schema));
        $form->appendChild($schemaInput);

        // WordPress nonce field.
        $nonce = $document->createElement('input');
        $nonce->setAttribute('type', 'hidden');
        $nonce->setAttribute('name', 'notifal_form_nonce');
        $nonce->setAttribute('class', 'notifal-form-nonce');
        $nonce->setAttribute('value', wp_create_nonce('notifal_form_submit'));
        $form->appendChild($nonce);

        // Honeypot (visually hidden via class).
        if ($this->settings->isHoneypotEnabled()) {
            $hpWrap = $document->createElement('div');
            $hpWrap->setAttribute('class', 'notifal-form-honeypot notifal-hidden');
            $hpWrap->setAttribute('aria-hidden', 'true');
            $hp = $document->createElement('input');
            $hp->setAttribute('type', 'text');
            $hp->setAttribute('name', 'notifal_hp_website');
            $hp->setAttribute('class', 'notifal-form-honeypot-field');
            $hp->setAttribute('tabindex', '-1');
            $hp->setAttribute('autocomplete', 'off');
            $hpWrap->appendChild($hp);
            $form->appendChild($hpWrap);
        }

        // Captcha mount when settings require it (widget rendered by frontend JS).
        $mode = $form->getAttribute('data-captcha') !== ''
            ? $form->getAttribute('data-captcha')
            : 'inherit';
        if ($this->settings->isCaptchaRequired($mode)) {
            // Empty mount: frontend calls grecaptcha.render / turnstile.render into this node.
            $captcha = $document->createElement('div');
            $captcha->setAttribute('class', 'notifal-form-captcha');
            $captcha->setAttribute('data-captcha-provider', $this->settings->getCaptchaProvider());
            $captcha->setAttribute('data-captcha-sitekey', $this->settings->getCaptchaSiteKey());
            // Place before submit so the checkbox appears above the CTA.
            $this->insertCaptchaMount($form, $captcha);
        }
    }

    /**
     * Insert the captcha mount before the primary submit control when possible.
     *
     * @param \DOMElement $form    Form root.
     * @param \DOMElement $captcha Captcha mount element.
     * @return void
     * @since 3.0.0
     */
    private function insertCaptchaMount(\DOMElement $form, \DOMElement $captcha): void
    {
        // Prefer the Notifal submit control so multi-step last-step CTAs stay correct.
        $submit = null;
        $candidates = $form->getElementsByTagName('*');

        foreach ($candidates as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            // Match explicit Notifal submit actions first.
            if ($node->getAttribute('data-form-action') === 'submit') {
                $submit = $node;
                break;
            }

            // Fallback: native submit buttons / inputs.
            $type = strtolower($node->getAttribute('type'));
            if ($type === 'submit') {
                $submit = $node;
                break;
            }
        }

        // Insert immediately before the submit control when a parent exists.
        if ($submit instanceof \DOMElement && $submit->parentNode) {
            $submit->parentNode->insertBefore($captcha, $submit);
            return;
        }

        // Last resort: append at the end of the form root.
        $form->appendChild($captcha);
    }

    /**
     * Apply Form CPT captcha presentation onto the form root before schema signing.
     *
     * Form admin and builder both store captcha on the CPT. Template HTML can lag
     * behind, so the CPT value is authoritative for connected forms.
     *
     * @param \DOMElement $form   Form root.
     * @param int         $formId Form CPT ID.
     * @return void
     * @since 3.0.0
     */
    private function applyCaptchaModeFromCpt(\DOMElement $form, int $formId): void
    {
        // Unbound widgets keep whatever the template already has.
        if ($formId <= 0) {
            return;
        }

        // Read the sanitized presentation slice from Form CPT meta.
        $presentation = $this->formMeta->getPresentation($formId);
        $mode = isset($presentation['captcha'])
            ? sanitize_key((string) $presentation['captcha'])
            : '';

        // Ignore empty / unexpected values and leave the HTML attribute alone.
        if ($mode === '' || !in_array($mode, AttributeContract::CAPTCHA_MODES, true)) {
            return;
        }

        // Write CPT captcha onto the live markup (inherit, none, google, turnstile).
        $form->setAttribute('data-captcha', $mode);
    }

    /**
     * Apply Form CPT unique flags onto field controls before schema signing.
     *
     * Keeps live notifications aligned with Forms admin unique toggles.
     *
     * @param \DOMElement $form   Form root.
     * @param int         $formId Form CPT ID.
     * @return void
     * @since 3.0.0
     */
    private function applyUniqueFlagsFromCpt(\DOMElement $form, int $formId): void
    {
        if ($formId <= 0) {
            return;
        }

        $definitions = $this->formFields->get($formId);
        if ($definitions === []) {
            return;
        }

        // Map machine name => unique bool from the Form CPT.
        $uniqueMap = [];
        foreach ($definitions as $field) {
            if (!is_array($field) || empty($field['name'])) {
                continue;
            }
            $uniqueMap[(string) $field['name']] = !empty($field['unique']);
        }

        if ($uniqueMap === []) {
            return;
        }

        $xpath = new \DOMXPath($form->ownerDocument);
        $nodes = $xpath->query(
            './/*[contains(concat(" ", normalize-space(@class), " "), " '
            . AttributeContract::CLASS_FIELD
            . ' ")]',
            $form
        );

        if (!$nodes) {
            return;
        }

        foreach ($nodes as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            $name = sanitize_key($node->getAttribute('data-field-name'));
            if ($name === '') {
                $name = sanitize_key($node->getAttribute('name'));
            }

            if ($name === '' || !array_key_exists($name, $uniqueMap)) {
                continue;
            }

            // CPT is authoritative: write true or remove the attribute when off.
            if ($uniqueMap[$name]) {
                $node->setAttribute('data-unique', 'true');
            } else {
                $node->removeAttribute('data-unique');
            }
        }
    }

    /**
     * Apply Form CPT conditional visibility onto field controls before schema signing.
     *
     * Keeps live notifications aligned with Forms admin conditional rules.
     *
     * @param \DOMElement $form   Form root.
     * @param int         $formId Form CPT ID.
     * @return void
     * @since 3.0.0
     */
    private function applyConditionalAttributesFromCpt(\DOMElement $form, int $formId): void
    {
        if ($formId <= 0) {
            return;
        }

        $definitions = $this->formFields->get($formId);
        if ($definitions === []) {
            return;
        }

        // Map machine name => conditional rule from the Form CPT.
        $conditionalMap = [];
        foreach ($definitions as $field) {
            if (!is_array($field) || empty($field['name'])) {
                continue;
            }

            $conditionalMap[(string) $field['name']] = [
                'conditional_field'    => isset($field['conditional_field'])
                    ? sanitize_key((string) $field['conditional_field'])
                    : '',
                'conditional_operator' => isset($field['conditional_operator'])
                    ? sanitize_key((string) $field['conditional_operator'])
                    : '',
                'conditional_value'    => isset($field['conditional_value'])
                    ? sanitize_text_field((string) $field['conditional_value'])
                    : '',
            ];
        }

        if ($conditionalMap === []) {
            return;
        }

        $xpath = new \DOMXPath($form->ownerDocument);
        $nodes = $xpath->query(
            './/*[contains(concat(" ", normalize-space(@class), " "), " '
            . AttributeContract::CLASS_FIELD
            . ' ")]',
            $form
        );

        if (!$nodes) {
            return;
        }

        foreach ($nodes as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            $name = sanitize_key($node->getAttribute('data-field-name'));
            if ($name === '') {
                $name = sanitize_key($node->getAttribute('name'));
            }

            if ($name === '' || !array_key_exists($name, $conditionalMap)) {
                continue;
            }

            $rule = $conditionalMap[$name];
            $dep = $rule['conditional_field'];
            $op = $rule['conditional_operator'];
            $value = $rule['conditional_value'];

            // CPT is authoritative: write attrs or strip them when the rule is cleared.
            if ($dep !== '' && $op !== '' && in_array($op, AttributeContract::CONDITIONAL_OPERATORS, true)) {
                $node->setAttribute('data-conditional-field', $dep);
                $node->setAttribute('data-conditional-operator', $op);
                if ($value !== '') {
                    $node->setAttribute('data-conditional-value', $value);
                } else {
                    $node->removeAttribute('data-conditional-value');
                }
            } else {
                $node->removeAttribute('data-conditional-field');
                $node->removeAttribute('data-conditional-operator');
                $node->removeAttribute('data-conditional-value');
            }
        }
    }

    /**
     * Sync data-primary-color into the form root CSS custom property.
     *
     * @param \DOMElement $form Form root.
     * @return void
     * @since 3.0.0
     */
    private function applyPrimaryColor(\DOMElement $form): void
    {
        $color = sanitize_hex_color($form->getAttribute('data-primary-color'));
        if (!is_string($color) || $color === '') {
            return;
        }

        $style = (string) $form->getAttribute('style');
        $declaration = '--notifal-primary-color:' . $color;
        // Replace an existing custom property or append a new one.
        if (preg_match('/--notifal-primary-color\s*:\s*[^;]+;?/i', $style)) {
            $style = (string) preg_replace(
                '/--notifal-primary-color\s*:\s*[^;]+;?/i',
                $declaration . ';',
                $style
            );
        } else {
            $style = trim($style);
            if ($style !== '' && substr($style, -1) !== ';') {
                $style .= ';';
            }
            $style .= $declaration . ';';
        }

        $form->setAttribute('style', $style);
    }

    /**
     * @param string $html HTML.
     * @return \DOMDocument|null
     */
    private function createDocument(string $html): ?\DOMDocument
    {
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML(
            '<?xml encoding="utf-8" ?><html><body><div id="notifal-form-render-root">' . $html . '</div></body></html>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $loaded ? $document : null;
    }

    /**
     * @param \DOMDocument $document Document.
     * @param \DOMElement  $root     Root wrapper.
     * @return string
     */
    private function innerHtml(\DOMDocument $document, \DOMElement $root): string
    {
        $html = '';
        foreach ($root->childNodes as $child) {
            $html .= $document->saveHTML($child);
        }

        return $html;
    }
}
