<?php

namespace Notifal\Modules\Ai\Domain;

use Notifal\Modules\Ai\Config\Constants;

defined('ABSPATH') || exit;

/**
 * Normalizes and validates hosted generation intent before the gateway call.
 *
 * Mirrors the notifal-ai-gateway contract so users get actionable errors locally.
 *
 * @since 3.0.0
 * @updated 3.0.0 Allow intent.images for OnPage vision reference attachments.
 * @updated 3.0.0 Support standalone Form CPT create/edit (`form`).
 * @updated 3.0.0 Allow hardened intent.reference_html for convert-from-markup prompts.
 * @updated 3.0.0 Align wizard_site_analysis intent keys and content limits with the gateway.
 * @updated 3.0.0 Allow detected_primary_color and detected_brand_colors for wizard_site_analysis.
 * @updated 3.0.0 Allow Pro inline layout when notifal_pro capability is active.
 * @updated 3.0.0 Accept collect_leads and derive it from capture use_case slugs.
 * @updated 3.0.0 Allow intent.design_draft_html for Pass 2-only retries after Pass 1.
 * @author Hossein <hossein@notifal.com>
 */
class GenerationIntentValidator
{
    /**
     * Use-case slugs that imply email / lead capture (wizard + builder vocabularies).
     *
     * @since 3.0.0
     * @var string[]
     */
    private const CAPTURE_USE_CASES = [
        'lead_capture',
        'newsletter',
        'welcome_offer',
        'grow-email-list',
        'lead-generation',
    ];

    /**
     * Minimum UTF-8 byte length for a useful generation description.
     *
     * @var int
     */
    private const MIN_DESCRIPTION_BYTES = 10;

    /**
     * Minimum UTF-8 byte length for an edit instruction.
     *
     * @var int
     */
    private const MIN_INSTRUCTION_BYTES = 5;

    /**
     * Maximum UTF-8 bytes for one create description or edit instruction.
     *
     * @var int
     */
    private const MAX_PROMPT_BYTES = Constants::MAX_GENERATION_PROMPT_BYTES;

    /**
     * Allowed top-level intent keys for standard generation features.
     *
     * @var string[]
     */
    private const ALLOWED_INTENT_KEYS = [
        'industry',
        'goal',
        'layout',
        'appearance',
        'description',
        'example',
        'language',
        'site_profile',
        'use_case',
        'instruction',
        'existing_html',
        'existing_fields',
        // Optional pasted markup/CSS to convert (hardened, never admin-rendered).
        'reference_html',
        // Optional prior Pass 1 HTML for Pass 2-only retry (hardened, never admin-rendered).
        'design_draft_html',
        'images',
        // Optional flag: when true, the gateway injects Notifal Forms knowledge.
        'include_form',
        // Explicit lead-capture contract (language-agnostic; preferred over free-text guess).
        'collect_leads',
        'wants_form',
        'collect_email',
        // When true, quiz/survey answers must use Notifal .notifal-form-step fields.
        'form_multi_step',
        // When true, progressive hide/show panels should use custom-trigger UI steps.
        'ui_multi_step',
        // Optional flag: when true, the gateway retrieves Notifal documentation excerpts.
        'include_docs',
        // Prior HTML Builder chat turns for multi-turn Noti context.
        'conversation',
    ];

    /**
     * Normalize and validate one intent array for a hosted feature.
     *
     * @since 3.0.0
     * @param string $feature Generation feature slug.
     * @param array<string, mixed> $intent Raw intent from the browser.
     * @return array{success: true, intent: array<string, mixed>}|array{success: false, code: string, message: string, fields: string[]}
     */
    public static function normalize(string $feature, array $intent): array
    {
        // Normalize legacy edit slugs before validating the feature allowlist.
        $feature = AiFeature::normalize($feature);

        // Reject unsupported features before inspecting intent fields.
        if (!AiFeature::isValid($feature)) {
            return self::failure(
                'generation_unsupported_feature',
                __('This AI feature is not supported on your Notifal version.', 'notifal'),
                ['feature']
            );
        }

        // Fold legacy top-level business copy into site_profile for gateway compatibility.
        $intent = self::foldBusinessDescription($intent);

        // Setup Wizard site analysis uses a dedicated intent contract.
        if (AiFeature::WIZARD_SITE_ANALYSIS === $feature) {
            return self::normalizeWizardSiteAnalysis($intent);
        }

        // Capture raw edit context before pruning empty create-mode keys.
        $existingHtmlRaw   = (string) ($intent['existing_html'] ?? '');
        $existingFieldsRaw = $intent['existing_fields'] ?? null;
        $hasExistingFields = is_array($existingFieldsRaw) && $existingFieldsRaw !== [];

        // Capture optional convert-from-markup HTML before unknown-key checks.
        $referenceHtmlRaw = $intent['reference_html'] ?? null;
        // Capture optional Pass 1 draft reuse for Pass 2-only retries.
        $designDraftHtmlRaw = $intent['design_draft_html'] ?? null;

        // Detect edit modes for HTML Builder and standalone Form CPT.
        $isHtmlEdit = AiFeature::HTML_TEMPLATE === $feature && '' !== trim($existingHtmlRaw);
        $isFormEdit = AiFeature::FORM === $feature
            && ('' !== trim($existingHtmlRaw) || $hasExistingFields);

        // Drop empty edit fields on create requests so they are not treated as unknown keys.
        if (!$isHtmlEdit && !$isFormEdit) {
            unset($intent['instruction'], $intent['existing_html'], $intent['existing_fields']);
        }

        // Reference / design-draft HTML is create-only; drop it on edit requests.
        if ($isHtmlEdit || $isFormEdit) {
            unset($intent['reference_html'], $intent['design_draft_html']);
            $referenceHtmlRaw   = null;
            $designDraftHtmlRaw = null;
        }

        // Reject unknown top-level keys that the gateway would block.
        $unknownKeys = array_values(array_diff(array_keys($intent), self::ALLOWED_INTENT_KEYS));
        if ($unknownKeys !== []) {
            return self::failure(
                'generation_intent_unknown_fields',
                sprintf(
                    /* translators: %s: comma-separated field names */
                    __('These wizard fields are not accepted by Notifal AI: %s. Update Notifal to the latest version or simplify your choices and try again.', 'notifal'),
                    implode(', ', array_map('sanitize_key', $unknownKeys))
                ),
                $unknownKeys
            );
        }

        // Drop conversation on features that do not support multi-turn builder chat.
        if (AiFeature::HTML_TEMPLATE !== $feature) {
            unset($intent['conversation']);
        }

        // OnPage generation requires layout (Appearance notification_display_type).
        // HTML Builder templates are layout-agnostic; Form CPT never uses layout.
        $layout = sanitize_key((string) ($intent['layout'] ?? ''));
        // Mirror gateway capability rules (inline requires Notifal Pro).
        $layoutCapabilities = OnPageDisplayLayoutSupport::clientCapabilities();
        if (AiFeature::ONPAGE_NOTIFICATION === $feature) {
            if (!OnPageDisplayLayoutSupport::isAllowed($layout, $layoutCapabilities)) {
                return self::failure(
                    'generation_invalid_layout',
                    OnPageDisplayLayoutSupport::invalidLayoutMessage($layout, $layoutCapabilities),
                    ['layout']
                );
            }
        } elseif (AiFeature::HTML_TEMPLATE === $feature) {
            // Optional create-time hint only; drop on edits or invalid values.
            if ($isHtmlEdit || !OnPageDisplayLayoutSupport::isAllowed($layout, $layoutCapabilities)) {
                $layout = '';
                unset($intent['layout']);
            }
        } else {
            $layout = '';
            unset($intent['layout']);
        }

        // HTML template edit mode validates instruction and keeps template HTML intact.
        if ($isHtmlEdit) {
            return self::normalizeHtmlEdit($intent, $existingHtmlRaw);
        }

        // Form CPT edit mode validates instruction plus existing HTML and/or fields.
        if ($isFormEdit) {
            return self::normalizeFormEdit($intent, $existingHtmlRaw, $existingFieldsRaw, $hasExistingFields);
        }

        // Harden optional reference HTML before description checks (create modes only).
        $referenceCheck = self::normalizeReferenceHtml($intent, $referenceHtmlRaw);
        if (empty($referenceCheck['success'])) {
            return $referenceCheck;
        }
        $intent = $referenceCheck['intent'];
        $hasReferenceHtml = '' !== trim((string) ($intent['reference_html'] ?? ''));

        // Harden optional Pass 1 draft reuse for Pass 2-only retries (create modes only).
        $designDraftCheck = self::normalizeDesignDraftHtml($intent, $designDraftHtmlRaw);
        if (empty($designDraftCheck['success'])) {
            return $designDraftCheck;
        }
        $intent = $designDraftCheck['intent'];
        $hasDesignDraftHtml = '' !== trim((string) ($intent['design_draft_html'] ?? ''));

        // Require a bounded description for create, OnPage, and Form create modes.
        $description = sanitize_textarea_field((string) ($intent['description'] ?? ''));
        $descriptionBytes = strlen($description);

        // When reusable markup is provided, allow a short prose brief and supply a convert default.
        if ($descriptionBytes < self::MIN_DESCRIPTION_BYTES && ($hasReferenceHtml || $hasDesignDraftHtml)) {
            $description = $hasDesignDraftHtml
                ? __('Adapt the provided design draft HTML into a Notifal-compatible template.', 'notifal')
                : __('Convert the provided reference HTML into a Notifal-compatible template.', 'notifal');
            $descriptionBytes = strlen($description);
        }

        if ($descriptionBytes < self::MIN_DESCRIPTION_BYTES) {
            return self::failure(
                'generation_description_too_short',
                AiFeature::FORM === $feature
                    ? __('Your prompt is too short. Describe the form you want in at least one full sentence, then try again.', 'notifal')
                    : __('Your prompt is too short. Describe the popup you want in at least one full sentence, then try again.', 'notifal'),
                ['description']
            );
        }

        if ($descriptionBytes > self::MAX_PROMPT_BYTES) {
            return self::failure(
                'generation_description_too_long',
                GatewayErrorMapper::message('generation_description_too_long'),
                ['description']
            );
        }

        // Persist layout only when it remains meaningful for this feature.
        if ('' !== $layout) {
            $intent['layout'] = $layout;
        }
        $intent['description'] = $description;

        // Normalize optional Forms and multi-step capture flags when the client sends them.
        $intent = self::normalizeFormCaptureFlags($intent);

        // Normalize prior chat turns for HTML Builder create requests.
        $conversationCheck = self::normalizeConversation($intent);
        if (!$conversationCheck['success']) {
            return $conversationCheck;
        }
        $intent = $conversationCheck['intent'];

        // Return the sanitized intent for the gateway request body.
        return [
            'success' => true,
            'intent'  => $intent,
        ];
    }

    /**
     * Normalize an HTML Builder edit intent.
     *
     * @param array<string, mixed> $intent          Intent under construction.
     * @param string               $existingHtmlRaw Existing template HTML.
     * @return array{success: true, intent: array<string, mixed>}|array{success: false, code: string, message: string, fields: string[]}
     * @since 3.0.0
     * @updated 3.0.0 Drop layout; templates are layout-agnostic OnPage Appearance controls display type.
     */
    private static function normalizeHtmlEdit(array $intent, string $existingHtmlRaw): array
    {
        $instruction = sanitize_textarea_field((string) ($intent['instruction'] ?? ''));
        $instructionBytes = strlen($instruction);
        if ($instructionBytes < self::MIN_INSTRUCTION_BYTES) {
            return self::failure(
                'generation_instruction_too_short',
                __('Describe the change you want (at least 5 characters).', 'notifal'),
                ['instruction']
            );
        }

        if ($instructionBytes > self::MAX_PROMPT_BYTES) {
            return self::failure(
                'generation_instruction_too_long',
                GatewayErrorMapper::message('generation_instruction_too_long'),
                ['instruction']
            );
        }

        if ('' === trim($existingHtmlRaw)) {
            return self::failure(
                'generation_existing_html_missing',
                __('Existing template HTML is required for edits.', 'notifal'),
                ['existing_html']
            );
        }

        // Prefer an explicit description; otherwise mirror the instruction for the gateway prompt.
        $description = sanitize_textarea_field((string) ($intent['description'] ?? ''));
        if (strlen($description) < self::MIN_DESCRIPTION_BYTES) {
            $description = $instruction;
        }

        if (strlen($description) > self::MAX_PROMPT_BYTES) {
            return self::failure(
                'generation_description_too_long',
                GatewayErrorMapper::message('generation_description_too_long'),
                ['description']
            );
        }

        $intent['instruction']   = $instruction;
        $intent['description']   = $description;
        $intent['existing_html'] = $existingHtmlRaw;
        unset($intent['existing_fields'], $intent['layout']);

        $intent = self::normalizeFormCaptureFlags($intent);

        // Normalize prior chat turns for HTML Builder edit requests.
        $conversationCheck = self::normalizeConversation($intent);
        if (!$conversationCheck['success']) {
            return $conversationCheck;
        }
        $intent = $conversationCheck['intent'];

        return [
            'success' => true,
            'intent'  => $intent,
        ];
    }

    /**
     * Normalize prior HTML Builder chat turns for the gateway.
     *
     * @param array<string, mixed> $intent Intent under construction.
     * @return array{success: true, intent: array<string, mixed>}|array{success: false, code: string, message: string, fields: string[]}
     * @since 3.0.0
     */
    private static function normalizeConversation(array $intent): array
    {
        if (!array_key_exists('conversation', $intent)) {
            return [
                'success' => true,
                'intent'  => $intent,
            ];
        }

        $raw = $intent['conversation'];
        if (!is_array($raw)) {
            return self::failure(
                'conversation_too_long',
                GatewayErrorMapper::message('conversation_too_long'),
                ['conversation']
            );
        }

        // Reject oversized history before the gateway round-trip.
        if (count($raw) > Constants::MAX_AI_CHAT_CONVERSATION_MESSAGES) {
            return self::failure(
                'conversation_too_long',
                GatewayErrorMapper::message('conversation_too_long'),
                ['conversation']
            );
        }

        $normalized = [];
        foreach ($raw as $turn) {
            if (!is_array($turn)) {
                continue;
            }

            $role = sanitize_key((string) ($turn['role'] ?? ''));
            if (!in_array($role, ['user', 'assistant'], true)) {
                continue;
            }

            $content = sanitize_textarea_field((string) ($turn['content'] ?? ''));
            if ('' === $content) {
                continue;
            }

            $contentBytes = strlen($content);
            if ($contentBytes > Constants::MAX_AI_CHAT_CONVERSATION_TURN_BYTES) {
                return self::failure(
                    'conversation_too_long',
                    GatewayErrorMapper::message('conversation_too_long'),
                    ['conversation']
                );
            }

            $normalized[] = [
                'role'    => $role,
                'content' => $content,
            ];
        }

        if ($normalized === []) {
            unset($intent['conversation']);
        } else {
            $intent['conversation'] = $normalized;
        }

        return [
            'success' => true,
            'intent'  => $intent,
        ];
    }

    /**
     * Normalize a standalone Form CPT edit intent.
     *
     * @param array<string, mixed> $intent            Intent under construction.
     * @param string               $existingHtmlRaw   Existing form HTML.
     * @param mixed                $existingFieldsRaw Existing field definitions.
     * @param bool                 $hasExistingFields Whether fields were provided.
     * @return array{success: true, intent: array<string, mixed>}|array{success: false, code: string, message: string, fields: string[]}
     */
    private static function normalizeFormEdit(
        array $intent,
        string $existingHtmlRaw,
        $existingFieldsRaw,
        bool $hasExistingFields
    ): array {
        $instruction = sanitize_textarea_field((string) ($intent['instruction'] ?? ''));
        $instructionBytes = strlen($instruction);
        if ($instructionBytes < self::MIN_INSTRUCTION_BYTES) {
            return self::failure(
                'generation_instruction_too_short',
                __('Describe the form change you want (at least 5 characters).', 'notifal'),
                ['instruction']
            );
        }

        if ($instructionBytes > self::MAX_PROMPT_BYTES) {
            return self::failure(
                'generation_instruction_too_long',
                GatewayErrorMapper::message('generation_instruction_too_long'),
                ['instruction']
            );
        }

        // Prefer an explicit description; otherwise mirror the instruction for the gateway prompt.
        $description = sanitize_textarea_field((string) ($intent['description'] ?? ''));
        if (strlen($description) < self::MIN_DESCRIPTION_BYTES) {
            $description = $instruction;
        }

        if (strlen($description) > self::MAX_PROMPT_BYTES) {
            return self::failure(
                'generation_description_too_long',
                GatewayErrorMapper::message('generation_description_too_long'),
                ['description']
            );
        }

        $intent['instruction'] = $instruction;
        $intent['description'] = $description;
        unset($intent['layout']);

        if ('' !== trim($existingHtmlRaw)) {
            $intent['existing_html'] = $existingHtmlRaw;
        } else {
            unset($intent['existing_html']);
        }

        if ($hasExistingFields && is_array($existingFieldsRaw)) {
            $intent['existing_fields'] = self::sanitizeExistingFields($existingFieldsRaw);
        } else {
            unset($intent['existing_fields']);
        }

        // Normalize optional Forms and multi-step capture flags for Form CPT edits.
        $intent = self::normalizeFormCaptureFlags($intent);

        return [
            'success' => true,
            'intent'  => $intent,
        ];
    }

    /**
     * Cast optional Forms and multi-step flags to booleans for the gateway.
     *
     * @since 3.0.0
     * @updated 3.0.0 Derive collect_leads from capture use_case and form_multi_step.
     * @param array<string, mixed> $intent Intent under construction.
     * @return array<string, mixed> Intent with strict boolean capture flags.
     */
    private static function normalizeFormCaptureFlags(array $intent): array
    {
        // Cast include_form when the client sent the key.
        if (array_key_exists('include_form', $intent)) {
            $intent['include_form'] = !empty($intent['include_form']);
        }

        // Cast form_multi_step and force include_form when quiz capture is requested.
        if (array_key_exists('form_multi_step', $intent)) {
            $intent['form_multi_step'] = !empty($intent['form_multi_step']);
            if (!empty($intent['form_multi_step'])) {
                $intent['include_form'] = true;
            }
        }

        // Cast ui_multi_step for progressive reveal panels that are not form fields.
        if (array_key_exists('ui_multi_step', $intent)) {
            $intent['ui_multi_step'] = !empty($intent['ui_multi_step']);
        }

        // Optional aliases from alternate clients.
        if (array_key_exists('wants_form', $intent)) {
            $intent['wants_form'] = !empty($intent['wants_form']);
        }
        if (array_key_exists('collect_email', $intent)) {
            $intent['collect_email'] = !empty($intent['collect_email']);
        }

        // Cast explicit collect_leads when present.
        if (array_key_exists('collect_leads', $intent)) {
            $intent['collect_leads'] = !empty($intent['collect_leads']);
        }

        // Derive collect_leads from multi-step, aliases, and capture use_case slugs.
        if (!empty($intent['form_multi_step']) || !empty($intent['wants_form']) || !empty($intent['collect_email'])) {
            $intent['collect_leads'] = true;
        }
        if (empty($intent['collect_leads']) && self::useCaseImpliesCollectLeads((string) ($intent['use_case'] ?? ''))) {
            $intent['collect_leads'] = true;
        }

        // Forms knowledge follows collect_leads for HTML Builder create jobs.
        if (!empty($intent['collect_leads'])) {
            $intent['include_form'] = true;
        }

        // Cast include_docs when the client requested documentation retrieval.
        if (array_key_exists('include_docs', $intent)) {
            $intent['include_docs'] = !empty($intent['include_docs']);
        }

        return $intent;
    }

    /**
     * Whether a use_case slug implies email / lead capture by product contract.
     *
     * @since 3.0.0
     * @param string $useCase Raw use_case slug.
     * @return bool True when capture is required regardless of brief language.
     */
    public static function useCaseImpliesCollectLeads(string $useCase): bool
    {
        $slug = sanitize_key($useCase);

        return '' !== $slug && in_array($slug, self::CAPTURE_USE_CASES, true);
    }

    /**
     * Sanitize existing Form CPT field definitions for edit context.
     *
     * @param array<int, mixed> $fields Raw fields.
     * @return array<int, array<string, mixed>>
     */
    private static function sanitizeExistingFields(array $fields): array
    {
        $normalized = [];

        foreach (array_values($fields) as $field) {
            if (40 <= count($normalized) || !is_array($field)) {
                break;
            }

            $name = sanitize_key((string) ($field['name'] ?? ''));
            if ('' === $name) {
                continue;
            }

            $options = [];
            if (isset($field['options']) && is_array($field['options'])) {
                foreach ($field['options'] as $option) {
                    if (!is_scalar($option)) {
                        continue;
                    }
                    $options[] = sanitize_text_field((string) $option);
                    if (50 <= count($options)) {
                        break;
                    }
                }
            }

            $normalized[] = [
                'name'                 => $name,
                'type'                 => sanitize_key((string) ($field['type'] ?? 'text')),
                'label'                => sanitize_text_field((string) ($field['label'] ?? '')),
                'placeholder'          => sanitize_text_field((string) ($field['placeholder'] ?? '')),
                'required'             => !empty($field['required']),
                'unique'               => !empty($field['unique']),
                'regex'                => substr(sanitize_text_field((string) ($field['regex'] ?? '')), 0, 120),
                'options'              => $options,
                'conditional_field'    => sanitize_key((string) ($field['conditional_field'] ?? '')),
                'conditional_operator' => sanitize_key((string) ($field['conditional_operator'] ?? '')),
                'conditional_value'    => sanitize_text_field((string) ($field['conditional_value'] ?? '')),
            ];
        }

        return $normalized;
    }

    /**
     * Move top-level business_description into site_profile when present.
     *
     * @since 3.0.0
     * @param array<string, mixed> $intent Raw intent array.
     * @return array<string, mixed> Intent without unsupported top-level business_description.
     */
    private static function foldBusinessDescription(array $intent): array
    {
        // Read optional business copy from the legacy top-level wizard field.
        $business = sanitize_textarea_field((string) ($intent['business_description'] ?? ''));

        // Nothing to fold when the legacy field is empty.
        if ($business === '') {
            unset($intent['business_description']);

            return $intent;
        }

        // Merge business copy into site_profile for gateway-safe structured metadata.
        $siteProfile = is_array($intent['site_profile'] ?? null) ? $intent['site_profile'] : [];
        if (!isset($siteProfile['business_description']) || $siteProfile['business_description'] === '') {
            $siteProfile['business_description'] = $business;
        }

        // Persist merged profile and drop the unsupported top-level key.
        $intent['site_profile'] = $siteProfile;
        unset($intent['business_description']);

        return $intent;
    }

    /**
     * Harden optional create-mode reference HTML for convert-from-markup prompts.
     *
     * @since 3.0.0
     * @param array<string, mixed> $intent           Intent under construction.
     * @param mixed                $referenceHtmlRaw Raw reference HTML from the client.
     * @return array{success: true, intent: array<string, mixed>}|array{success: false, code: string, message: string, fields: string[]}
     */
    private static function normalizeReferenceHtml(array $intent, $referenceHtmlRaw): array
    {
        // Drop the key when the client did not send reference markup.
        if (null === $referenceHtmlRaw || '' === trim((string) $referenceHtmlRaw)) {
            unset($intent['reference_html']);

            return [
                'success' => true,
                'intent'  => $intent,
            ];
        }

        // Reject oversized reference payloads with an actionable error.
        if (ReferenceHtmlNormalizer::exceedsLimit($referenceHtmlRaw)) {
            return self::failure(
                'generation_reference_html_too_long',
                __('Your reference HTML is too large. Shorten the pasted markup or CSS and try again.', 'notifal'),
                ['reference_html']
            );
        }

        // Harden scripts, event handlers, and other executable constructs.
        $hardened = ReferenceHtmlNormalizer::normalize($referenceHtmlRaw);
        if ('' === $hardened) {
            unset($intent['reference_html']);

            return [
                'success' => true,
                'intent'  => $intent,
            ];
        }

        // Persist hardened markup for the gateway convert prompt.
        $intent['reference_html'] = $hardened;

        return [
            'success' => true,
            'intent'  => $intent,
        ];
    }

    /**
     * Harden optional create-mode Pass 1 design draft HTML for Pass 2-only retries.
     *
     * @since 3.0.0
     * @param array<string, mixed> $intent             Intent under construction.
     * @param mixed                $designDraftHtmlRaw Raw design draft HTML from the client.
     * @return array{success: true, intent: array<string, mixed>}|array{success: false, code: string, message: string, fields: string[]}
     */
    private static function normalizeDesignDraftHtml(array $intent, $designDraftHtmlRaw): array
    {
        // Drop the key when the client did not send a reusable Pass 1 draft.
        if (null === $designDraftHtmlRaw || '' === trim((string) $designDraftHtmlRaw)) {
            unset($intent['design_draft_html']);

            return [
                'success' => true,
                'intent'  => $intent,
            ];
        }

        // Reject oversized draft payloads with the same actionable ceiling as reference HTML.
        if (ReferenceHtmlNormalizer::exceedsLimit($designDraftHtmlRaw)) {
            return self::failure(
                'generation_reference_html_too_long',
                __('Your design draft HTML is too large. Shorten the markup or CSS and try again.', 'notifal'),
                ['design_draft_html']
            );
        }

        // Harden scripts, event handlers, and other executable constructs.
        $hardened = ReferenceHtmlNormalizer::normalize($designDraftHtmlRaw);
        if ('' === $hardened) {
            unset($intent['design_draft_html']);

            return [
                'success' => true,
                'intent'  => $intent,
            ];
        }

        // Persist hardened markup so the gateway can skip Pass 1 and adapt Pass 2 only.
        $intent['design_draft_html'] = $hardened;

        return [
            'success' => true,
            'intent'  => $intent,
        ];
    }

    /**
     * Maximum UTF-8 bytes accepted for wizard scraped site content.
     *
     * Matches notifal-ai-gateway WizardPromptBuilder::MAX_SITE_CONTENT_BYTES.
     *
     * @var int
     */
    private const MAX_WIZARD_SITE_CONTENT_BYTES = 65536;

    /**
     * Minimum UTF-8 bytes required for useful wizard site content.
     *
     * @var int
     */
    private const MIN_WIZARD_SITE_CONTENT_BYTES = 20;

    /**
     * Allowed wizard site analysis intent keys.
     *
     * Keep in sync with notifal-ai-gateway InputValidator::validate_wizard_site_analysis.
     *
     * @var string[]
     */
    private const WIZARD_SITE_ANALYSIS_KEYS = [
        'site_content',
        'active_plugins',
        'is_woocommerce',
        'site_domain',
        'site_locale',
        'site_timezone',
        'locale',
        'page_title',
        'meta_description',
        'site_title',
        'tagline',
        'logo_url',
        'detected_primary_color',
        'detected_brand_colors',
    ];

    /**
     * Maximum unique brand palette colors accepted from Setup Wizard scrape.
     *
     * @var int
     */
    private const MAX_WIZARD_DETECTED_BRAND_COLORS = 3;

    /**
     * Normalize Setup Wizard public site analysis intent.
     *
     * @since 3.0.0
     * @param array<string, mixed> $intent Raw wizard analysis intent.
     * @return array{success: true, intent: array<string, mixed>}|array{success: false, code: string, message: string, fields: string[]}
     */
    private static function normalizeWizardSiteAnalysis(array $intent): array
    {
        // Reject unknown wizard intent keys before sanitization.
        $unknownKeys = array_values(array_diff(array_keys($intent), self::WIZARD_SITE_ANALYSIS_KEYS));
        if ($unknownKeys !== []) {
            return self::failure(
                'generation_intent_unknown_fields',
                sprintf(
                    /* translators: %s: comma-separated field names */
                    __('These wizard fields are not accepted by Notifal AI: %s. Update Notifal to the latest version or simplify your choices and try again.', 'notifal'),
                    implode(', ', array_map('sanitize_key', $unknownKeys))
                ),
                $unknownKeys
            );
        }

        // Sanitize scraped site content before byte checks.
        $siteContent = sanitize_textarea_field((string) ($intent['site_content'] ?? ''));
        $contentBytes = strlen($siteContent);

        // Require bounded scraped content for useful analysis.
        if ($contentBytes < self::MIN_WIZARD_SITE_CONTENT_BYTES || $contentBytes > self::MAX_WIZARD_SITE_CONTENT_BYTES) {
            return self::failure(
                'generation_site_content_length',
                __('We could not read enough public site content for analysis. Check that your home page is reachable and try again.', 'notifal'),
                ['site_content']
            );
        }

        // Build normalized wizard intent payload.
        $normalized = [
            'site_content'   => $siteContent,
            'is_woocommerce' => !empty($intent['is_woocommerce']),
        ];

        // Normalize optional active plugin slugs from the local site.
        $rawPlugins = $intent['active_plugins'] ?? [];
        if (is_array($rawPlugins)) {
            $plugins = [];
            foreach (array_slice($rawPlugins, 0, 100) as $slug) {
                $slug = sanitize_key((string) $slug);
                if ('' !== $slug) {
                    $plugins[] = $slug;
                }
            }
            if ($plugins !== []) {
                $normalized['active_plugins'] = array_values(array_unique($plugins));
            }
        }

        // Bound optional public site host.
        $siteDomain = sanitize_text_field((string) ($intent['site_domain'] ?? ''));
        if ('' !== $siteDomain) {
            $normalized['site_domain'] = substr($siteDomain, 0, 253);
        }

        // Bound optional locale hints.
        $siteLocale = sanitize_text_field((string) ($intent['site_locale'] ?? ($intent['locale'] ?? '')));
        if ('' !== $siteLocale) {
            $normalized['site_locale'] = substr($siteLocale, 0, 20);
        }

        // Bound optional site timezone for seasonal suggestion context.
        $siteTimezone = sanitize_text_field((string) ($intent['site_timezone'] ?? ''));
        if ('' !== $siteTimezone) {
            $normalized['site_timezone'] = substr($siteTimezone, 0, 64);
        }

        // Bound optional page metadata scraped from the home page.
        $pageTitle = sanitize_text_field((string) ($intent['page_title'] ?? ''));
        if ('' !== $pageTitle) {
            $normalized['page_title'] = substr($pageTitle, 0, 200);
        }

        $metaDescription = sanitize_textarea_field((string) ($intent['meta_description'] ?? ''));
        if ('' !== $metaDescription) {
            $normalized['meta_description'] = substr($metaDescription, 0, 500);
        }

        // Bound optional WordPress site title used by gateway wizard prompts.
        $siteTitle = sanitize_text_field((string) ($intent['site_title'] ?? ''));
        if ('' !== $siteTitle) {
            $normalized['site_title'] = substr($siteTitle, 0, 200);
        }

        // Bound optional tagline used by gateway wizard prompts.
        $tagline = sanitize_textarea_field((string) ($intent['tagline'] ?? ''));
        if ('' !== $tagline) {
            $normalized['tagline'] = substr($tagline, 0, 500);
        }

        // Bound optional logo URL for brand hints.
        $logoUrl = esc_url_raw((string) ($intent['logo_url'] ?? ''));
        if ('' !== $logoUrl) {
            $normalized['logo_url'] = substr($logoUrl, 0, 500);
        }

        // Bound optional scraped primary brand color for gateway suggestion appearance.
        $detectedPrimaryColor = self::sanitizeWizardHexColor((string) ($intent['detected_primary_color'] ?? ''));
        if ('' !== $detectedPrimaryColor) {
            $normalized['detected_primary_color'] = $detectedPrimaryColor;
        }

        // Bound optional scraped brand palette colors for gateway suggestion appearance.
        $detectedBrandColors = self::sanitizeWizardBrandColors($intent['detected_brand_colors'] ?? []);
        if ($detectedBrandColors !== []) {
            $normalized['detected_brand_colors'] = $detectedBrandColors;
        }

        // Return gateway-safe wizard analysis intent.
        return [
            'success' => true,
            'intent'  => $normalized,
        ];
    }

    /**
     * Sanitize one Setup Wizard hex color for hosted analysis intent.
     *
     * @since 3.0.0
     * @param string $color Raw color value from scrape metadata.
     * @return string Lowercase #rgb/#rrggbb hex or empty string.
     */
    private static function sanitizeWizardHexColor(string $color): string
    {
        // Prefer WordPress hex sanitizer when available.
        if (function_exists('sanitize_hex_color')) {
            $sanitized = sanitize_hex_color(trim($color));
            return is_string($sanitized) ? strtolower($sanitized) : '';
        }

        // Fallback for environments without sanitize_hex_color().
        $color = trim($color);
        if (!preg_match('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $color)) {
            return '';
        }

        return strtolower($color);
    }

    /**
     * Sanitize a bounded list of unique Setup Wizard brand palette colors.
     *
     * @since 3.0.0
     * @param mixed $colors Raw color list from scrape metadata.
     * @return string[] Unique lowercase hex colors.
     */
    private static function sanitizeWizardBrandColors($colors): array
    {
        // Reject non-array palette payloads.
        if (!is_array($colors)) {
            return [];
        }

        // Normalize each candidate and keep unique values only.
        $sanitized = [];
        foreach ($colors as $color) {
            $hex = self::sanitizeWizardHexColor((string) $color);
            if ('' === $hex || in_array($hex, $sanitized, true)) {
                continue;
            }

            $sanitized[] = $hex;
            if (count($sanitized) >= self::MAX_WIZARD_DETECTED_BRAND_COLORS) {
                break;
            }
        }

        return $sanitized;
    }

    /**
     * Build a standardized validation failure payload.
     *
     * @since 3.0.0
     * @param string $code Stable error code for JavaScript handlers.
     * @param string $message Localized user-facing message.
     * @param string[] $fields Related intent field slugs.
     * @return array{success: false, code: string, message: string, fields: string[]}
     */
    private static function failure(string $code, string $message, array $fields): array
    {
        // Return a consistent failure envelope for the generation bridge.
        return [
            'success' => false,
            'code'    => sanitize_key($code),
            'message' => $message,
            'fields'  => array_map('sanitize_key', $fields),
        ];
    }
}
