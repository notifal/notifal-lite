<?php

namespace Notifal\Core\View;

defined('ABSPATH') || exit;

/**
 * ViewRenderer
 * Renders PHP views with injected data.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class ViewRenderer
{
    /**
     * Render a view.
     *
     * @param string $view Dot notation (e.g., "Templates.Admin.dashboard")
     * @param array $data Variables to extract into view scope
     * @return void
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public function render($view, array $data = [])
    {
        $resolver = notifal_app(ViewResolver::class);
        $file = $resolver->resolve($view);

        extract($data, EXTR_SKIP);
        include $file;
    }
}
