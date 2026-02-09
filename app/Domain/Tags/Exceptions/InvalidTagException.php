<?php

namespace Notifal\Domain\Tags\Exceptions;

defined('ABSPATH') || exit;

/**
 * Class InvalidTagException
 *
 * Thrown when an invalid tag is created or accessed.
 *
 * @package Notifal\Domain\Tags\Exceptions
 * @author Hossein <hossein@notifal.com>
 * @since 2.0.0
 */
class InvalidTagException extends \InvalidArgumentException
{
    /**
     * InvalidTagException constructor.
     *
     * @param string $message
     * @param int    $code
     * @param \Throwable|null $previous
     * @since 2.0.0
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        \Throwable $previous = null
    ) {
        if (empty($message)) {
            $message = __('Invalid Tag operation.', 'notifal');
        }

        parent::__construct($message, $code, $previous);
    }

}
