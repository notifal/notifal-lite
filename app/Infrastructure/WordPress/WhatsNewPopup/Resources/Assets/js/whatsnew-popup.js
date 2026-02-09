/**
 * What's New Popup JavaScript
 *
 * Handles the what's new popup interactions and AJAX requests.
 * This script manages:
 * - Popup display and animations
 * - User interaction handlers (close, ESC key, click outside)
 * - Button click tracking
 * - AJAX dismissal of the popup
 * - Detection of plugin updates on plugins.php page
 *
 * @package Notifal\Infrastructure\WordPress\WhatsNewPopup\Resources\Assets\js
 * @since 2.0.0
 * @version 2.0.0
 * @author Notifal Team
 */

(() => {
    'use strict';

    /**
     * WhatsNewPopup Object
     * Main object handling all popup functionality
     */
    const WhatsNewPopup = {
        /**
         * Flag to track if we're monitoring for updates
         *
         * @since 2.0.0
         * @type {boolean}
         */
        isMonitoringUpdates: false,

    /**
     * Initialize the popup
     * Sets up event handlers and shows the popup
     *
     * @since 2.0.0
     * @return {void}
     */
    init: function() {
            this.bindEvents();

            // Only show popup automatically if it should be shown
            if (this.shouldShowPopup()) {
                this.showPopup();
            }

            // Monitor for plugin updates on plugins.php page
            this.monitorPluginUpdates();
        },

        /**
         * Check if popup should be shown automatically
         *
         * @since 2.0.0
         * @return {boolean}
         */
        shouldShowPopup: function() {
            // Check if popup should auto-show (set by PHP)
            const overlay = document.querySelector('.notifal-whatsnew-popup-overlay');
            return overlay !== null && overlay.getAttribute('data-auto-show') === 'true';
        },

        /**
         * Monitor for Notifal plugin updates on plugins.php
         * Detects when update completes and shows popup dynamically
         *
         * @since 2.0.0
         * @return {void}
         */
        monitorPluginUpdates: function() {
            const self = this;
            
            // Only run on plugins.php page
            const isPluginsPage = window.location.pathname.indexOf('plugins.php') !== -1;
            if (!isPluginsPage) {
                return;
            }

            // Set monitoring flag
            self.isMonitoringUpdates = true;

            /**
             * Listen for WordPress plugin update success event
             * WordPress triggers 'wp-plugin-update-success' event when update completes
             */
            document.addEventListener('wp-plugin-update-success', function(event) {
                // Check if it's Notifal plugin
                if (event.detail && event.detail.plugin) {
                    const pluginSlug = event.detail.plugin;
                    
                    // Check if this is the Notifal plugin
                    if (pluginSlug.indexOf('notifal/notifal.php') !== -1 || pluginSlug.indexOf('notifal') !== -1) {
                        // Wait a moment for WordPress to finish processing
                        setTimeout(function() {
                            self.checkAndShowPopupAfterUpdate();
                        }, 500);
                    }
                }
            });

            /**
             * Fallback: Monitor for successful update messages
             * WordPress adds success messages after plugin updates
             */
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    mutation.addedNodes.forEach(function(node) {
                        // Check if it's a notice element
                        if (node.nodeType === 1 && node.classList && node.classList.contains('notice-success')) {
                            const noticeText = node.textContent || '';
                            
                            // Check if it mentions Notifal update
                            if (noticeText.toLowerCase().indexOf('notifal') !== -1 && 
                                noticeText.toLowerCase().indexOf('update') !== -1) {
                                // Wait a moment then check for popup
                                setTimeout(function() {
                                    self.checkAndShowPopupAfterUpdate();
                                }, 500);
                            }
                        }
                    });
                });
            });

            // Start observing the document for changes
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        },

        /**
         * Check if popup should be shown after update and fetch/display it
         * Fetches popup data via AJAX and renders it dynamically
         *
         * @since 2.0.0
         * @return {void}
         */
        checkAndShowPopupAfterUpdate: function() {
            const self = this;

            // Don't check if popup already exists
            if (document.getElementById('notifal-whatsnew-popup')) {
                return;
            }

            // Fetch popup data via AJAX
            const xhr = new XMLHttpRequest();
            const url = notifalWhatsNewPopup.ajaxUrl + '?action=notifal_get_whatsnew_popup_data&nonce=' + notifalWhatsNewPopup.nonce;

            xhr.open('GET', url, true);

            xhr.onload = function() {
                if (xhr.status >= 200 && xhr.status < 300) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        
                        if (response.success && response.data) {
                            // Render popup dynamically
                            self.renderPopupDynamically(response.data);
                            
                            // Show the popup
                            setTimeout(function() {
                                self.showPopup();
                            }, 100);
                        }
                    } catch (e) {
                        // Silently fail - popup will show on next page load
                    }
                }
            };

            xhr.send();
        },

        /**
         * Render popup HTML dynamically
         * Creates and injects popup HTML into the page
         *
         * @since 2.0.0
         * @param {Object} data - Popup data from AJAX response
         * @return {void}
         */
        renderPopupDynamically: function(data) {
            // Create popup overlay
            const overlay = document.createElement('div');
            overlay.id = 'notifal-whatsnew-popup';
            overlay.className = 'notifal-whatsnew-popup-overlay';
            overlay.style.display = 'none';

            // Build popup HTML
            let popupHTML = '<div class="notifal-whatsnew-popup-content">';
            popupHTML += '<div class="notifal-whatsnew-popup-header">';
            popupHTML += '<button type="button" class="notifal-whatsnew-popup-close" aria-label="Close">';
            popupHTML += '<span class="dashicons dashicons-no"></span>';
            popupHTML += '</button>';
            popupHTML += '<div class="notifal-whatsnew-popup-welcome-icon" aria-hidden="true">';
            popupHTML += '<span class="notifal-popup-emoji">✨</span>';
            popupHTML += '</div>';
            popupHTML += '<h2>' + this.escapeHtml(data.title) + '</h2>';
            popupHTML += '</div>';
            popupHTML += '<div class="notifal-whatsnew-popup-body">';
            popupHTML += '<div class="notifal-whatsnew-popup-description">';
            popupHTML += data.content; // Content is already sanitized on server
            popupHTML += '</div>';
            popupHTML += '<div class="notifal-whatsnew-popup-actions">';
            
            // Add action buttons
            if (data.action_buttons && data.action_buttons.length > 0) {
                data.action_buttons.forEach(function(button) {
                    if (button.close) {
                        // Close button
                        popupHTML += '<button type="button" ';
                        popupHTML += 'class="notifal-whatsnew-popup-button notifal-whatsnew-popup-button-secondary" ';
                        popupHTML += 'data-button-id="' + button.id + '">';
                        popupHTML += '<span class="dashicons ' + button.icon + '"></span>';
                        popupHTML += button.text;
                        popupHTML += '</button>';
                    } else {
                        // Link button
                        popupHTML += '<a href="' + button.url + '" ';
                        popupHTML += 'class="notifal-whatsnew-popup-button';
                        if (button.primary) {
                            popupHTML += ' notifal-whatsnew-popup-button-primary';
                        }
                        if (button.external) {
                            popupHTML += ' notifal-whatsnew-popup-button-external';
                        }
                        popupHTML += '" ';
                        popupHTML += 'data-button-id="' + button.id + '"';
                        if (button.external) {
                            popupHTML += ' target="_blank" rel="noopener noreferrer"';
                        }
                        popupHTML += '>';
                        popupHTML += '<span class="dashicons ' + button.icon + '"></span>';
                        popupHTML += button.text;
                        popupHTML += '</a>';
                    }
                });
            }
            
            popupHTML += '</div>';
            popupHTML += '</div>';
            popupHTML += '</div>';

            overlay.innerHTML = popupHTML;
            
            // Append to body
            document.body.appendChild(overlay);
        },

        /**
         * Escape HTML to prevent XSS
         * Simple HTML escaping for titles
         *
         * @since 2.0.0
         * @param {string} text - Text to escape
         * @return {string} Escaped text
         */
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
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
                if (e.target.closest('.notifal-whatsnew-popup-close')) {
                    e.preventDefault();
                    self.closePopup();
                }
            });

            /**
             * Click outside to close handler
             * Closes the popup when user clicks outside the popup content
             */
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('notifal-whatsnew-popup-overlay')) {
                    self.closePopup();
                }
            });

            /**
             * ESC key press handler
             * Closes the popup when user presses the ESC key
             */
            document.addEventListener('keydown', function(e) {
                const popup = document.getElementById('notifal-whatsnew-popup');
                if (e.keyCode === 27 && popup && popup.offsetParent !== null) {
                    self.closePopup();
                }
            });

            /**
             * Button clicks handler
             * Tracks button clicks and handles close buttons vs navigation buttons
             */
            document.addEventListener('click', function(e) {
                const button = e.target.closest('.notifal-whatsnew-popup-button:not(.notifal-whatsnew-popup-button-close)');
                if (button) {
                    const buttonId = button.dataset.buttonId;
                    self.trackButtonClick(buttonId);

                    // Close popup after button click to allow navigation
                    self.closePopup(true);
                }
            });

            /**
             * Secondary button handler (Got it button)
             * Closes popup without navigation
             */
            document.addEventListener('click', function(e) {
                const button = e.target.closest('.notifal-whatsnew-popup-button-secondary');
                if (button) {
                    e.preventDefault();
                    const buttonId = button.dataset.buttonId;
                    self.trackButtonClick(buttonId);

                    // Close popup
                    self.closePopup();
                }
            });
        },

        /**
         * Show the what's new popup
         * Displays the popup with animation and handles accessibility
         *
         * @since 2.0.0
         * @return {void}
         */
        showPopup: function() {
            // Get popup elements
            const overlay = document.querySelector('.notifal-whatsnew-popup-overlay');
            const popup = document.querySelector('.notifal-whatsnew-popup-content');

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
            const closeBtn = popup.querySelector('.notifal-whatsnew-popup-close');
            if (closeBtn) {
                closeBtn.focus();
            }

            // Prevent body scroll when popup is open
            document.body.classList.add('notifal-whatsnew-popup-active');
        },

        /**
         * Close the what's new popup
         * Closes the popup with animation and dismisses it via AJAX
         *
         * @since 2.0.0
         * @param {boolean} skipDismissCheck Legacy parameter, always allows closing
         * @return {void}
         */
        closePopup: function(skipDismissCheck) {
            const self = this;

            // Get popup elements
            const overlay = document.querySelector('.notifal-whatsnew-popup-overlay');
            const popup = document.querySelector('.notifal-whatsnew-popup-content');

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
                document.body.classList.remove('notifal-whatsnew-popup-active');

                // Dismiss popup via AJAX to save user preference
                self.dismissPopup();
            }, 300);
        },

        /**
         * Dismiss the popup via AJAX
         * Sends AJAX request to mark the current version as shown
         *
         * @since 2.0.0
         * @return {void}
         */
        dismissPopup: function() {
            // Send AJAX request to dismiss popup
            const xhr = new XMLHttpRequest();
            const formData = new FormData();

            formData.append('action', 'notifal_dismiss_whatsnew_popup');
            formData.append('nonce', notifalWhatsNewPopup.nonce);

            xhr.open('POST', notifalWhatsNewPopup.ajaxUrl, true);

            xhr.onload = function() {
                if (xhr.status >= 200 && xhr.status < 300) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            // Successfully marked version as shown
                        } else {
                            // Dismissal failed - could add user notification here if needed
                        }
                    } catch (e) {
                        // JSON parse error - could add user notification here if needed
                    }
                } else {
                    // HTTP error - could add user notification here if needed
                }
            };

            xhr.onerror = function() {
                // Network error - could add user notification here if needed
            };

            xhr.send(formData);
        },

        /**
         * Public API: Manually show the what's new popup
         * This can be called from external code (like sticky menu)
         *
         * @since 2.0.0
         * @return {void}
         */
        showWhatsNewPopup: function() {
            // Check if popup already exists
            let overlay = document.querySelector('.notifal-whatsnew-popup-overlay');

            if (!overlay) {
                // Fetch popup data and render it
                this.fetchPopupDataAndShow();
            } else {
                // Popup already exists, just show it
                this.showPopup();
            }
        },

        /**
         * Fetch popup data via AJAX and show the popup
         *
         * @since 2.0.0
         * @return {void}
         */
        fetchPopupDataAndShow: function() {
            const self = this;

            // Show loading state if needed
            // For now, we'll fetch and render immediately

            fetch(notifalWhatsNewPopup.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams({
                    action: 'notifal_get_whatsnew_popup_data',
                    nonce: notifalWhatsNewPopup.nonce
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    self.renderPopup(data.data);
                    self.showPopup();
                } else {
                    console.error('Error fetching popup data:', data.data);
                }
            })
            .catch(error => {
                console.error('Error loading what\'s new popup:', error);
            });
        },

        /**
         * Render the popup HTML from data
         *
         * @param {Object} data - Popup data from server
         * @since 2.0.0
         * @return {void}
         */
        renderPopup: function(data) {
            // Create popup overlay
            const overlay = document.createElement('div');
            overlay.id = 'notifal-whatsnew-popup';
            overlay.className = 'notifal-whatsnew-popup-overlay';
            overlay.style.display = 'none';

            // Build popup HTML
            let popupHTML = '<div class="notifal-whatsnew-popup-content">';
            popupHTML += '<div class="notifal-whatsnew-popup-header">';
            popupHTML += '<button type="button" class="notifal-whatsnew-popup-close" aria-label="Close">';
            popupHTML += '<span class="dashicons dashicons-no"></span>';
            popupHTML += '</button>';
            popupHTML += '<div class="notifal-whatsnew-popup-welcome-icon" aria-hidden="true">';
            popupHTML += '<span class="notifal-popup-emoji">✨</span>';
            popupHTML += '</div>';
            popupHTML += '<h2>' + this.escapeHtml(data.title) + '</h2>';
            popupHTML += '</div>';
            popupHTML += '<div class="notifal-whatsnew-popup-body">';
            popupHTML += '<div class="notifal-whatsnew-popup-description">';
            popupHTML += data.content; // Content is already sanitized on server
            popupHTML += '</div>';
            popupHTML += '<div class="notifal-whatsnew-popup-actions">';

            // Add action buttons
            if (data.action_buttons && data.action_buttons.length > 0) {
                data.action_buttons.forEach(function(button) {
                    popupHTML += this.renderActionButton(button);
                }.bind(this));
            }

            popupHTML += '</div>';
            popupHTML += '</div>';
            popupHTML += '</div>';

            overlay.innerHTML = popupHTML;

            // Add to body
            document.body.appendChild(overlay);

            // Bind events
            this.bindPopupEvents();
        },

        /**
         * Render an action button
         *
         * @param {Object} button - Button configuration
         * @since 2.0.0
         * @return {string}
         */
        renderActionButton: function(button) {
            let buttonHTML = '';

            if (button.close) {
                // Close button
                buttonHTML += '<button type="button" ';
                buttonHTML += 'class="notifal-whatsnew-popup-button notifal-whatsnew-popup-button-secondary" ';
                buttonHTML += 'data-button-id="' + button.id + '">';
                buttonHTML += '<span class="dashicons ' + button.icon + '"></span>';
                buttonHTML += button.text;
                buttonHTML += '</button>';
            } else {
                // Link button
                buttonHTML += '<a href="' + button.url + '" ';
                buttonHTML += 'class="notifal-whatsnew-popup-button';
                if (button.primary) {
                    buttonHTML += ' notifal-whatsnew-popup-button-primary';
                }
                if (button.external) {
                    buttonHTML += ' notifal-whatsnew-popup-button-external';
                }
                buttonHTML += '" ';
                buttonHTML += 'data-button-id="' + button.id + '"';
                if (button.external) {
                    buttonHTML += ' target="_blank" rel="noopener noreferrer"';
                }
                buttonHTML += '>';
                buttonHTML += '<span class="dashicons ' + button.icon + '"></span>';
                buttonHTML += button.text;
                buttonHTML += '</a>';
            }

            return buttonHTML;
        },

        /**
         * Bind events for the popup
         *
         * @since 2.0.0
         * @return {void}
         */
        bindPopupEvents: function() {
            const self = this;
            const overlay = document.querySelector('.notifal-whatsnew-popup-overlay');
            const closeButton = overlay.querySelector('.notifal-whatsnew-popup-close');

            // Close button
            if (closeButton) {
                closeButton.addEventListener('click', () => {
                    self.closePopup();
                });
            }

            // Close on overlay click
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) {
                    self.closePopup();
                }
            });

            // ESC key to close
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && overlay.style.display !== 'none') {
                    self.closePopup();
                }
            });

            // Handle action buttons
            const actionButtons = overlay.querySelectorAll('.notifal-whatsnew-popup-button');
            actionButtons.forEach(button => {
                button.addEventListener('click', (e) => {
                    const buttonId = button.getAttribute('data-button-id');

                    if (buttonId === 'got-it' || button.classList.contains('notifal-whatsnew-popup-button-secondary')) {
                        // Close button
                        self.closePopup();

                        // Mark as dismissed if it's the "Got it" button
                        if (buttonId === 'got-it') {
                            self.markAsDismissed();
                        }
                    }
                    // Other buttons are regular links, let them navigate normally
                });
            });
        },

        /**
         * Mark the popup as dismissed
         *
         * @since 2.0.0
         * @return {void}
         */
        markAsDismissed: function() {
            fetch(notifalWhatsNewPopup.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams({
                    action: 'notifal_dismiss_whatsnew_popup',
                    nonce: notifalWhatsNewPopup.nonce
                })
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    console.warn('Failed to mark what\'s new as dismissed:', data.data);
                }
            })
            .catch(error => {
                console.error('Error dismissing what\'s new popup:', error);
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
     * Expose WhatsNewPopup globally for external access (e.g., sticky menu)
     */
    window.NotifalWhatsNewPopup = WhatsNewPopup;

    /**
     * Initialize when document is ready
     * DOM content loaded handler
     */
    document.addEventListener('DOMContentLoaded', function() {
        WhatsNewPopup.init();
    });

})();
