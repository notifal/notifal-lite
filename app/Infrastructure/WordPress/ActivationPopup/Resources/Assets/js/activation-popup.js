/**
 * Activation Popup JavaScript
 *
 * Handles the activation popup interactions and AJAX requests.
 * This script manages:
 * - Popup display and animations
 * - User interaction handlers (close, ESC key, click outside)
 * - Button click tracking
 * - AJAX dismissal of the popup
 *
 * @package Notifal\Infrastructure\WordPress\ActivationPopup\Resources\Assets\js
 * @since 2.0.0
 * @version 2.0.0
 * @author Hossein <hossein@notifal.com>
 */

(() => {
    'use strict';

    /**
     * ActivationPopup Object
     * Main object handling all popup functionality
     */
    const ActivationPopup = {
        /**
         * Initialize the popup
         * Sets up event handlers and shows the popup
         *
         * @since 2.0.0
         * @return {void}
         */
        init: function() {
            this.bindEvents();
            this.showPopup();
        },

        /**
         * Bind event handlers
         * Sets up all user interaction handlers for the popup
         *
         * @since 2.0.0
         * @return {void}
         */
        bindEvents: function() {
            const self = this;

            /**
             * Close button click handler
             * Closes the popup when user clicks the close button
             */
            document.addEventListener('click', function(e) {
                if (e.target.closest('.notifal-activation-popup-close')) {
                    e.preventDefault();
                    self.closePopup();
                }
            });

            /**
             * Click outside to close handler
             * Closes the popup when user clicks outside the popup content
             */
            document.addEventListener('click', function(e) {
                if (e.target.matches('.notifal-activation-popup-overlay')) {
                    self.closePopup();
                }
            });

            /**
             * ESC key press handler
             * Closes the popup when user presses the ESC key
             */
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && document.getElementById('notifal-activation-popup').offsetParent !== null) {
                    self.closePopup();
                }
            });

            /**
             * Button clicks handler
             * Tracks button clicks and closes popup after action button is clicked
             */
            document.addEventListener('click', function(e) {
                if (e.target.matches('.notifal-activation-popup-button')) {
                    const buttonId = e.target.getAttribute('data-button-id');
                    self.trackButtonClick(buttonId);

                    // Close popup after button click to allow navigation
                    self.closePopup(true);
                }
            });
        },

        /**
         * Show the activation popup
         * Displays the popup with animation and handles accessibility
         *
         * @since 2.0.0
         * @return {void}
         */
        showPopup: function() {
            // Get popup elements
            const overlay = document.querySelector('.notifal-activation-popup-overlay');
            const popup = document.querySelector('.notifal-activation-popup-content');

            // Exit if popup elements not found
            if (!popup) {
                return;
            }

            // Force display first, then add classes for animation
            overlay.style.display = 'block';
            popup.style.display = 'block';

            // Add show classes to trigger animations
            overlay.classList.add('show');
            popup.classList.add('show');

            // Focus management for accessibility
            // Set aria-hidden to false for screen readers
            popup.setAttribute('aria-hidden', 'false');

            // Focus the close button for keyboard navigation
            const closeBtn = popup.querySelector('.notifal-activation-popup-close');
            if (closeBtn) {
                closeBtn.focus();
            }

            // Prevent body scroll when popup is open
            document.body.classList.add('notifal-activation-popup-active');
        },

        /**
         * Close the activation popup
         * Closes the popup with animation and dismisses it via AJAX
         *
         * @since 2.0.0
         * @return {void}
         */
        closePopup: function(skipDismissCheck) {
            const self = this;

            // Get popup elements
            const overlay = document.querySelector('.notifal-activation-popup-overlay');
            const popup = document.querySelector('.notifal-activation-popup-content');

            // Always allow closing the popup (no permission check needed)

            // Add closing animation class
            popup.classList.add('hide');

            // Hide popup after animation completes (300ms)
            setTimeout(function() {
                // Remove show classes
                overlay.classList.remove('show');
                overlay.style.display = 'none';
                popup.classList.remove('show', 'hide');
                popup.style.display = 'none';

                // Restore body scroll
                document.body.classList.remove('notifal-activation-popup-active');

                // Dismiss popup via AJAX to save user preference
                self.dismissPopup();
            }, 300);
        },

        /**
         * Dismiss the popup via AJAX
         * Sends AJAX request to mark the popup as shown
         * Also removes the activation parameter from the URL
         *
         * @since 2.0.0
         * @return {void}
         */
        dismissPopup: function() {
            // Create form data for POST request
            const formData = new FormData();
            formData.append('action', 'notifal_dismiss_activation_popup');
            formData.append('nonce', notifalActivationPopup.nonce);

            // Send fetch request to dismiss popup
            fetch(notifalActivationPopup.ajaxUrl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove the activation parameter from URL after successful dismissal
                    // This prevents the popup from showing again on page refresh
                    if (window.history.replaceState) {
                        const url = new URL(window.location);
                        url.searchParams.delete('notifal_activation');
                        window.history.replaceState(null, null, url);
                    }
                }
            })
            .catch(error => {
                // Log error if dismissal failed - keep only critical errors
                if (error.name !== 'TypeError' && error.message.includes('network')) {
                    // Network errors are expected in some environments, don't log
                    return;
                }
            });
        },

        /**
         * Track button clicks for analytics
         * Can be extended to send data to analytics services
         *
         * @since 2.0.0
         * @param {string} buttonId - The button ID that was clicked
         * @return {void}
         */
        trackButtonClick: function(buttonId) {

        }
    };

    /**
     * Initialize when document is ready
     * DOM content loaded handler
     */
    document.addEventListener('DOMContentLoaded', function() {
        ActivationPopup.init();
    });

})();
