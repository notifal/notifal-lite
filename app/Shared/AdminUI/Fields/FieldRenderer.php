<?php

namespace Notifal\Shared\AdminUI\Fields;

use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;
use Notifal\Infrastructure\WordPress\Hooks\FilterHooks;
use Notifal\Shared\Services\NotifalIconService;
use Notifal\Shared\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class FieldRenderer
 * Central renderer for all standard UI field components in admin.
 *
 * @since 2.0.0
 * @package Notifal\Shared\AdminUI\Fields
 * @author Hossein <hossein@notifal.com>
 */
class FieldRenderer {

    /**
     * Render tooltip.
     *
     * Renders a tooltip either as an inline wrapper (hover on the target element)
     * or as a question mark icon with the tooltip box.
     *
     * @param string $tooltip Tooltip content.
     * @param array  $attributes Optional HTML attributes for the wrapper (e.g. ['data-position' => 'top'])
     * @param bool   $inline If true, tooltip is shown when hovering the parent element.
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function tooltip(string $tooltip = '', array $attributes = [], bool $inline = false): void {
        if (!$tooltip) return;

        $attr = self::buildAttributes($attributes);

        if ($inline) {
            // Inline mode: tooltip attaches directly to parent
            echo '<div class="notifal-tooltip-inline"' . $attr . '>';
            echo '<div class="notifal-tooltip-box">' . esc_html($tooltip) . '</div>';
            echo '</div>';
        } else {
            // Default mode: question mark icon with tooltip
            echo '<div class="notifal-tooltip-wrapper"' . $attr . '>';
            // Use NotifalIconService for the tooltip icon
            echo '<span class="notifal-tooltip-icon" tabindex="0">' . NotifalIconService::render('question-circle', 16) . '</span>';
            echo '<div class="notifal-tooltip-box">' . esc_html($tooltip) . '</div>';
            echo '</div>';
        }
    }

    /**
     * Render enhanced tooltip with visual examples.
     *
     * Renders a tooltip with visual examples and animations to help users understand
     * the different display types and their behaviors.
     *
     * @param string $tooltip Tooltip content.
     * @param string $visual_example Visual example HTML content.
     * @param array  $attributes Optional HTML attributes for the wrapper.
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function enhancedTooltip(string $tooltip = '', string $visual_example = '', array $attributes = []): void {
        if (!$tooltip && !$visual_example) return;

        $attr = self::buildAttributes($attributes);

        echo '<div class="notifal-tooltip-wrapper notifal-enhanced-tooltip"' . $attr . '>';
        echo '<span class="notifal-tooltip-icon" tabindex="0">' . NotifalIconService::render('question-circle', 16) . '</span>';
        echo '<div class="notifal-tooltip-box notifal-enhanced-tooltip-box">';
        
        if ($tooltip) {
            echo '<div class="notifal-tooltip-content">' . esc_html($tooltip) . '</div>';
        }
        
        if ($visual_example) {
            echo '<div class="notifal-tooltip-visual">' . wp_kses_post($visual_example) . '</div>';
        }
        
        echo '</div>';
        echo '</div>';
    }

    /**
     * Render dynamic tooltip that updates based on field changes.
     *
     * Creates an inline tooltip that can be updated dynamically via JavaScript.
     * Perfect for fields where tooltip content depends on the selected value.
     *
     * @param string $id Unique identifier for the tooltip.
     * @param string $default_content Default tooltip content.
     * @param array  $attributes Optional HTML attributes for the wrapper.
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function dynamicTooltip(string $id, string $default_content = '', array $attributes = []): void {
        if (!$default_content) return;

        $attr = self::buildAttributes($attributes);
        $attr .= ' data-tooltip-id="' . esc_attr($id) . '"';

        echo '<div class="notifal-tooltip-wrapper notifal-dynamic-tooltip"' . $attr . '>';
        echo '<span class="notifal-tooltip-icon" tabindex="0">' . NotifalIconService::render('question-circle', 16) . '</span>';
        echo '<div class="notifal-tooltip-box notifal-dynamic-tooltip-box">';
        echo '<div class="notifal-tooltip-content">' . esc_html($default_content) . '</div>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Render a select dropdown field with dynamic tooltip support.
     *
     * @param string $id Field ID and name.
     * @param array $options Array of ['value' => ..., 'label' => ...]
     * @param string $selected Selected value.
     * @param string $label Label text.
     * @param string $tooltip Tooltip text.
     * @param array $attributes Optional HTML attributes ['input' => [], 'wrapper' => []]
     * @param bool $dynamic_tooltip Whether to use dynamic tooltip that updates with selection.
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function select(
        string $id,
        array $options,
        string $selected = '',
        string $label = '',
        string $tooltip = '',
        array $attributes = [],
        bool $dynamic_tooltip = false
    ): void {
        $options = apply_filters(FilterHooks::FIELD_SELECT_OPTIONS . $id, $options);
        do_action(ActionHooks::FIELD_SELECT_BEFORE);
        do_action(ActionHooks::FIELD_SELECT_BEFORE . $id);

        $wrapper_attrs = self::buildAttributes($attributes['wrapper'] ?? []);
        $input_attrs   = self::buildAttributes($attributes['input'] ?? []);
        
        // Handle class merging for wrapper
        $wrapper_classes = 'notifal-field-wrapper notifal-direction-column';
        if (isset($attributes['wrapper']['class'])) {
            $wrapper_classes .= ' ' . $attributes['wrapper']['class'];
        }

        echo '<div class="' . esc_attr($wrapper_classes) . '"' . $wrapper_attrs . '>';
        if ($label) {
            echo '<div class="notifal-field-header notifal-flex notifal-flex-row">';
            echo '<label class="notifal-form-label" for="' . esc_attr($id) . '">' . esc_html($label) . '</label>';
            
            if ($dynamic_tooltip) {
                // Use dynamic tooltip for fields that need content updates
                self::dynamicTooltip($id . '_tooltip', $tooltip);
            } else {
                // Use regular tooltip for static content
                self::tooltip($tooltip);
            }
            
            echo '</div>';
        }

        echo '<select class="notifal-select"' . ($dynamic_tooltip ? ' data-dynamic-tooltip="true"' : '') . ' id="' . esc_attr($id) . '" name="' . esc_attr($id) . '"' . $input_attrs . '>';
        foreach ($options as $opt) {
            $disabled_attr = isset($opt['disabled']) && $opt['disabled'] ? ' disabled="disabled"' : '';
            printf(
                '<option value="%1$s"%2$s%3$s>%4$s</option>',
                esc_attr($opt['value']),
                selected($opt['value'], $selected, false),
                $disabled_attr,
                esc_html($opt['label'])
            );
        }
        echo '</select>';
        echo '</div>';

        do_action(ActionHooks::FIELD_SELECT_AFTER);
        do_action(ActionHooks::FIELD_SELECT_AFTER . $id);
    }

    /**
     * Render a text input field.
     *
     * @param string $id Input ID and name.
     * @param string $value Default value.
     * @param string $label Label text.
     * @param string $tooltip Tooltip text.
     * @param array $attributes HTML attributes ['input' => [], 'wrapper' => []]
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function textInput(
        string $id,
        string $value = '',
        string $label = '',
        string $tooltip = '',
        array $attributes = []
    ): void {
        do_action(ActionHooks::FIELD_TEXT_BEFORE);
        do_action(ActionHooks::FIELD_TEXT_BEFORE . $id);

        $wrapper_attrs = self::buildAttributes($attributes['wrapper'] ?? []);
        $input_attrs   = self::buildAttributes($attributes['input'] ?? []);

        echo '<div class="notifal-field-wrapper notifal-direction-column"' . $wrapper_attrs . '>';
        if ($label) {
            echo '<div class="notifal-field-header notifal-flex notifal-flex-row">';
            echo '<label class="notifal-form-label" for="' . esc_attr($id) . '">' . esc_html($label) . '</label>';
            self::tooltip($tooltip);
            echo '</div>';
        }

        echo '<input type="text" id="' . esc_attr($id) . '" name="' . esc_attr($id) . '" value="' . esc_attr($value) . '" class="notifal-text-input"' . $input_attrs . ' />';
        echo '</div>';

        do_action(ActionHooks::FIELD_TEXT_AFTER);
        do_action(ActionHooks::FIELD_TEXT_AFTER . $id);
    }

    /**
     * Render a textarea field.
     *
     * @param string $id Input ID and name.
     * @param string $value Default value.
     * @param string $label Label text.
     * @param string $tooltip Tooltip text.
     * @param array $attributes HTML attributes ['input' => [], 'wrapper' => []]
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function textarea(
        string $id,
        string $value = '',
        string $label = '',
        string $tooltip = '',
        array $attributes = []
    ): void {
        do_action(ActionHooks::FIELD_TEXTAREA_BEFORE);
        do_action(ActionHooks::FIELD_TEXTAREA_BEFORE . $id);

        $wrapper_attrs = self::buildAttributes($attributes['wrapper'] ?? []);
        $input_attrs   = self::buildAttributes($attributes['input'] ?? []);

        echo '<div class="notifal-field-wrapper notifal-direction-column"' . $wrapper_attrs . '>';
        if ($label) {
            echo '<div class="notifal-field-header notifal-flex notifal-flex-row">';
            echo '<label class="notifal-form-label" for="' . esc_attr($id) . '">' . esc_html($label) . '</label>';
            self::tooltip($tooltip);
            echo '</div>';
        }

        echo '<textarea id="' . esc_attr($id) . '" name="' . esc_attr($id) . '" class="notifal-textarea-input"' . $input_attrs . '>' . esc_textarea($value) . '</textarea>';
        echo '</div>';

        do_action(ActionHooks::FIELD_TEXTAREA_AFTER);
        do_action(ActionHooks::FIELD_TEXTAREA_AFTER . $id);
    }

    /**
     * Render a date input field.
     *
     * @param string $id Input ID and name.
     * @param string $value Default value (YYYY-MM-DD format).
     * @param string $label Label text.
     * @param string $tooltip Tooltip text.
     * @param array $attributes HTML attributes ['input' => [], 'wrapper' => []]
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function dateInput(
        string $id,
        string $value = '',
        string $label = '',
        string $tooltip = '',
        array $attributes = []
    ): void {
        do_action(ActionHooks::FIELD_DATE_BEFORE);
        do_action(ActionHooks::FIELD_DATE_BEFORE . $id);

        $wrapper_attrs = self::buildAttributes($attributes['wrapper'] ?? []);
        $input_attrs   = self::buildAttributes($attributes['input'] ?? []);

        echo '<div class="notifal-field-wrapper notifal-direction-column"' . $wrapper_attrs . '>';
        if ($label) {
            echo '<div class="notifal-field-header notifal-flex notifal-flex-row">';
            echo '<label class="notifal-form-label" for="' . esc_attr($id) . '">' . esc_html($label) . '</label>';
            self::tooltip($tooltip);
            echo '</div>';
        }

        echo '<input type="date" class="notifal-input notifal-date-input" id="' . esc_attr($id) . '" name="' . esc_attr($id) . '" value="' . esc_attr($value) . '"' . $input_attrs . ' />';
        echo '</div>';

        do_action(ActionHooks::FIELD_DATE_AFTER);
        do_action(ActionHooks::FIELD_DATE_AFTER . $id);
    }

    /**
     * Convert associative array of attributes to string.
     *
     * @param array $attributes
     * @return string
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private static function buildAttributes(array $attributes): string {
        $result = '';
        foreach ($attributes as $key => $val) {
            // Skip class attribute as it's handled separately
            if ($key !== 'class') {
                $result .= ' ' . esc_attr($key) . '="' . esc_attr($val) . '"';
            }
        }
        return $result;
    }

    /**
     * Render a toggle switch input.
     *
     * @param string $id
     * @param bool $checked
     * @param string $label
     * @param string $tooltip
     * @param array $attributes HTML attributes ['input' => [], 'wrapper' => []]
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function toggle(
        string $id,
        bool $checked = false,
        string $label = '',
        string $tooltip = '',
        array $attributes = []
    ): void {
        do_action(ActionHooks::FIELD_TOGGLE_BEFORE);
        do_action(ActionHooks::FIELD_TOGGLE_BEFORE . $id);

        $wrapper_attrs = self::buildAttributes($attributes['wrapper'] ?? []);
        $input_attrs   = self::buildAttributes($attributes['input'] ?? []);

        echo '<div class="notifal-field-wrapper notifal-direction-row"' . $wrapper_attrs . '>';

        if ($label) {
            echo '<label class="notifal-form-label" for="' . esc_attr($id) . '">' . esc_html($label) . '</label>';
        }

        echo '<div class="notifal-toggle-container">';
        echo '<input type="checkbox" id="' . esc_attr($id) . '" name="' . esc_attr($id) . '" class="notifal-toggle-input" ' . checked($checked, true, false) . $input_attrs . ' />';
        echo '<label class="notifal-toggle-slider" for="' . esc_attr($id) . '"></label>';
        echo '</div>';

        self::tooltip($tooltip);
        echo '</div>';

        do_action(ActionHooks::FIELD_TOGGLE_AFTER);
        do_action(ActionHooks::FIELD_TOGGLE_AFTER . $id);
    }

    /**
     * Render a multi-select dropdown input.
     *
     * @param string $name
     * @param array $options
     * @param array $selected
     * @param string $label
     * @param string $tooltip
     * @param array $attributes HTML attributes ['input' => [], 'wrapper' => []]
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function multiSelect(
        string $name,
        array $options = [],
        array $selected = [],
        string $label = '',
        string $tooltip = '',
        array $attributes = []
    ): void {
        do_action(ActionHooks::FIELD_MULTI_SELECT_BEFORE, $name);

        $wrapper_attrs = self::buildAttributes($attributes['wrapper'] ?? []);
        $input_attrs   = self::buildAttributes($attributes['input'] ?? []);

        echo '<div class="notifal-field-wrapper notifal-direction-column"' . $wrapper_attrs . '>';

        if ($label) {
            echo '<div class="notifal-field-header notifal-flex notifal-flex-row">';
            echo '<label class="notifal-form-label" for="' . esc_attr($name) . '">' . esc_html($label) . '</label>';
            self::tooltip($tooltip);
            echo '</div>';
        }

        echo '<select id="' . esc_attr($name) . '" name="' . esc_attr($name) . '[]" multiple="multiple" class="notifal-multi-select-input"' . $input_attrs . '>';
        
        // Handle both formats: associative array [value => label] and array of objects [['value' => x, 'label' => y]]
        foreach ($options as $key => $option) {
            $value = '';
            $text = '';
            $disabled = false;

            if (is_array($option) && isset($option['value']) && isset($option['label'])) {
                // New format: ['value' => 'x', 'label' => 'y']
                $value = $option['value'];
                $text = $option['label'];
                $disabled = isset($option['disabled']) ? $option['disabled'] : false;
            } else {
                // Legacy format: [value => label]
                $value = $key;
                $text = $option;
            }

            $disabled_attr = $disabled ? ' disabled="disabled"' : '';
            printf(
                '<option value="%s" %s%s>%s</option>',
                esc_attr($value),
                selected(in_array($value, $selected, true), true, false),
                $disabled_attr,
                esc_html($text)
            );
        }
        echo '</select>';
        echo '</div>';

        do_action(ActionHooks::FIELD_MULTI_SELECT_AFTER, $name);
    }

    /**
     * Render AJAX-powered selector input.
     *
     * @param string $name
     * @param array $selected
     * @param string $label
     * @param string $tooltip
     * @param string $post_type
     * @param array $attributes HTML attributes ['input' => [], 'wrapper' => []]
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function ajaxSearch(
        string $name,
        array $selected = [],
        string $label = '',
        string $tooltip = '',
        string $post_type = 'page',
        array $attributes = []
    ): void {
        do_action(ActionHooks::FIELD_AJAX_SEARCH_BEFORE, $name);

        $wrapper_attrs = self::buildAttributes($attributes['wrapper'] ?? []);
        $input_attrs   = self::buildAttributes($attributes['input'] ?? []);

        echo '<div class="notifal-field-wrapper notifal-direction-column"' . $wrapper_attrs . '>';

        if ($label) {
            echo '<div class="notifal-field-header notifal-flex notifal-flex-row">';
            echo '<label class="notifal-form-label" for="' . esc_attr($name) . '">' . esc_html($label) . '</label>';
            self::tooltip($tooltip);
            echo '</div>';
        }

        echo '<div id="' . esc_attr($name) . '" class="notifal-full-width notifal-ajax-search" data-name="' . esc_attr($name) . '" data-post-type="' . esc_attr($post_type) . '"' . $input_attrs . '>';

        echo '<div class="notifal-search-wrapper">';
        echo '<div class="notifal-ajax-search-selected">';
        foreach ($selected as $post_id) {
            $post = Helper::getPostSafe($post_id);
            if (! $post) continue;
            echo '<div class="notifal-selected-item" data-id="' . esc_attr($post->ID) . '">';
            echo '<span class="notifal-selected-label">' . esc_html($post->post_title) . '</span>';
            echo '<input type="hidden" name="' . esc_attr($name) . '[]" value="' . esc_attr($post->ID) . '" />';
            echo '<button type="button" class="notifal-remove-selected">';
            echo NotifalIconService::render('x-circle', 12);
            echo '</button>';
            echo '</div>';
        }
        echo '</div>';
        echo '<input type="text" class="notifal-ajax-search-input" placeholder="' . esc_attr__('Type at least 3 letters to start searching.', 'notifal') . '" autocomplete="off" />';
        echo '</div>';

        echo '<div class="notifal-ajax-search-box ajaxnotifal-ajax-search-loader" style="display: none;">' . esc_html__('Searching', 'notifal') . '</div>';
        echo '<div class="notifal-ajax-search-box ajaxnotifal-ajax-search-no-results" style="display: none;">' . esc_html__('No results found!', 'notifal') . '</div>';
        echo '<div class="notifal-ajax-search-box ajaxnotifal-ajax-search-results" style="display: none;"></div>';
        echo '</div>';
        echo '</div>';

        do_action(ActionHooks::FIELD_AJAX_SEARCH_AFTER, $name);
    }

    /**
     * Render a badge-style selector UI with add/remove and clearable support.
     *
     * @param string $name Field name.
     * @param array $options Available badge options [slug => label].
     * @param array $selected Selected slugs.
     * @param string $label Field label text.
     * @param string $tooltip Tooltip description.
     * @param bool $allow_add Allow users to add new badges.
     * @param bool $allow_clear Show remove icon for active badges.
     * @param array $attributes HTML attributes ['wrapper' => []]
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function badgeSelector(
        string $name,
        array $options,
        array $selected = [],
        string $label = '',
        string $tooltip = '',
        bool $allow_add = false,
        bool $allow_clear = true,
        array $attributes = []
    ): void {
        $wrapper_attrs = self::buildAttributes($attributes['wrapper'] ?? []);

        echo '<div class="notifal-field-wrapper notifal-direction-column"' . $wrapper_attrs . '>';

        if ($label) {
            echo '<div class="notifal-field-header notifal-flex notifal-flex-row">';
            echo '<label class="notifal-form-label" for="' . esc_attr($name) . '">' . esc_html($label) . '</label>';
            self::tooltip($tooltip);
            echo '</div>';
        }

        echo '<div class="notifal-label-badges" id="' . esc_attr($name) . '_container" data-name="' . esc_attr($name) . '" data-clear="' . ( $allow_clear ? '1' : '0' ) . '">';

        foreach ($options as $slug => $data) {
            $is_active = in_array($slug, $selected, true);
            $class     = $is_active ? 'active' : '';
            echo '<div class="notifal-label-badge notifal-flex notifal-align-center ' . esc_attr($class) . '" data-slug="' . esc_attr($slug) . '" data-term-id="' . esc_attr($data['id']) . '">';
            echo esc_html($data['name']);
            echo '<input type="hidden" name="' . esc_attr($name) . '[]" value="' . esc_attr($slug) . '" ' . ( $is_active ? '' : 'disabled' ) . '>';

            if ($allow_clear) {
                echo '<button type="button" class="notifal-remove-badge" aria-label="' . esc_attr__('Remove label', 'notifal') . '">';
                echo NotifalIconService::render('x-circle', 12);
                echo '</button>';
            }

            echo '</div>';
        }

        if ($allow_add) {
            echo '<div class="notifal-label-add-wrapper">';
            echo '<input type="text" class="notifal-input" id="' . esc_attr($name) . '_new_input" placeholder="' . esc_attr__('Add new...', 'notifal') . '" />';
            echo '<button type="button" class="notifal-label-add-btn" id="' . esc_attr($name) . '_add_btn" data-loading-text="' . esc_attr__('Adding...', 'notifal') . '">';
            echo NotifalIconService::render('plus-circle', 16);
            echo '</button>';
            echo '</div>';
        }

        echo '</div>'; // .notifal-label-badges
        echo '</div>'; // .notifal-field-wrapper
    }

    /**
     * Render a number input field.
     *
     * @param string $id Field ID and name.
     * @param int|float $value Default value.
     * @param string $label Label text.
     * @param string $tooltip Tooltip text.
     * @param array $attributes Optional HTML attributes ['input' => [], 'wrapper' => []]
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function numberInput(
        string $id,
        $value = 0,
        string $label = '',
        string $tooltip = '',
        array $attributes = []
    ): void {
        do_action(ActionHooks::FIELD_NUMBER_BEFORE);
        do_action(ActionHooks::FIELD_NUMBER_BEFORE . $id);

        $wrapper_attrs = self::buildAttributes($attributes['wrapper'] ?? []);
        $input_attrs   = self::buildAttributes($attributes['input'] ?? []);

        echo '<div class="notifal-field-wrapper notifal-direction-column"' . $wrapper_attrs . '>';
        if ($label) {
            echo '<div class="notifal-field-header notifal-flex notifal-flex-row">';
            echo '<label class="notifal-form-label" for="' . esc_attr($id) . '">' . esc_html($label) . '</label>';
            self::tooltip($tooltip);
            echo '</div>';
        }

        echo '<input type="number" class="notifal-input" id="' . esc_attr($id) . '" name="' . esc_attr($id) . '" value="' . esc_attr($value) . '"' . $input_attrs . '>';
        echo '</div>';

        do_action(ActionHooks::FIELD_NUMBER_AFTER, $id);
    }

    /**
     * Render a color picker field.
     *
     * @param string $id Field ID and name.
     * @param string $value Default color value (hex or rgba).
     * @param string $label Label text.
     * @param string $tooltip Tooltip text.
     * @param array $attributes Optional HTML attributes ['input' => [], 'wrapper' => []]
     * @param bool $alpha Whether to support alpha/transparency (RGBA).
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function colorPicker(
        string $id,
        string $value = '#000000',
        string $label = '',
        string $tooltip = '',
        array $attributes = [],
        bool $alpha = false
    ): void {
        do_action(ActionHooks::FIELD_COLOR_BEFORE);
        do_action(ActionHooks::FIELD_COLOR_BEFORE . $id);

        $wrapper_attrs = self::buildAttributes($attributes['wrapper'] ?? []);
        $input_attrs   = self::buildAttributes($attributes['input'] ?? []);

        // Check if alpha support is requested via parameter or data-alpha attribute
        $supports_alpha = $alpha || isset($attributes['input']['data-alpha']);

        echo '<div class="notifal-field-wrapper notifal-direction-column"' . $wrapper_attrs . '>';
        if ($label) {
            echo '<div class="notifal-field-header notifal-flex notifal-flex-row">';
            echo '<label class="notifal-form-label" for="' . esc_attr($id) . '">' . esc_html($label) . '</label>';
            self::tooltip($tooltip);
            echo '</div>';
        }

        echo '<div class="notifal-color-picker-wrapper"' . ($supports_alpha ? ' data-alpha="true"' : '') . '>';

        if ($supports_alpha) {
            // Parse RGBA value for initial setup
            $rgba_parts = self::parseRgbaValue($value);
            $hex_color = self::rgbaToHex($rgba_parts);

            // RGBA color picker with visual picker + alpha slider
            echo '<div class="notifal-rgba-picker">';
            echo '<input type="color" class="notifal-color-picker notifal-rgba-color" value="' . esc_attr($hex_color) . '"' . $input_attrs . '>';
            echo '<div class="notifal-alpha-wrapper">';
            echo '<label class="notifal-alpha-label">' . esc_html__('Opacity', 'notifal') . '</label>';
            echo '<input type="range" class="notifal-alpha-slider" min="0" max="100" step="1" value="' . esc_attr($rgba_parts['a'] * 100) . '">';
            echo '<span class="notifal-alpha-value">' . esc_attr($rgba_parts['a'] * 100) . '%</span>';
            echo '</div>';
            echo '</div>';
            echo '<input type="text" class="notifal-color-input notifal-rgba-input" value="' . esc_attr($value) . '" placeholder="rgba(0,0,0,1)" />';
            echo '<input type="hidden" class="notifal-rgba-hidden" id="' . esc_attr($id) . '" name="' . esc_attr($id) . '" value="' . esc_attr($value) . '">';
        } else {
            // Standard hex color picker
            echo '<input type="color" class="notifal-color-picker" id="' . esc_attr($id) . '" name="' . esc_attr($id) . '" value="' . esc_attr($value) . '"' . $input_attrs . '>';
            echo '<input type="text" class="notifal-color-input" value="' . esc_attr($value) . '" placeholder="#000000" />';
        }

        echo '</div>';
        echo '</div>';

        do_action(ActionHooks::FIELD_COLOR_AFTER, $id);
    }

    /**
     * Parse RGBA value and return components.
     *
     * @param string $value RGBA or hex color value.
     * @return array Array with r, g, b, a keys.
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private static function parseRgbaValue(string $value): array {
        // Default to black with full opacity
        $default = ['r' => 0, 'g' => 0, 'b' => 0, 'a' => 0.5];

        // If it's already hex, convert to RGB with full alpha
        if (preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $value)) {
            $hex = ltrim($value, '#');
            if (strlen($hex) === 3) {
                $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
            }
            return [
                'r' => hexdec(substr($hex, 0, 2)),
                'g' => hexdec(substr($hex, 2, 2)),
                'b' => hexdec(substr($hex, 4, 2)),
                'a' => 1
            ];
        }

        // Parse RGBA format
        if (preg_match('/rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)(?:\s*,\s*([0-9.]+))?\s*\)/', $value, $matches)) {
            return [
                'r' => (int) $matches[1],
                'g' => (int) $matches[2],
                'b' => (int) $matches[3],
                'a' => isset($matches[4]) ? (float) $matches[4] : 1
            ];
        }

        return $default;
    }

    /**
     * Convert RGBA components to hex color.
     *
     * @param array $rgba Array with r, g, b, a keys.
     * @return string Hex color value.
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    private static function rgbaToHex(array $rgba): string {
        return sprintf('#%02x%02x%02x',
            max(0, min(255, $rgba['r'])),
            max(0, min(255, $rgba['g'])),
            max(0, min(255, $rgba['b']))
        );
    }

    /**
     * Render a range slider field.
     *
     * @param string $id Field ID and name.
     * @param int $value Default value.
     * @param string $label Label text.
     * @param string $tooltip Tooltip text.
     * @param array $attributes Optional HTML attributes ['input' => [], 'wrapper' => []]
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function rangeSlider(
        string $id,
        int $value = 50,
        string $label = '',
        string $tooltip = '',
        array $attributes = []
    ): void {
        do_action(ActionHooks::FIELD_RANGE_BEFORE);
        do_action(ActionHooks::FIELD_RANGE_BEFORE . $id);

        $wrapper_attrs = self::buildAttributes($attributes['wrapper'] ?? []);
        $input_attrs   = self::buildAttributes($attributes['input'] ?? []);

        echo '<div class="notifal-field-wrapper notifal-direction-column"' . $wrapper_attrs . '>';
        if ($label) {
            echo '<div class="notifal-field-header notifal-flex notifal-flex-row">';
            echo '<label class="notifal-form-label" for="' . esc_attr($id) . '">' . esc_html($label) . '</label>';
            self::tooltip($tooltip);
            echo '</div>';
        }

        echo '<div class="notifal-range-wrapper">';
        echo '<input type="range" class="notifal-range-slider" id="' . esc_attr($id) . '" name="' . esc_attr($id) . '" value="' . esc_attr($value) . '"' . $input_attrs . '>';
        echo '<span class="notifal-range-value">' . esc_html($value) . '</span>';
        echo '</div>';
        echo '</div>';

        do_action(ActionHooks::FIELD_RANGE_AFTER, $id);
    }

    /**
     * Render a media upload field.
     *
     * @param string $id Field ID and name.
     * @param string $value Default media URL.
     * @param string $label Label text.
     * @param string $tooltip Tooltip text.
     * @param array $attributes Optional HTML attributes ['input' => [], 'wrapper' => [], 'button' => []]
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function mediaUpload(
        string $id,
        string $value = '',
        string $label = '',
        string $tooltip = '',
        array $attributes = []
    ): void {
        do_action(ActionHooks::FIELD_MEDIA_BEFORE);
        do_action(ActionHooks::FIELD_MEDIA_BEFORE . $id);

        $wrapper_attrs = self::buildAttributes($attributes['wrapper'] ?? []);
        $input_attrs   = self::buildAttributes($attributes['input'] ?? []);
        $button_attrs  = self::buildAttributes($attributes['button'] ?? []);

        echo '<div class="notifal-field-wrapper notifal-direction-column"' . $wrapper_attrs . '>';
        if ($label) {
            echo '<div class="notifal-field-header notifal-flex notifal-flex-row">';
            echo '<label class="notifal-form-label" for="' . esc_attr($id) . '">' . esc_html($label) . '</label>';
            self::tooltip($tooltip);
            echo '</div>';
        }

        echo '<div class="notifal-media-upload-wrapper">';
        echo '<input type="hidden" class="notifal-media-input" id="' . esc_attr($id) . '" name="' . esc_attr($id) . '" value="' . esc_attr($value) . '"' . $input_attrs . '>';
        echo '<div class="notifal-media-preview">';
        if ($value) {
            echo '<div class="notifal-media-item">';
            echo '<span class="notifal-media-filename">' . esc_html(basename($value)) . '</span>';
            echo '<button type="button" class="notifal-media-remove" data-field="' . esc_attr($id) . '">' . NotifalIconService::render('x-circle', 16) . '</button>';
            echo '</div>';
        }
        echo '</div>';
        echo '<button type="button" class="notifal-media-upload-btn" data-field="' . esc_attr($id) . '"' . $button_attrs . '>';
        echo NotifalIconService::render('upload', 16);
        echo '<span>' . esc_html__('Upload Media', 'notifal') . '</span>';
        echo '</button>';
        echo '</div>';
        echo '</div>';

        do_action(ActionHooks::FIELD_MEDIA_AFTER, $id);
    }

    /**
     * Render a checkbox group field.
     *
     * @param string $name Field name.
     * @param array $options Array of options [['value' => ..., 'label' => ...]]
     * @param array $selected Array of selected values.
     * @param string $label Field label text.
     * @param string $tooltip Tooltip text.
     * @param array $attributes Optional HTML attributes ['input' => [], 'wrapper' => []]
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function multiCheckbox(
        string $name,
        array $options = [],
        array $selected = [],
        string $label = '',
        string $tooltip = '',
        array $attributes = []
    ): void {
        do_action(ActionHooks::FIELD_MULTI_CHECKBOX_BEFORE, $name);

        $wrapper_attrs = self::buildAttributes($attributes['wrapper'] ?? []);

        echo '<div class="notifal-field-wrapper notifal-direction-column"' . $wrapper_attrs . '>';

        if ($label) {
            echo '<div class="notifal-field-header notifal-flex notifal-flex-row">';
            echo '<label class="notifal-form-label">' . esc_html($label) . '</label>';
            self::tooltip($tooltip);
            echo '</div>';
        }

        echo '<div class="notifal-checkbox-group">';
        
        foreach ($options as $option) {
            $value = '';
            $text = '';
            
            if (is_array($option) && isset($option['value']) && isset($option['label'])) {
                $value = $option['value'];
                $text = $option['label'];
            } else {
                continue; // Skip invalid options
            }
            
            $is_checked = in_array($value, $selected, true);
            $checkbox_id = $name . '_' . sanitize_key($value);
            
            echo '<label class="notifal-checkbox-label">';
            echo '<input type="checkbox" name="' . esc_attr($name) . '[]" value="' . esc_attr($value) . '" id="' . esc_attr($checkbox_id) . '"' . checked($is_checked, true, false) . ' />';
            echo '<span class="notifal-checkbox-custom"></span>';
            echo '<span class="notifal-checkbox-text">' . esc_html($text) . '</span>';
            echo '</label>';
        }
        
        echo '</div>';
        echo '</div>';

        do_action(ActionHooks::FIELD_MULTI_CHECKBOX_AFTER, $name);
    }

    /**
     * Render a single template search field with AJAX functionality.
     *
     * @param string $name Field name.
     * @param int $selectedId Selected template ID.
     * @param string $label Label text.
     * @param string $tooltip Tooltip text.
     * @param array $attributes Optional HTML attributes ['input' => [], 'wrapper' => []]
     *
     * @since 2.0.0
     * @author Hossein <hossein@notifal.com>
     */
    public static function singleTemplateSearch(
        string $name,
        int $selectedId = 0,
        string $label = '',
        string $tooltip = '',
        array $attributes = []
    ): void {
        do_action(ActionHooks::FIELD_AJAX_SEARCH_BEFORE, $name);

        $wrapper_attrs = self::buildAttributes($attributes['wrapper'] ?? []);
        $input_attrs   = self::buildAttributes($attributes['input'] ?? []);

        echo '<div class="notifal-field-wrapper notifal-direction-column"' . $wrapper_attrs . '>';

        if ($label) {
            echo '<div class="notifal-field-header notifal-flex notifal-flex-row">';
            echo '<label class="notifal-form-label" for="' . esc_attr($name) . '">' . esc_html($label) . '</label>';
            self::tooltip($tooltip);
            echo '</div>';
        }

        echo '<div id="' . esc_attr($name) . '" class="notifal-full-width notifal-single-template-search" data-name="' . esc_attr($name) . '" data-post-type="notifal_template"' . $input_attrs . '>';

        echo '<div class="notifal-search-wrapper">';
        
        // Selected template display
        echo '<div class="notifal-single-template-selected">';
        if ($selectedId > 0) {
            $template = Helper::getPostSafe($selectedId, 'notifal_template');
            if ($template) {
                echo '<div class="notifal-selected-template" data-id="' . esc_attr($selectedId) . '">';
                echo '<span class="notifal-selected-label">' . esc_html($template->post_title) . '</span>';
                echo '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr($selectedId) . '" />';
                echo '<button type="button" class="notifal-remove-selected">';
                echo NotifalIconService::render('x-circle', 12);
                echo '</button>';
                echo '</div>';
            }
        }
        echo '</div>';
        
        echo '<input type="text" class="notifal-single-template-search-input" placeholder="' . esc_attr__('Search templates...', 'notifal') . '" autocomplete="off" />';
        echo '</div>';

        echo '<div class="notifal-single-template-search-box notifal-single-template-search-loader" style="display: none;">' . esc_html__('Searching templates...', 'notifal') . '</div>';
        echo '<div class="notifal-single-template-search-box notifal-single-template-search-no-results" style="display: none;">' . esc_html__('No templates found!', 'notifal') . '</div>';
        echo '<div class="notifal-single-template-search-box notifal-single-template-search-results" style="display: none;"></div>';
        echo '</div>';
        echo '</div>';

        do_action(ActionHooks::FIELD_AJAX_SEARCH_AFTER, $name);
    }

}
