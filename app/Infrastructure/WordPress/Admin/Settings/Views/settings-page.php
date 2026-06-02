<?php

defined('ABSPATH') || exit;

use Notifal\Domain\Settings\Constants\Urls;
use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;

/**
 * Settings page main view
 * 
 * Renders the main settings page with tab navigation and content areas.
 * Pure view file with no business logic - only displays passed data.
 * 
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 * 
 * @var string $current_tab Current active tab
 * @var string $page_slug Settings page slug
 * @var string $nonce_action Nonce action name
 * @var string $nonce_name Nonce field name
 * @var string $nonce_value Nonce field value
 * @var array $tag_settings Tag settings data
 * @var array $available_tabs Available tabs configuration
 * @var bool $woocommerce_active Whether WooCommerce is active
 * @var bool $notifal_pro_active Whether Notifal Pro is activated and connected
 */

?>

<div class="wp-wrap notifal-admin-page">
    <div class="notifal-settings-dashboard">
    <?php do_action(ActionHooks::ADMIN_PAGE_CONTENT_BEFORE); ?>

        <!-- Global Messages Container for Toast Notifications -->
        <div id="notifal-global-messages" class="notifal-global-messages-container"></div>

        <!-- Dashboard Header -->
        <div class="notifal-dashboard-header">
            <div class="notifal-flex notifal-justify-between notifal-align-center">
                <div>
                    <h1 class="notifal-dashboard-title">
                        <?php echo esc_html__('Notifal Settings', 'notifal'); ?>
                    </h1>
                    <p class="notifal-dashboard-subtitle">
                        <?php echo esc_html__('Configure Notifal plugin settings and preferences', 'notifal'); ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php
        $settings_status = isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : '';
        $allowed_statuses = ['saved', 'error'];
        if ($settings_status !== '' && in_array($settings_status, $allowed_statuses, true)):
            $status_class = $settings_status === 'saved' ? 'notifal-success' : 'notifal-error';
        ?>
            <div class="notifal-alert <?php echo esc_attr($status_class); ?>">
                <?php if ($settings_status === 'saved'): ?>
                    <?php echo esc_html__('Settings saved successfully!', 'notifal'); ?>
                <?php else: ?>
                    <?php echo esc_html__('Error saving settings. Please try again.', 'notifal'); ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Settings Card -->
        <div class="notifal-card">
            <!-- Tab Navigation -->
            <nav class="notifal-nav-tab-wrapper">
                <?php foreach ($available_tabs as $tab_key => $tab_config): ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=' . $page_slug . '&tab=' . $tab_key)); ?>" 
                       class="notifal-nav-tab <?php echo esc_attr( $current_tab === $tab_key ? 'notifal-nav-tab-active' : '' ); ?>">
                        <?php echo esc_html($tab_config['label']); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <!-- Tab Content Container -->
            <div class="notifal-tab-content">
        <?php if ($current_tab === 'tags'): ?>
            <!-- Tags Settings Tab -->
            <div class="notifal-tab-panel" id="notifal-tags-panel">
                <div class="notifal-settings-section">
                    <h2 class="notifal-section-title">
                        <?php echo esc_html__('Tag Categories', 'notifal'); ?>
                    </h2>
                    <p class="notifal-section-description">
                        <?php echo esc_html__('Control which tag categories are available in tag selectors throughout Notifal. Disabled categories will not appear in Elementor or Block Editor tags section.', 'notifal'); ?>
                    </p>

                    <!-- Settings Form -->
                    <form method="post" class="notifal-settings-form">
                        <!-- AJAX handling - no action needed -->

                        <!-- Core WordPress Tags -->
                        <div class="notifal-settings-group">
                            <h3 class="notifal-group-title">
                                <?php echo esc_html__('Core WordPress Tags', 'notifal'); ?>
                                <span class="notifal-group-badge notifal-badge-core">
                                    <?php echo esc_html__('Always Available', 'notifal'); ?>
                                </span>
                            </h3>
                            <p class="notifal-group-description">
                                <?php echo esc_html__('These tag categories are part of core WordPress and are always available.', 'notifal'); ?>
                            </p>

                            <div class="notifal-settings-grid">
                                <!-- User Tags -->
                                <div class="notifal-setting-item">
                                    <label class="notifal-setting-label">
                                        <input type="checkbox" 
                                               name="user_tags_enabled" 
                                               value="1" 
                                               <?php checked($tag_settings['user_tags_enabled']); ?>
                                               class="notifal-setting-checkbox">
                                        <span class="notifal-setting-title">
                                            <?php echo esc_html__('User Tags', 'notifal'); ?>
                                        </span>
                                    </label>
                                    <p class="notifal-setting-description">
                                        <?php echo esc_html__('Tags for user information like {user_name}, {user_email}, etc.', 'notifal'); ?>
                                    </p>
                                </div>

                                <!-- Post Tags -->
                                <div class="notifal-setting-item">
                                    <label class="notifal-setting-label">
                                        <input type="checkbox" 
                                               name="post_tags_enabled" 
                                               value="1" 
                                               <?php checked($tag_settings['post_tags_enabled']); ?>
                                               class="notifal-setting-checkbox">
                                        <span class="notifal-setting-title">
                                            <?php echo esc_html__('Post Tags', 'notifal'); ?>
                                        </span>
                                    </label>
                                    <p class="notifal-setting-description">
                                        <?php echo esc_html__('Tags for blog posts like {post_title}, {post_content}, etc.', 'notifal'); ?>
                                    </p>
                                </div>

                                <!-- Page Tags -->
                                <div class="notifal-setting-item">
                                    <label class="notifal-setting-label">
                                        <input type="checkbox" 
                                               name="page_tags_enabled" 
                                               value="1" 
                                               <?php checked($tag_settings['page_tags_enabled']); ?>
                                               class="notifal-setting-checkbox">
                                        <span class="notifal-setting-title">
                                            <?php echo esc_html__('Page Tags', 'notifal'); ?>
                                        </span>
                                    </label>
                                    <p class="notifal-setting-description">
                                        <?php echo esc_html__('Tags for WordPress pages like {page_title}, {page_url}, etc.', 'notifal'); ?>
                                    </p>
                                </div>

                                <!-- Comment Tags - MOVED TO NOTIFAL PRO -->
                                <div class="notifal-setting-item <?php echo !$notifal_pro_active ? 'notifal-pro-feature notifal-pro-disabled' : ''; ?>">
                                    <label class="notifal-setting-label">
                                        <input type="checkbox"
                                               name="comment_tags_enabled"
                                               value="1"
                                               <?php checked($tag_settings['comment_tags_enabled']); ?>
                                               <?php disabled(!$notifal_pro_active); ?>
                                               class="notifal-setting-checkbox">
                                        <span class="notifal-setting-title">
                                            <?php echo esc_html__('Comment Tags', 'notifal'); ?>
                                            <?php if (!$notifal_pro_active): ?>
                                                <span class="notifal-pro-badge notifal-pro-badge-inline"><?php echo esc_html__('PRO', 'notifal'); ?></span>
                                            <?php endif; ?>
                                        </span>
                                    </label>
                                    <p class="notifal-setting-description">
                                        <?php echo esc_html__('Tags for comments like {comment_author}, {comment_content}, etc. Available in Notifal Pro.', 'notifal'); ?>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- WooCommerce Tags -->
                        <div class="notifal-settings-group">
                            <h3 class="notifal-group-title">
                                <?php echo esc_html__('WooCommerce Tags', 'notifal'); ?>
                                <span class="notifal-group-badge notifal-badge-plugin">
                                    <?php echo esc_html__('Requires WooCommerce', 'notifal'); ?>
                                </span>
                            </h3>
                            <p class="notifal-group-description">
                                <?php echo esc_html__('Tags for WooCommerce products, orders, cart, and e-commerce functionality. These tags require WooCommerce to be active and will be automatically disabled if WooCommerce is not available.', 'notifal'); ?>
                            </p>

                            <div class="notifal-settings-grid">
                                <!-- Product Tags (WooCommerce) -->
                                <div class="notifal-setting-item <?php echo !$woocommerce_active ? 'notifal-setting-disabled' : ''; ?>">
                                    <label class="notifal-setting-label">
                                        <input type="checkbox" 
                                               name="product_tags_enabled" 
                                               value="1" 
                                               <?php checked($tag_settings['product_tags_enabled'] && $woocommerce_active); ?>
                                               <?php disabled(!$woocommerce_active); ?>
                                               class="notifal-setting-checkbox">
                                        <span class="notifal-setting-title">
                                            <?php echo esc_html__('Product Tags', 'notifal'); ?>
                                            <?php if (!$woocommerce_active): ?>
                                                <span class="notifal-plugin-required">
                                                    (<?php echo esc_html__('WooCommerce Required', 'notifal'); ?>)
                                                </span>
                                            <?php endif; ?>
                                        </span>
                                    </label>
                                    <p class="notifal-setting-description">
                                        <?php echo esc_html__('Tags for WooCommerce products like {product_name}, {product_price}, etc.', 'notifal'); ?>
                                    </p>
                                </div>

                                <!-- Order Tags (WooCommerce) -->
                                <div class="notifal-setting-item <?php echo !$woocommerce_active ? 'notifal-setting-disabled' : ''; ?>">
                                    <label class="notifal-setting-label">
                                        <input type="checkbox" 
                                               name="order_tags_enabled" 
                                               value="1" 
                                               <?php checked($tag_settings['order_tags_enabled'] && $woocommerce_active); ?>
                                               <?php disabled(!$woocommerce_active); ?>
                                               class="notifal-setting-checkbox">
                                        <span class="notifal-setting-title">
                                            <?php echo esc_html__('Order Tags', 'notifal'); ?>
                                            <?php if (!$woocommerce_active): ?>
                                                <span class="notifal-plugin-required">
                                                    (<?php echo esc_html__('WooCommerce Required', 'notifal'); ?>)
                                                </span>
                                            <?php endif; ?>
                                        </span>
                                    </label>
                                    <p class="notifal-setting-description">
                                        <?php echo esc_html__('Tags for WooCommerce orders like {order_total}, {order_status}, etc.', 'notifal'); ?>
                                    </p>
                                </div>

                                <!-- Cart Tags (WooCommerce) -->
                                <div class="notifal-setting-item <?php echo !$woocommerce_active ? 'notifal-setting-disabled' : ''; ?>">
                                    <label class="notifal-setting-label">
                                        <input type="checkbox"
                                               name="cart_tags_enabled"
                                               value="1"
                                               <?php checked(!empty($tag_settings['cart_tags_enabled']) && $woocommerce_active); ?>
                                               <?php disabled(!$woocommerce_active); ?>
                                               class="notifal-setting-checkbox">
                                        <span class="notifal-setting-title">
                                            <?php echo esc_html__('Cart Tags', 'notifal'); ?>
                                            <?php if (!$woocommerce_active): ?>
                                                <span class="notifal-plugin-required">
                                                    (<?php echo esc_html__('WooCommerce Required', 'notifal'); ?>)
                                                </span>
                                            <?php endif; ?>
                                        </span>
                                    </label>
                                    <p class="notifal-setting-description">
                                        <?php echo esc_html__('Tags for the visitor cart like {cart_total}, {cart_item_count}, {cart_coupons}, etc.', 'notifal'); ?>
                                    </p>
                                </div>
                            </div>

                            <?php if (!$woocommerce_active): ?>
                                <div class="notifal-plugin-notice">
                                    <p>
                                        <strong><?php echo esc_html__('Note:', 'notifal'); ?></strong>
                                        <?php echo esc_html__('WooCommerce is not currently active. Product, Order, and Cart tags will be automatically disabled until WooCommerce is activated.', 'notifal'); ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Generated Custom Post Type Tags Section -->
                        <div class="notifal-settings-group <?php echo !$notifal_pro_active ? 'notifal-pro-feature notifal-pro-disabled notifal-pro-hidden' : ''; ?>" id="notifal-custom-posttype-tags-section">
                            <h3 class="notifal-group-title">
                                <?php echo esc_html__('Generated Custom Post Type Tags', 'notifal'); ?>
                                <span class="notifal-group-badge notifal-badge-generated">
                                    <?php echo esc_html__('Generated Tags', 'notifal'); ?>
                                </span>
                                <?php if (!$notifal_pro_active): ?>
                                    <span class="notifal-pro-badge notifal-pro-badge-inline"><?php echo esc_html__('PRO', 'notifal'); ?></span>
                                <?php endif; ?>
                            </h3>
                            <p class="notifal-group-description">
                                <?php echo esc_html__('Control which tag categories are available in tag selectors throughout Notifal. Disabled categories will not appear in Elementor or Block Editor tags section.', 'notifal'); ?>
                                <?php if (!$notifal_pro_active): ?>
                                    <?php echo esc_html__('This feature is available in Notifal Pro. Connect and activate your license to unlock generated custom post type tags.', 'notifal'); ?>
                                <?php endif; ?>
                            </p>

                            <div class="notifal-settings-grid" id="notifal-custom-posttype-tags-grid">
                                <!-- Custom post type tag checkboxes will be dynamically added here -->
                            </div>
                        </div>

                        <!-- Post Type Tag Generator Section -->
                        <div class="notifal-settings-group">
                            <h3 class="notifal-group-title">
                                <?php echo esc_html__('Post Type Tag Generator', 'notifal'); ?>
                                <span class="notifal-group-badge notifal-badge-feature">
                                    <?php echo esc_html__('Smart Tags', 'notifal'); ?>
                                </span>
                                <?php if (!$notifal_pro_active): ?>
                                    <span class="notifal-pro-badge notifal-pro-badge-inline"><?php echo esc_html__('PRO', 'notifal'); ?></span>
                                <?php endif; ?>
                            </h3>
                            <p class="notifal-group-description">
                                <?php echo esc_html__('Automatically generate tags for custom post types. Select post types to generate comprehensive tag sets including standard fields, custom meta fields, and taxonomies.', 'notifal'); ?>
                                <?php if (!$notifal_pro_active): ?>
                                    <br><strong><?php echo esc_html__('Note:', 'notifal'); ?></strong> <?php echo esc_html__('This feature is available in Notifal Pro. Connect and activate your license to unlock the post type tag generator.', 'notifal'); ?>
                                <?php endif; ?>
                            </p>

                                                    <!-- Post Type Discovery & Selection -->
                        <div class="notifal-posttype-generator">
                            <?php if ($notifal_pro_active): ?>
                            <!-- Saved Generated Tags Section -->
                            <div class="notifal-saved-tags-section notifal-pro-hidden" id="notifal-saved-tags-section">
                                <div class="notifal-section-header">
                                    <h4 class="notifal-section-title">
                                        <span class="notifal-icon notifal-icon-tags-fill"></span>
                                        <?php echo esc_html__('Saved Generated Tags', 'notifal'); ?>
                                    </h4>
                                </div>
                                <div class="notifal-saved-tags-grid" id="notifal-saved-tags-grid">
                                    <!-- Saved post types will be shown here -->
                                </div>
                            </div>

                            <!-- Post Type Selection Header -->
                            <div class="notifal-generator-header">
                                <h4 class="notifal-generator-title">
                                    <?php echo esc_html__('Select Post Types for Generation', 'notifal'); ?>
                                </h4>
                                <div class="notifal-generator-actions">
                                    <button type="button"
                                            class="notifal-button primary small notifal-pro-hidden"
                                            id="notifal-generate-tags"
                                            title="<?php echo esc_attr__('Generate and save tags for selected post types', 'notifal'); ?>"
                                            <?php disabled(!$notifal_pro_active); ?>>
                                        <span class="notifal-icon notifal-icon-tags-fill"></span>
                                        <?php echo esc_html__('Generate Tags', 'notifal'); ?>
                                    </button>
                                    <button type="button"
                                            class="notifal-button secondary small"
                                            id="notifal-refresh-posttypes"
                                            title="<?php echo esc_attr__('Refresh post type list', 'notifal'); ?>"
                                            <?php disabled(!$notifal_pro_active); ?>>
                                        <span class="notifal-icon notifal-icon-arrow-repeat"></span>
                                        <?php echo esc_html__('Refresh', 'notifal'); ?>
                                    </button>
                                </div>
                            </div>

                                <!-- Loading state -->
                                <div class="notifal-loading-state" id="notifal-posttype-loading">
                                    <div class="notifal-loading-spinner"></div>
                                    <p><?php echo esc_html__('Discovering post types...', 'notifal'); ?></p>
                                </div>

                                <!-- Post Type Grid -->
                                <div class="notifal-posttype-grid notifal-pro-hidden" id="notifal-posttype-grid">
                                    <!-- Post types will be loaded here via JavaScript -->
                                </div>
                            <?php else: ?>
                                <!-- Pro Upgrade Message -->
                                <div class="notifal-settings-grid">
                                    <div class="notifal-setting-item notifal-pro-upgrade-item">
                                        <div class="notifal-upgrade-content">
                                            <div class="notifal-upgrade-icon">
                                                <span class="notifal-icon notifal-icon-crown"></span>
                                            </div>
                                            <div class="notifal-upgrade-text">
                                                <h4 class="notifal-upgrade-title"><?php echo esc_html__('Unlock Custom Post Type Tags', 'notifal'); ?></h4>
                                                <p class="notifal-upgrade-description"><?php echo esc_html__('Generate dynamic tags for any custom post type automatically. Create comprehensive tag sets including standard fields, custom meta fields, and taxonomies.', 'notifal'); ?></p>
                                                <div class="notifal-upgrade-features">
                                                    <div class="notifal-feature-item">
                                                        <span class="notifal-icon notifal-icon-check"></span>
                                                        <?php echo esc_html__('Auto-discover meta fields', 'notifal'); ?>
                                                    </div>
                                                    <div class="notifal-feature-item">
                                                        <span class="notifal-icon notifal-icon-check"></span>
                                                        <?php echo esc_html__('Include taxonomies and terms', 'notifal'); ?>
                                                    </div>
                                                    <div class="notifal-feature-item">
                                                        <span class="notifal-icon notifal-icon-check"></span>
                                                        <?php echo esc_html__('Standard post fields', 'notifal'); ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="notifal-upgrade-action">
                                                <a href="<?php echo esc_url(Urls::getUpgradeUrl('custom_post_type_tags')); ?>"
                                                   class="notifal-button primary notifal-upgrade-button"
                                                   target="_blank"
                                                   rel="noopener noreferrer">
                                                    <span class="notifal-icon notifal-icon-rocket"></span>
                                                    <?php echo esc_html__('Upgrade to Notifal Pro', 'notifal'); ?>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                                                            <!-- Generated Tags Preview (Pending) -->
                            <div class="notifal-tags-preview-container notifal-pro-hidden" id="notifal-tags-preview-container">
                                <div class="notifal-preview-header">
                                    <h4 class="notifal-preview-title">
                                        <span class="notifal-icon notifal-icon-eye"></span>
                                        <?php echo esc_html__('Tag Preview (Pending Generation)', 'notifal'); ?>
                                    </h4>
                                    <div class="notifal-preview-actions">
                                        <button type="button"
                                                class="notifal-button secondary small"
                                                id="notifal-refresh-preview"
                                                title="<?php echo esc_attr__('Refresh tag preview with latest data', 'notifal'); ?>"
                                                <?php disabled(!$notifal_pro_active); ?>>
                                            <span class="notifal-icon notifal-icon-arrow-repeat"></span>
                                            <?php echo esc_html__('Refresh Preview', 'notifal'); ?>
                                        </button>
                                    </div>
                                </div>

                                <!-- Warning Message -->
                                <div class="notifal-message notifal-panel-alert-warning notifal-generate-warning-message">
                                    <span class="notifal-icon notifal-icon-alert"></span>
                                    <strong><?php echo esc_html( sprintf( __( 'These are preview tags only. Click %s button above to create and save them to your tag system.', 'notifal' ), '"' . __( 'Generate Tags', 'notifal' ) . '"' ) ); ?></strong>

                                </div>

                                    <!-- Preview Content -->
                                    <div class="notifal-tags-preview" id="notifal-tags-preview">
                                        <!-- Tag previews will be loaded here via JavaScript -->
                                    </div>

                                    <!-- Preview Summary -->
                                    <div class="notifal-preview-summary" id="notifal-preview-summary">
                                        <!-- Summary stats will be shown here -->
                                    </div>
                                </div>

                                <?php if ($notifal_pro_active): ?>
                                <!-- Generator Settings -->
                                <div class="notifal-generator-settings">
                                    <h4 class="notifal-generator-title">
                                        <?php echo esc_html__('Generation Options', 'notifal'); ?>
                                    </h4>

                                <div class="notifal-settings-grid">
                                        <!-- Meta Field Discovery -->
                                        <div class="notifal-setting-item">
                                            <label class="notifal-setting-label">
                                                <input type="checkbox"
                                                       name="auto_discover_meta"
                                                       value="1"
                                                       checked
                                                       class="notifal-setting-checkbox"
                                                       id="notifal-auto-discover"
                                                       <?php disabled(!$notifal_pro_active); ?>>
                                                <span class="notifal-setting-title">
                                                    <?php echo esc_html__('Auto-discover Meta Fields', 'notifal'); ?>
                                                </span>
                                            </label>
                                            <p class="notifal-setting-description">
                                                <?php echo esc_html__('Automatically find and include custom meta fields for each post type. Creates {posttype_meta_{key}} tags.', 'notifal'); ?>
                                            </p>
                                        </div>

                                        <!-- Include Taxonomies -->
                                        <div class="notifal-setting-item">
                                            <label class="notifal-setting-label">
                                                <input type="checkbox"
                                                       name="include_taxonomies"
                                                       value="1"
                                                       checked
                                                       class="notifal-setting-checkbox"
                                                       id="notifal-include-taxonomies"
                                                       <?php disabled(!$notifal_pro_active); ?>>
                                                <span class="notifal-setting-title">
                                                    <?php echo esc_html__('Include Taxonomies', 'notifal'); ?>
                                                </span>
                                            </label>
                                            <p class="notifal-setting-description">
                                                <?php echo esc_html__('Include taxonomy tags like categories and custom taxonomies for each post type.', 'notifal'); ?>
                                            </p>
                                </div>

                                        <!-- Auto-update -->
                                        <div class="notifal-setting-item">
                                            <label class="notifal-setting-label">
                                                <input type="checkbox"
                                                       name="auto_update_tags"
                                                       value="1"
                                                       checked
                                                       class="notifal-setting-checkbox"
                                                       id="notifal-auto-update"
                                                       <?php disabled(!$notifal_pro_active); ?>>
                                                <span class="notifal-setting-title">
                                                    <?php echo esc_html__('Auto-update Tags', 'notifal'); ?>
                                                </span>
                                            </label>
                                            <p class="notifal-setting-description">
                                                <?php echo esc_html__('Automatically regenerate tags when post type structure changes (new meta fields, taxonomies).', 'notifal'); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Form Actions -->
                        <div class="notifal-form-actions">
                            <button type="submit" class="notifal-button notifal-save-button" id="notifal-save-settings">
                                <span class="notifal-icon notifal-icon-check"></span>
                                <?php echo esc_html__('Save Settings', 'notifal'); ?>
                            </button>

                            <button type="button" class="notifal-button secondary"
                                    data-confirm="<?php echo esc_attr__('Are you sure you want to reset all tag settings to defaults?', 'notifal'); ?>"
                                    id="notifal-reset-settings">
                                <span class="notifal-icon notifal-icon-arrow-repeat"></span>
                                <?php echo esc_html__('Reset to Defaults', 'notifal'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        <?php elseif ($current_tab === 'onpage'): ?>
            <!-- OnPage Notifications Tab (Future) -->
            <div class="notifal-tab-panel" id="notifal-onpage-panel">
                <div class="notifal-coming-soon">
                    <h2><?php echo esc_html__('OnPage Notification Settings', 'notifal'); ?></h2>
                    <p><?php echo esc_html__('OnPage notification settings will be available in a future version.', 'notifal'); ?></p>
                </div>
            </div>

        <?php endif; ?>
            </div>
        </div>
    </div>
</div>
