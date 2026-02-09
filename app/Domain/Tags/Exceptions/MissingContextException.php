<?php

namespace Notifal\Domain\Tags\Exceptions;

defined('ABSPATH') || exit;

/**
 * Class MissingContextException
 *
 * Thrown when a required context (product, order, user) is missing or not found.
 *
 * @package Notifal\Domain\Tags\Exceptions
 * @author Hossein <hossein@notifal.com>
 * @since 2.0.0
 */
class MissingContextException extends \RuntimeException
{
    /**
     * MissingContextException constructor.
     *
     * @param string          $message  Optional custom error message.
     * @param int             $code     Error code.
     * @param \Throwable|null $previous Previous throwable for chaining.
     * @since 2.0.0
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        \Throwable $previous = null
    ) {
        if (empty($message)) {
            $message = __('Required context is missing or not found.', 'notifal');
        }

        parent::__construct($message, $code, $previous);
    }
}
