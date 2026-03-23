/**
 * Campaign list table: toggles campaign meta `status` between active and paused via AJAX.
 *
 * Uses document-level click delegation so handlers work even when the list table is rendered
 * after this script runs (e.g. readyState already past loading) or when rows are replaced.
 *
 * @module NotifalCampaignStatusToggle
 * @since 2.2.0
 * @author Hossein <hossein@notifal.com>
 */

/**
 * Whether the delegated click listener has been registered.
 *
 * @type {boolean}
 */
let campaignToggleDelegationBound = false;

/**
 * Applies or removes the loading state on a toggle button.
 *
 * @since 2.2.0
 * @param {HTMLButtonElement} badge Target button.
 * @param {boolean} loading Whether loading is active.
 * @returns {void}
 */
function setToggleLoadingState(badge, loading) {
    if (loading) {
        badge.classList.add('notifal-loading');
        badge.disabled = true;
    } else {
        badge.classList.remove('notifal-loading');
        badge.disabled = false;
    }
}

/**
 * Shows an error toast when the shared admin utilities are available.
 *
 * @since 2.2.0
 * @param {string} message Error text.
 * @returns {void}
 */
function showToggleError(message) {
    if (window.NotifalUtils && typeof window.NotifalUtils.showMessage === 'function') {
        window.NotifalUtils.showMessage(message, 'error');
    }
}

/**
 * Sends the AJAX request to toggle campaign status.
 *
 * @since 2.2.0
 * @param {string} campaignId Campaign post ID.
 * @returns {Promise<Object>} Parsed JSON response body.
 */
async function postToggleCampaignStatus(campaignId) {
    const ajax = window.NotifalCampaignToggleAjax || {};
    const actionName =
        ajax.ajax_actions && ajax.ajax_actions.toggle_campaign_status
            ? ajax.ajax_actions.toggle_campaign_status
            : 'notifal_toggle_campaign_status';
    const nonce =
        ajax.nonce && ajax.nonce.toggle_campaign_status ? ajax.nonce.toggle_campaign_status : '';

    const formData = new FormData();
    formData.append('action', actionName);
    formData.append('nonce', nonce);
    formData.append('campaign_id', campaignId);

    const response = await fetch(ajax.ajax_url || '', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
    });

    if (!response.ok) {
        throw new Error('HTTP ' + String(response.status));
    }

    return response.json();
}

/**
 * Runs the toggle flow for a status button element.
 *
 * @since 2.2.0
 * @param {HTMLButtonElement} badge Toggle button.
 * @returns {Promise<void>}
 */
async function runCampaignStatusToggleForButton(badge) {
    const campaignId = badge.dataset.campaignId;

    if (!campaignId) {
        return;
    }

    if (badge.classList.contains('notifal-loading')) {
        return;
    }

    try {
        setToggleLoadingState(badge, true);

        const data = await postToggleCampaignStatus(campaignId);

        if (data && data.success && data.data) {
            window.setTimeout(() => {
                window.location.reload();
            }, 400);
            return;
        }

        let errorMessage =
            (window.NotifalCampaignStrings && window.NotifalCampaignStrings.unexpected_error) ||
            'An unexpected error occurred. Please try again.';

        if (data && data.data) {
            if (typeof data.data === 'string') {
                errorMessage = data.data;
            } else if (data.data.message) {
                errorMessage = data.data.message;
            }
        }

        showToggleError(errorMessage);
    } catch (err) {
        showToggleError(
            (window.NotifalCampaignStrings && window.NotifalCampaignStrings.unexpected_error) ||
                'An unexpected error occurred. Please try again.'
        );
    } finally {
        setToggleLoadingState(badge, false);
    }
}

/**
 * Delegated click handler: resolves the toggle button from the event target.
 *
 * @since 2.2.0
 * @param {MouseEvent} event Click event.
 * @returns {void}
 */
function onCampaignToggleDocumentClick(event) {
    const raw = event.target;
    const el = raw instanceof Element ? raw : raw && raw.parentElement ? raw.parentElement : null;
    if (!el || typeof el.closest !== 'function') {
        return;
    }

    const btn = el.closest('.notifal-campaign-status-toggle');
    if (!btn || !(btn instanceof HTMLButtonElement)) {
        return;
    }

    event.preventDefault();
    void runCampaignStatusToggleForButton(btn);
}

/**
 * Registers the delegated listener once.
 *
 * @since 2.2.0
 * @returns {void}
 */
function bootCampaignStatusToggle() {
    if (campaignToggleDelegationBound) {
        return;
    }
    campaignToggleDelegationBound = true;
    document.addEventListener('click', onCampaignToggleDocumentClick);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootCampaignStatusToggle);
} else {
    bootCampaignStatusToggle();
}
