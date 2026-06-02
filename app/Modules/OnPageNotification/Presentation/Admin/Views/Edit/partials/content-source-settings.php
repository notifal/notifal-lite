<?php

namespace Notifal\Modules\OnPageNotification\Presentation\Admin\Views\Edit\partials;

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\Support\PluginDetector;
use Notifal\Shared\AdminUI\Fields\FieldRenderer;
use Notifal\Modules\OnPageNotification\Presentation\Admin\Traits\RestrictionCardRendererTrait;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Content Source Settings Tab
 *
 * Handles the display and configuration of notification content source settings
 * including dynamic content restrictions, template analysis, and content filtering.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Modules\OnPageNotification\Presentation\Admin\Views\Edit\partials
 */
class ContentSourceSettingsRenderer
{
    use RestrictionCardRendererTrait;

    /**
     * Render the complete content source settings tab.
     *
     * @return void
     *
     * @since 2.0.0
     */
    public function render(): void
    {
        $tab = 'content_source';

        do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_BEFORE, $tab));
        $this->renderTabContent($tab);
        do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_AFTER, $tab));
    }

    /**
     * Render the main tab content.
     *
     * @param string $tab Tab identifier
     * @return void
     *
     * @since 2.0.0
     */
    private function renderTabContent(string $tab): void
    {
        ?>
        <div class="notifal-settings-section notifal-<?php echo esc_attr($tab); ?>-settings">
            <h1><?php esc_html_e('Content Source', 'notifal'); ?></h1>

            <div class="notifal-tab-panel-fields">
                <?php
                $this->renderTemplateAnalysisSection();
                $this->renderDynamicRestrictionsSection();
                ?>
            </div>
        </div>

        <script type="text/javascript">
            // Set global Pro status for JavaScript
            window.NotifalContentSourceProStatus = <?php echo PluginDetector::isNotifalProActive() ? 'true' : 'false'; ?>;

            // Set available comment statuses for dynamic filtering
            window.NotifalCommentStatuses = <?php echo wp_json_encode(get_comment_statuses()); ?>;
        </script>
        <?php
    }

    /**
     * Render the template analysis section.
     *
     * @return void
     *
     * @since 2.0.0
     */
    private function renderTemplateAnalysisSection(): void
    {
        ?>
        <!-- Template Analysis Section -->
        <div class="notifal-field-wrapper notifal-direction-column">
            <div class="notifal-template-analysis-content">
                <div class="notifal-analysis-status" id="notifal-template-analysis-status">
                    <div class="notifal-empty-state">
                        <div class="notifal-empty-icon">🔍</div>
                        <h4><?php esc_html_e('No Template Selected', 'notifal'); ?></h4>
                        <p><?php esc_html_e('Please select a template in the Template section to analyze its content and configure restrictions.', 'notifal'); ?></p>
                    </div>
                </div>

                <div class="notifal-detected-tags notifal-hidden" id="notifal-detected-tags">
                    <h4><?php esc_html_e('Detected Dynamic Tags', 'notifal'); ?></h4>
                    <div id="notifal-tags-list">
                        <!-- Tags will be populated via JavaScript -->
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render the dynamic content restrictions section.
     *
     * @return void
     *
     * @since 2.0.0
     * @since 2.3.5 Updated with "Show duplicate source" toggle.
     */
    private function renderDynamicRestrictionsSection(): void
    {
        ?>
        <!-- Dynamic Content Restrictions -->
        <div class="notifal-dynamic-restrictions notifal-hidden" id="notifal-dynamic-restrictions">
            <?php
            FieldRenderer::toggle(
                'allow_duplicate_source',
                false,
                __('Show duplicate source', 'notifal'),
                __('When disabled, each visitor session sees each source item only once per notification context until the pool is exhausted.', 'notifal'),
                [
                    'input' => [
                        'value' => '1',
                    ],
                    'wrapper' => [
                        'id' => 'notifal-allow-duplicate-source-wrapper',
                    ],
                ]
            );
            ?>

            <div class="notifal-field-wrapper notifal-direction-column">
                <div class="notifal-field-header notifal-flex notifal-flex-row">
                    <label class="notifal-form-label"><?php esc_html_e('Dynamic Content Restrictions', 'notifal'); ?></label>
                    <?php FieldRenderer::tooltip(__('Configure restrictions to control which data is used for dynamic content. You can add multiple filters with AND/OR logic for complex filtering conditions.', 'notifal')); ?>
                </div>
            </div>

            <?php $this->renderAllRestrictionCards(); ?>
        </div>

        <?php if (!PluginDetector::isNotifalProActive()): ?>
            <?php $this->renderProUpgradeBox(); ?>
        <?php endif; ?>
        <?php
    }

    /**
     * Render all restriction cards using the trait.
     *
     * @return void
     *
     * @since 2.0.0
     */
    private function renderAllRestrictionCards(): void
    {
        // Order restrictions
        $this->renderRestrictionCard([
            'id' => 'notifal-order-restrictions',
            'title' => __('Order Restrictions', 'notifal'),
            'tooltip' => 'Control which orders are used for dynamic content like {order_id}, {order_meta_*}, etc.',
            'filter_type' => 'order',
            'is_pro_only' => false,
            'empty_message' => sprintf( __( 'No filters configured. Click %s to create a condition.', 'notifal' ), '"' . __( 'Add Filter', 'notifal' ) . '"' )
        ]);

        // Product restrictions
        $this->renderRestrictionCard([
            'id' => 'notifal-product-restrictions',
            'title' => __('Product Restrictions', 'notifal'),
            'tooltip' => 'Control which products are used for dynamic content like {product_name}, {product_link}, etc.',
            'filter_type' => 'product',
            'is_pro_only' => false,
            'empty_message' => sprintf( __( 'No filters configured. Click %s to create a condition.', 'notifal' ), '"' . __( 'Add Filter', 'notifal' ) . '"' )
        ]);

        // User tags information
        $this->renderInformationCard([
            'id' => 'notifal-user-restrictions',
            'title' => __('User Tags', 'notifal'),
            'content' => '
                <div class="notifal-info-message">
                    <div class="notifal-info-icon">
                        <span class="notifal-icon notifal-icon-info-circle size-20"></span>
                    </div>
                    <div class="notifal-info-content">
                        <p>' . esc_html__("The user tags will be replaced with the currently logged-in user's data. Also, make sure you set the display rules to show the notification only to logged-in users; otherwise, the tags will be replaced with empty strings.", 'notifal') . '</p>
                    </div>
                </div>'
        ]);

        // Post restrictions
        $this->renderRestrictionCard([
            'id' => 'notifal-post-restrictions',
            'title' => __('Post Restrictions', 'notifal'),
            'tooltip' => 'Control which posts are used for dynamic content like {post_title}, {post_content}, etc.',
            'filter_type' => 'post',
            'is_pro_only' => false,
            'empty_message' => sprintf( __( 'No filters configured. Click %s to create a condition.', 'notifal' ), '"' . __( 'Add Filter', 'notifal' ) . '"' )
        ]);

        // Page restrictions
        $this->renderRestrictionCard([
            'id' => 'notifal-page-restrictions',
            'title' => __('Page Restrictions', 'notifal'),
            'tooltip' => 'Control which pages are used for dynamic content like {page_title}, {page_content}, etc.',
            'filter_type' => 'page',
            'is_pro_only' => false,
            'empty_message' => sprintf( __( 'No filters configured. Click %s to create a condition.', 'notifal' ), '"' . __( 'Add Filter', 'notifal' ) . '"' )
        ]);

        // Comment restrictions (Pro only)
        $this->renderRestrictionCard([
            'id' => 'notifal-comment-restrictions',
            'title' => __('Comment Restrictions', 'notifal'),
            'tooltip' => 'Control which comments are used for dynamic content like {comment_content}, {comment_author}, etc. Comment features are available in Notifal Pro.',
            'filter_type' => 'comment',
            'is_pro_only' => !PluginDetector::isNotifalProActive(),
            'empty_message' => sprintf( __( 'No filters configured. Click %s to create a condition.', 'notifal' ), '"' . __( 'Add Filter', 'notifal' ) . '"' )
        ]);

        // Custom post type restrictions
        $this->renderRestrictionCard([
            'id' => 'notifal-custom-posttype-restrictions',
            'title' => __('Custom Post Type Restrictions', 'notifal'),
            'tooltip' => 'Control which custom post type items are used for dynamic content like {cpt_title}, {cpt_content}, etc.',
            'filter_type' => 'custom_posttype',
            'is_pro_only' => false,
            'empty_message' => sprintf( __( 'No filters configured. Click %s to create a condition.', 'notifal' ), '"' . __( 'Add Filter', 'notifal' ) . '"' )
        ]);
    }
}

// Instantiate and render
$contentSourceRenderer = new ContentSourceSettingsRenderer();
$contentSourceRenderer->render(); 