<?php

namespace Notifal\Modules\OnPageNotification\Contracts;

defined('ABSPATH') || exit;

/**
 * Interface LabelProviderInterface
 *
 * Contract for retrieving label options for notifications.
 *
 * @package Notifal\Modules\OnPageNotification\Contracts
 * @author Hossein <hossein@notifal.com>
 * @since 2.0.0
 */
interface LabelProviderInterface
{
    /**
     * Get label options for notifal_label taxonomy.
     *
     * @return array [slug => ['name' => string, 'id' => int]]
     * @since 2.0.0
     */
    public function getOptions(): array;
}
