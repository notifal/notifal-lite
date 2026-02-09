<?php
/**
 * Sticky Menu Controller
 *
 * Handles sticky menu rendering and AJAX interactions.
 *
 * @package Notifal\Infrastructure\WordPress\StickyMenu\Infrastructure
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */

namespace Notifal\Infrastructure\WordPress\StickyMenu\Infrastructure;

use Notifal\Infrastructure\WordPress\StickyMenu\Domain\StickyMenu;
use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;

defined('ABSPATH') || exit;

/**
 * Class StickyMenuController
 */
class StickyMenuController
{
    /**
     * StickyMenu domain instance
     *
     * @var StickyMenu
     */
    private StickyMenu $sticky_menu;

    /**
     * Constructor
     *
     * @since 2.0.0
     */
    public function __construct()
    {
        $this->sticky_menu = new StickyMenu();
    }

    /**
     * Register hooks and actions
     *
     * @return void
     * @since 2.0.0
     */
    public static function register(): void
    {
        // Create instance for handling callbacks
        $instance = new self();

        // Admin hooks
        add_action(ActionHooks::ADMIN_PAGE_CONTENT_BEFORE, [$instance, 'renderStickyMenu'], 1);
    }

    /**
     * Render the sticky menu on admin pages
     *
     * @return void
     * @since 2.0.0
     */
    public function renderStickyMenu(): void
    {
        // Don't render during AJAX requests
        if (wp_doing_ajax()) {
            return;
        }

        $this->renderMenuHtml();
    }

    /**
     * Render the sticky menu HTML
     *
     * @return void
     * @since 2.0.0
     */
    private function renderMenuHtml(): void
    {
        $first_row_items = $this->sticky_menu->getFirstRowMenuItems();
        $second_row_items = $this->sticky_menu->getSecondRowMenuItems();
        ?>
        <div id="notifal-sticky-menu" class="notifal-sticky-menu">
            <!-- First Row -->
            <div class="notifal-sticky-menu-row notifal-sticky-menu-first-row">
                <div class="notifal-sticky-menu-container notifal-pointer">
                    <?php foreach ($first_row_items as $item): ?>
                        <?php $this->renderMenuItem($item); ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Second Row -->
            <div class="notifal-sticky-menu-row notifal-sticky-menu-second-row">
                <div class="notifal-sticky-menu-container">
                    <?php foreach ($second_row_items as $item): ?>
                        <?php $this->renderMenuItem($item); ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render a single menu item
     *
     * @param array $item Menu item configuration
     * @return void
     * @since 2.0.0
     */
    private function renderMenuItem(array $item): void
    {
        $classes = ['notifal-sticky-menu-item'];

        if (isset($item['type'])) {
            $classes[] = 'notifal-sticky-menu-item-' . $item['type'];
        }

        if (isset($item['primary']) && $item['primary']) {
            $classes[] = 'notifal-sticky-menu-item-primary';
        }

        // Add active class if item is active (current page)
        if ($this->sticky_menu->isMenuItemActive($item)) {
            $classes[] = 'notifal-sticky-menu-item-active';
        }

        $class_string = implode(' ', $classes);
        $attributes = '';

        // Add title attribute if provided
        if (isset($item['title'])) {
            $attributes .= ' title="' . esc_attr($item['title']) . '"';
        }

        // Handle different item types
        switch ($item['type']) {
            case 'logo':
                ?>
                <div class="<?php echo esc_attr($class_string); ?>"<?php echo $attributes; ?>>
                    <div class="notifal-sticky-menu-logo">
                        <?php
                        if (isset($item['svg']) && $item['svg'] !== '') {
                            $allowed_svg = [
                                'svg' => ['xmlns' => true, 'viewbox' => true, 'width' => true, 'height' => true, 'class' => true, 'fill' => true, 'aria-hidden' => true],
                                'mask' => ['id' => true, 'maskUnits' => true, 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'fill' => true],
                                'rect' => ['fill' => true, 'width' => true, 'height' => true, 'x' => true, 'y' => true, 'transform' => true],
                                'path' => ['d' => true, 'fill' => true, 'fill-rule' => true, 'clip-rule' => true, 'mask' => true, 'transform' => true],
                                'g' => ['clip-path' => true],
                                'defs' => [],
                                'linearGradient' => ['id' => true, 'x1' => true, 'y1' => true, 'x2' => true, 'y2' => true, 'gradientUnits' => true],
                                'stop' => ['stop-color' => true, 'offset' => true],
                                'clipPath' => ['id' => true],
                            ];
                            echo wp_kses($item['svg'], $allowed_svg);
                        }
                        ?>
                    </div>
                </div>
                <?php
                break;

            case 'link':
                if (!isset($item['url'])) {
                    break;
                }
                $url = $item['url'];
                // Only call admin_url if it's a relative path and not external
                if (!isset($item['external']) || !$item['external']) {
                    if (strpos($url, 'http') !== 0) {
                        $url = admin_url($url);
                    }
                }
                $target = isset($item['external']) && $item['external'] ? ' target="_blank" rel="noopener noreferrer"' : '';

                // If item is active, don't make it a link
                if (strpos($class_string, 'notifal-sticky-menu-item-active') !== false) {
                    ?>
                    <span class="<?php echo esc_attr($class_string); ?>"<?php echo $attributes; ?>>
                        <?php if (isset($item['icon'])): ?>
                            <span class="notifal-sticky-menu-icon notifal-icon-<?php echo esc_attr($item['icon']); ?>" aria-hidden="true"></span>
                        <?php endif; ?>
                        <span class="notifal-sticky-menu-text"><?php echo esc_html($item['text']); ?></span>
                    </span>
                    <?php
                } else {
                    ?>
                    <a href="<?php echo esc_url($url); ?>" class="<?php echo esc_attr($class_string); ?>"<?php echo $attributes . $target; ?>>
                        <?php if (isset($item['icon'])): ?>
                            <span class="notifal-sticky-menu-icon notifal-icon-<?php echo esc_attr($item['icon']); ?>" aria-hidden="true"></span>
                        <?php endif; ?>
                        <span class="notifal-sticky-menu-text"><?php echo esc_html($item['text']); ?></span>
                    </a>
                    <?php
                }
                break;

            case 'button':
                if (isset($item['action'])) {
                    // Handle action-based buttons (like what's new popup)
                    ?>
                    <button type="button" class="<?php echo esc_attr($class_string); ?>" data-action="<?php echo esc_attr($item['action']); ?>"<?php echo $attributes; ?>>
                        <?php if (isset($item['icon'])): ?>
                            <span class="notifal-sticky-menu-icon notifal-icon-<?php echo esc_attr($item['icon']); ?>" aria-hidden="true"></span>
                        <?php endif; ?>
                        <span class="notifal-sticky-menu-text"><?php echo esc_html($item['text']); ?></span>
                    </button>
                    <?php
                } elseif (isset($item['url'])) {
                    // Handle URL-based buttons
                    $url = isset($item['external']) && $item['external'] ? esc_url($item['url']) : admin_url($item['url']);
                    $target = isset($item['external']) && $item['external'] ? ' target="_blank" rel="noopener noreferrer"' : '';
                    ?>
                    <a href="<?php echo esc_url($url); ?>" class="<?php echo esc_attr($class_string); ?>"<?php echo $attributes . $target; ?>>
                        <?php if (isset($item['icon'])): ?>
                            <span class="notifal-sticky-menu-icon notifal-icon-<?php echo esc_attr($item['icon']); ?>" aria-hidden="true"></span>
                        <?php endif; ?>
                        <span class="notifal-sticky-menu-text"><?php echo esc_html($item['text']); ?></span>
                    </a>
                    <?php
                }
                break;

            case 'icon_link':
                if (!isset($item['url'])) {
                    break;
                }
                $url = $item['url'];
                // Only call admin_url if it's a relative path
                if (strpos($url, 'http') !== 0) {
                    $url = admin_url($url);
                }

                // If item is active, don't make it a link
                if (strpos($class_string, 'notifal-sticky-menu-item-active') !== false) {
                    ?>
                    <span class="<?php echo esc_attr($class_string); ?>"<?php echo $attributes; ?>>
                        <?php if (isset($item['icon'])): ?>
                            <span class="notifal-sticky-menu-icon notifal-icon-<?php echo esc_attr($item['icon']); ?>" aria-hidden="true"></span>
                        <?php endif; ?>
                        <span class="notifal-sticky-menu-text"><?php echo esc_html($item['text']); ?></span>
                    </span>
                    <?php
                } else {
                    ?>
                    <a href="<?php echo esc_url($url); ?>" class="<?php echo esc_attr($class_string); ?>"<?php echo $attributes; ?>>
                        <?php if (isset($item['icon'])): ?>
                            <span class="notifal-sticky-menu-icon notifal-icon-<?php echo esc_attr($item['icon']); ?>" aria-hidden="true"></span>
                        <?php endif; ?>
                        <span class="notifal-sticky-menu-text"><?php echo esc_html($item['text']); ?></span>
                    </a>
                    <?php
                }
                break;
        }
    }
}
