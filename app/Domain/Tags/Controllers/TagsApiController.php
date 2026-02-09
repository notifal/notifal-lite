<?php

namespace Notifal\Domain\Tags\Controllers;

use Notifal\Domain\Tags\TagManager;
use Notifal\Domain\Tags\TagsHelper;

defined('ABSPATH') || exit;

/**
 * Class TagsApiController
 *
 * Handles API logic for retrieving all registered tags grouped by category.
 *
 * @package Notifal\Domain\Tags\Controllers
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class TagsApiController
{
    /**
     * Get all registered tags grouped by category.
     *
     * Retrieves filtered tags from the TagManager and groups them by category
     * for API consumption. Each tag includes key, label, and description.
     *
     * @return array Array of tags grouped by category with tag data.
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public function getTags(): array
    {
        /** @var TagManager $manager */
        $manager = notifal_app(TagManager::class);

        return TagsHelper::groupByCategory($manager->allFiltered(), true);
    }
}
