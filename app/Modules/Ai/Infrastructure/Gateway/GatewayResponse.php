<?php

namespace Notifal\Modules\Ai\Infrastructure\Gateway;

defined('ABSPATH') || exit;

/**
 * Normalizes hosted AI gateway HTTP responses for application services.
 *
 * @since 3.0.0
 * @updated 3.0.0 Carry optional related intent field slugs from validation errors.
 * @author Hossein <hossein@notifal.com>
 */
class GatewayResponse
{
    /**
     * Whether the gateway reported success.
     *
     * @var bool
     */
    private bool $success;

    /**
     * HTTP status code returned by the gateway.
     *
     * @var int
     */
    private int $statusCode;

    /**
     * Parsed response data on success.
     *
     * @var array<string, mixed>|null
     */
    private ?array $data;

    /**
     * Stable gateway error code on failure.
     *
     * @var string
     */
    private string $errorCode;

    /**
     * Safe gateway error message on failure.
     *
     * @var string
     */
    private string $errorMessage;

    /**
     * Optional retry-after seconds hint from the gateway.
     *
     * @var int|null
     */
    private ?int $retryAfter;

    /**
     * Gateway request correlation identifier.
     *
     * @var string
     */
    private string $requestId;

    /**
     * Optional related intent field slugs from input validation.
     *
     * @var string[]
     */
    private array $fields;

    /**
     * Create a normalized gateway response object.
     *
     * @since 3.0.0
     * @updated 3.0.0 Accept optional related field slugs for wizard UX.
     * @param bool                 $success      Success flag.
     * @param int                  $statusCode   HTTP status code.
     * @param array<string, mixed>|null $data    Success payload data.
     * @param string               $errorCode    Stable error code.
     * @param string               $errorMessage Safe error message.
     * @param int|null             $retryAfter   Retry hint in seconds.
     * @param string               $requestId    Gateway request identifier.
     * @param string[]             $fields       Related intent field slugs.
     */
    public function __construct(
        bool $success,
        int $statusCode,
        ?array $data,
        string $errorCode = '',
        string $errorMessage = '',
        ?int $retryAfter = null,
        string $requestId = '',
        array $fields = []
    ) {
        // Store normalized response properties.
        $this->success = $success;
        $this->statusCode = $statusCode;
        $this->data = $data;
        $this->errorCode = $errorCode;
        $this->errorMessage = $errorMessage;
        $this->retryAfter = $retryAfter;
        $this->requestId = $requestId;

        // Bound and sanitize optional field hints for AJAX consumers.
        $normalizedFields = [];
        foreach ($fields as $field) {
            $slug = sanitize_key((string) $field);
            if ('' === $slug || in_array($slug, $normalizedFields, true)) {
                continue;
            }
            $normalizedFields[] = $slug;
            if (12 <= count($normalizedFields)) {
                break;
            }
        }
        $this->fields = $normalizedFields;
    }

    /**
     * Determine whether the gateway call succeeded.
     *
     * @since 3.0.0
     * @return bool True on success.
     */
    public function isSuccess(): bool
    {
        // Expose the normalized success flag.
        return $this->success;
    }

    /**
     * Return the HTTP status code.
     *
     * @since 3.0.0
     * @return int HTTP status code.
     */
    public function getStatusCode(): int
    {
        // Expose the upstream HTTP status.
        return $this->statusCode;
    }

    /**
     * Return parsed success data.
     *
     * @since 3.0.0
     * @return array<string, mixed> Success payload.
     */
    public function getData(): array
    {
        // Return an empty array when no payload exists.
        return $this->data ?? [];
    }

    /**
     * Return the stable gateway error code.
     *
     * @since 3.0.0
     * @return string Error code slug.
     */
    public function getErrorCode(): string
    {
        // Expose the stable error slug.
        return $this->errorCode;
    }

    /**
     * Return the safe gateway error message.
     *
     * @since 3.0.0
     * @return string Error message.
     */
    public function getErrorMessage(): string
    {
        // Expose the sanitized upstream message.
        return $this->errorMessage;
    }

    /**
     * Return the retry-after hint when present.
     *
     * @since 3.0.0
     * @return int|null Retry seconds or null.
     */
    public function getRetryAfter(): ?int
    {
        // Expose optional throttling metadata.
        return $this->retryAfter;
    }

    /**
     * Return the gateway request correlation ID.
     *
     * @since 3.0.0
     * @return string Request identifier.
     */
    public function getRequestId(): string
    {
        // Expose the correlation identifier for support logs.
        return $this->requestId;
    }

    /**
     * Return related intent field slugs from validation failures.
     *
     * @since 3.0.0
     * @return string[] Field slugs.
     */
    public function getFields(): array
    {
        // Expose field hints for wizard highlighting.
        return $this->fields;
    }
}
