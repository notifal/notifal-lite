<?php

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\Support\PluginDetector;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\DisplayRulesService;
use Notifal\Shared\AdminUI\Fields\FieldRenderer;
use Notifal\Shared\Helpers\UserHelper;
use Notifal\Shared\Services\NotifalIconService;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Display Rules Settings Tab
 *
 * Handles the display and configuration of notification display rules settings
 * including rule types, conditions, combination logic, and Pro feature restrictions.
 * Provides UI for managing when and where notifications should appear.
 *
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 * @package Notifal\Modules\OnPageNotification\Presentation\Admin\Views\Edit\partials
 */

$tab = 'display_rules';

do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_BEFORE, $tab));

/**
 * Initialize display rules state and determine UI visibility
 * Based on existing rules data, control which sections are shown to the user
 */
$hasExistingRules = false;
$existingRulesData = [];

if (isset($notification_data['display_rules_data']) && !empty($notification_data['display_rules_data'])) {
    $existingRulesData = $notification_data['display_rules_data'];
    $hasExistingRules = !empty($existingRulesData);
}

// Determine CSS classes for conditional UI display
$emptyStateClass = $hasExistingRules ? 'notifal-hidden' : '';
$rulesFieldsClass = $hasExistingRules ? '' : 'notifal-hidden';
$rulesSummaryClass = $hasExistingRules ? '' : 'notifal-hidden';
?>

<div class="notifal-settings-section notifal-display-rules-settings">

    <h1><?php esc_html_e( 'Display Rules', 'notifal' ); ?></h1>

    <!-- Empty State Message -->
    <div class="notifal-empty-rules-message <?php echo esc_attr($emptyStateClass); ?>" id="notifal-empty-rules-message">
        <div class="notifal-empty-state">
            <div class="notifal-empty-icon">🎯</div>
            <h3><?php esc_html_e( 'No Display Rules Set', 'notifal' ); ?></h3>
            <p><?php esc_html_e( "You haven't defined any display rules yet. If you want this notification to appear under specific conditions, please add a rule.", 'notifal' ); ?></p>
            <button type="button" class="notifal-button primary" id="notifal-show-rule-form">
                <?php esc_html_e( 'Add Your First Rule', 'notifal' ); ?>
            </button>
        </div>
    </div>

    <!-- Rules Configuration Section -->
    <div class="notifal-display-rules-fields <?php echo esc_attr($rulesFieldsClass); ?>">

        <!-- Active Display Rules Preview -->
        <div class="notifal-display-rules-summary notifal-mt-20 <?php echo esc_attr($rulesSummaryClass); ?>" id="notifal-display-rules-summary">
            <div class="notifal-summary-header">
                <div class="notifal-flex notifal-flex-row notifal-align-center">
                    <h3 class="notifal-mb-10"><?php esc_html_e( 'Active Rules', 'notifal' ); ?></h4>
                    <span><?php FieldRenderer::tooltip( sprintf( __( 'Click on any rule box to edit it. Use the %s button to view detailed information about each rule.', 'notifal' ), '"' . __( 'Show Details', 'notifal' ) . '"' ) ); ?></span>
                </div>
                <span class="notifal-summary-count" id="notifal-rules-count"><?php echo esc_html(count($existingRulesData)); ?></span>
            </div>
            
            <!-- Rule Combination Logic Selector - PRO FEATURE -->
            <div class="notifal-rule-combination-logic notifal-mb-16 <?php echo count($existingRulesData) > 1 ? 'notifal-pro-visible' : 'notifal-pro-hidden'; ?>" id="notifal-rule-combination-logic">
                <div class="notifal-logic-selector">
                    <div class="notifal-field-header notifal-flex notifal-flex-row">
                        <label for="rule_combination_logic" class="notifal-logic-label">
                            <?php esc_html_e( 'Rule Combination Logic:', 'notifal' ); ?>
                            <?php if ( ! PluginDetector::isNotifalProActive() ) : ?>
                                <span class="notifal-pro-badge notifal-pro-badge-inline"><?php esc_html_e( 'PRO', 'notifal' ); ?></span>
                            <?php endif; ?>
                        </label>
                        <?php FieldRenderer::tooltip( __( 'OR: Notification shows if any single rule matches. AND: Notification shows only if all rules match simultaneously. Multiple rules and logic combinations are Pro features.', 'notifal' ) ); ?>
                    </div>
                    <?php if (PluginDetector::isNotifalProActive()): ?>
                    <select id="rule_combination_logic" name="rule_combination_logic" class="notifal-logic-select">
                        <option value="OR" <?php selected(isset($notification_data['rule_combination_logic']) ? $notification_data['rule_combination_logic'] : 'OR', 'OR'); ?>><?php esc_html_e( 'OR - Show if ANY rule matches', 'notifal' ); ?></option>
                        <option value="AND" <?php selected(isset($notification_data['rule_combination_logic']) ? $notification_data['rule_combination_logic'] : 'OR', 'AND'); ?>><?php esc_html_e( 'AND - Show if ALL rules match', 'notifal' ); ?></option>
                    </select>
                    <?php else: ?>
                    <div class="notifal-pro-upsell-inline">
                        <p class="notifal-text-description"><?php esc_html_e('Multiple rules and AND/OR logic are available in Notifal Pro. Upgrade to unlock advanced display rule combinations.', 'notifal'); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="notifal-rule-list" id="notifal-display-rule-list">
                <!-- Rules will be added here via JS -->
            </div>
        </div>

        <!-- Rule Configuration Form -->
        <div class="notifal-rule-configuration">
            <h4><?php esc_html_e( 'Add New Rule', 'notifal' ); ?></h4>
            
            <!-- Rule Type Selector -->
            <?php
            FieldRenderer::select(
                'display_rule_type',
                DisplayRulesService::getRuleTypeOptions(),
                'post_type',
                __( 'Target Type', 'notifal' ),
                __( 'Choose the type of rule based on your target. Pages, Posts, and Products are convenience options that automatically switch to Post Type and configure the appropriate settings. Post Type allows you to select from all available post types. Pro features require Notifal Pro.', 'notifal' )
            );
            ?>

            <!-- Rule Conditions -->
            <div class="notifal-display-rules-conditions">

                <!-- Pages -->
                <div class="notifal-display-condition-section notifal-display-pages notifal-hidden">
                    <?php
                    FieldRenderer::select(
                        'target_pages_mode',
                        [
                            [ 'value' => 'exclude', 'label' => __( 'All Pages Except', 'notifal' ) ],
                            [ 'value' => 'specific', 'label' => __( 'Only Specific Page(s)', 'notifal' ) ],
                        ],
                        'exclude',
                        __( 'Page Visibility', 'notifal' ),
                        __( 'Choose whether to show the notification on all pages except selected ones, or only on specific pages.', 'notifal' )
                    );

                    FieldRenderer::ajaxSearch(
                        'target_pages',
                        [],
                        __( 'Select Pages', 'notifal' ),
                        __( 'Search and select the pages you want to target. Start typing to search.', 'notifal' )
                    );
                    ?>
                </div>

                <!-- Posts -->
                <div class="notifal-display-condition-section notifal-display-posts notifal-hidden">
                    <?php
                    FieldRenderer::select(
                        'target_posts_mode',
                        [
                            [ 'value' => 'exclude', 'label' => __( 'All Posts Except', 'notifal' ) ],
                            [ 'value' => 'specific', 'label' => __( 'Only Specific Post(s)', 'notifal' ) ],
                        ],
                        'exclude',
                        __( 'Post Visibility', 'notifal' ),
                        __( 'Choose whether to show the notification on all posts except selected ones, or only on specific posts.', 'notifal' )
                    );

                    FieldRenderer::ajaxSearch(
                        'target_posts',
                        [],
                        __( 'Select Posts', 'notifal' ),
                        __( 'Search and select the posts you want to target. Start typing to search.', 'notifal' ),
                        'post'
                    );
                    ?>
                </div>

                <!-- Products -->
                <div class="notifal-display-condition-section notifal-display-products notifal-hidden">
                    <?php
                    FieldRenderer::select(
                        'target_products_mode',
                        [
                            [ 'value' => 'exclude', 'label' => __( 'All Products Except', 'notifal' ) ],
                            [ 'value' => 'specific', 'label' => __( 'Only Specific Product(s)', 'notifal' ) ],
                        ],
                        'exclude',
                        __( 'Product Visibility', 'notifal' ),
                        __( 'Choose whether to show the notification on all products except selected ones, or only on specific products.', 'notifal' )
                    );

                    FieldRenderer::ajaxSearch(
                        'target_products',
                        [],
                        __( 'Select Products', 'notifal' ),
                        __( 'Search and select the products you want to target. Start typing to search.', 'notifal' ),
                        'product'
                    );
                    ?>
                </div>

                <!-- Post Type -->
                <div class="notifal-display-condition-section notifal-display-post_type">
                    <?php
                    FieldRenderer::select(
                        'target_post_type_visibility',
                        [
                            [ 'value' => 'all', 'label' => __( 'All Post Types', 'notifal' ) ],
                            [ 'value' => 'exclude', 'label' => __( 'All Post Types Except', 'notifal' ) ],
                            [ 'value' => 'specific', 'label' => __( 'Only Specific Post Type(s)', 'notifal' ) ],
                        ],
                        'all',
                        __( 'Post Type Visibility', 'notifal' ),
                        __( 'Choose how post types should be filtered for this notification.', 'notifal' )
                    );
                    ?>

                    <!-- Post Type Selection - Only shown when not "All Post Types" -->
                    <div class="notifal-post-type-selection notifal-hidden">
                        <?php
                        FieldRenderer::ajaxSearch(
                            'target_post_types[]',
                            [], // Empty array for selected items
                            __( 'Select Post Types', 'notifal' ),
                            __( 'Search and select post types to include or exclude from this notification.', 'notifal' ),
                            'post_type'
                        );
                        ?>

                        <!-- Post Items Visibility - Only shown when post types are selected -->
                        <div class="notifal-post-items-visibility notifal-hidden">
                            <?php
                            FieldRenderer::select(
                                'target_post_items_visibility',
                                [
                                    [ 'value' => 'all', 'label' => __( 'All Items', 'notifal' ) ],
                                    [ 'value' => 'exclude', 'label' => __( 'All Items Except', 'notifal' ) ],
                                    [ 'value' => 'specific', 'label' => __( 'Only Specific Items', 'notifal' ) ],
                                ],
                                'all',
                                __( 'Post Items Visibility', 'notifal' ),
                                __( 'Choose how individual posts within the selected post types should be filtered.', 'notifal' )
                            );
                            ?>

                            <!-- Post Items Selection - Only shown when not "All Items" -->
                            <div class="notifal-post-items-selection notifal-hidden">
                                <?php
                                FieldRenderer::ajaxSearch(
                                    'target_post_items[]',
                                    [], // Empty array for selected items
                                    __( 'Select Post Items', 'notifal' ),
                                    __( 'Search and select specific posts to include or exclude from this notification.', 'notifal' ),
                                    'post'
                                );
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Categories - PRO FEATURE -->
                <?php do_action('notifal_display_rules_categories_section'); ?>

                <!-- URL Matching - PRO FEATURE -->
                <?php do_action('notifal_display_rules_url_match_section'); ?>

                <!-- Users - PRO FEATURE -->
                <?php do_action('notifal_display_rules_users_section'); ?>

                <!-- Add Rule Button -->
                <div class="notifal-add-rule-container notifal-flex notifal-justify-end notifal-mt-20">
                    <button type="button" class="notifal-button primary notifal-add-display-rule">
                        <span class="notifal-button-icon">
                            <?php echo NotifalIconService::render('plus-circle', 16); ?>
                        </span>
                        <?php esc_html_e( 'Save Rule', 'notifal' ); ?>
                    </button>
                </div>

            </div>

        </div>

    </div>
</div>

<?php
do_action(sprintf(ActionHooks::ADMIN_ONPAGE_TAB_AFTER, $tab));
?>
