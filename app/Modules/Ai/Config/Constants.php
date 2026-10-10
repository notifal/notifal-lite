<?php

namespace Notifal\Modules\Ai\Config;

defined('ABSPATH') || exit;

/**
 * Shared constants for the Notifal AI client infrastructure.
 *
 * @since 3.0.0
 * @updated 3.0.0 Bump PROMPT_VERSION to 1.9.6 for language + mood template font import guidance.
 * @author Hossein <hossein@notifal.com>
 */
class Constants
{
    /**
     * Non-autoloaded option storing public installation metadata.
     *
     * @var string
     */
    public const OPTION_INSTALLATION = 'notifal_ai_installation';

    /**
     * Non-autoloaded option storing encrypted refresh credentials.
     *
     * @var string
     */
    public const OPTION_CREDENTIALS = 'notifal_ai_credentials';

    /**
     * Non-autoloaded option storing the one-time site profile.
     *
     * @var string
     */
    public const OPTION_SITE_PROFILE = 'notifal_ai_site_profile';

    /**
     * Transient key prefix for cached access tokens.
     *
     * @var string
     */
    public const TRANSIENT_ACCESS_TOKEN = 'notifal_ai_access_token';

    /**
     * Transient key for the lightweight generation readiness snapshot.
     *
     * @var string
     */
    public const TRANSIENT_GENERATION_READINESS = 'notifal_ai_generation_readiness';

    /**
     * Default cache lifetime for generation readiness (seconds).
     *
     * Kept short so enabling the gateway switch is felt quickly, while still
     * protecting notifal.com from repeated health probes on every AI open.
     *
     * @var int
     */
    public const READINESS_CACHE_TTL = 45;

    /**
     * AJAX nonce action shared by AI endpoints.
     *
     * @var string
     */
    public const AJAX_NONCE_ACTION = 'notifal_ai_ajax_nonce';

    /**
     * Default disclosure version mirrored from the gateway launch settings.
     *
     * @var string
     */
    public const DEFAULT_DISCLOSURE_VERSION = '2026-07-19';

    /**
     * Gateway knowledge versions sent with every generation request.
     *
     * @var string
     */
    public const PROMPT_VERSION = '1.9.6';

    /**
     * Gateway schema version sent with every generation request.
     *
     * @var string
     */
    public const SCHEMA_VERSION = '1.2.0';

    /**
     * Maximum prior conversation turns sent with HTML Builder chat requests.
     *
     * Must stay in sync with KnowledgeRegistry::MAX_CONVERSATION_MESSAGES on the gateway.
     *
     * @var int
     */
    public const MAX_AI_CHAT_CONVERSATION_MESSAGES = 20;

    /**
     * Maximum UTF-8 bytes for one generation description or edit instruction.
     *
     * Must stay in sync with InputValidator::MAX_INSTRUCTION_BYTES on the gateway
     * and MAX_AI_PROMPT_BYTES in the HTML Builder aiPromptLimits utility.
     *
     * @var int
     */
    public const MAX_GENERATION_PROMPT_BYTES = 20000;

    /**
     * Maximum UTF-8 bytes for one prior HTML Builder chat turn.
     *
     * Must stay in sync with KnowledgeRegistry::MAX_CONVERSATION_MESSAGE_BYTES.
     *
     * @var int
     */
    public const MAX_AI_CHAT_CONVERSATION_TURN_BYTES = 4000;

    /**
     * Default OAuth-style scopes requested during account connection.
     *
     * @var string[]
     */
    public const DEFAULT_SCOPES = [
        'generation:create',
        'generation:read',
        'generation:cancel',
        'usage:read',
        'connection:manage',
    ];

    /**
     * User agent suffix for server-to-server gateway calls.
     *
     * @var string
     */
    public const USER_AGENT = 'Notifal-Client/';
}
