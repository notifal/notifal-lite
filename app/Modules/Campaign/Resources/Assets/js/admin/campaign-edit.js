/**
 * Campaign editor script (admin).
 *
 * Handles validation, save UX (loading state + toast), and the on-page notification
 * assignment picker (debounced AJAX search, selected list, FormData payload).
 *
 * Schedule start/end initial values come from PHP (`ScheduleDateTimeHelper::storedToDatetimeLocalForAdmin`) so they
 * match the campaign list; min/max constraints and save validation align with `timing.js` and `TimingSettingsService`.
 *
 * @since 2.2.0
 * @author Hossein <hossein@notifal.com>
 */
(function () {
    'use strict';

    /**
     * Read a localized string from `NotifalCampaignStrings`.
     *
     * @param {string} key Translation key.
     * @returns {string} Localized text or the key if missing.
     */
    function getL10n(key) {
        if (typeof window.NotifalCampaignStrings !== 'undefined' && window.NotifalCampaignStrings[key]) {
            return window.NotifalCampaignStrings[key];
        }
        return key;
    }

    /**
     * Zero-pads a numeric segment for datetime-local strings.
     *
     * @param {number} num Segment value.
     * @returns {string} Two-character segment.
     */
    function padTimingDateTimePart(num) {
        return String(num).padStart(2, '0');
    }

    /**
     * Show a toast using the shared Notifal admin utilities.
     *
     * @param {string} type Message type: success, error, warning, info.
     * @param {string} message Human-readable message.
     * @returns {void}
     */
    function showToast(type, message) {
        if (typeof window.NotifalUtils !== 'undefined' && typeof window.NotifalUtils.showMessage === 'function') {
            window.NotifalUtils.showMessage(message, type, 5000);
        }
    }

    /**
     * Escape text for safe insertion into HTML.
     *
     * @param {string} text Raw text.
     * @returns {string} Escaped string.
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Build the admin edit URL for an on-page notification ID.
     *
     * @param {number} id Notification post ID.
     * @returns {string} Full URL.
     */
    function buildOnpageEditUrl(id) {
        const base = window.NotifalCampaignAjax && window.NotifalCampaignAjax.onpage_edit_url_base
            ? window.NotifalCampaignAjax.onpage_edit_url_base
            : '';
        return base + String(id);
    }

    /**
     * Last-known campaign schedule values after load or after constraint pass; used to allow
     * saving an unchanged past start (already-live window) without blocking resave.
     *
     * @type {{ startDate: string, endDate: string }}
     */
    let campaignScheduleValidationSnapshot = {
        startDate: '',
        endDate: '',
    };

    /**
     * Formats a Date as datetime-local value (YYYY-MM-DDTHH:mm) in the browser local timezone.
     *
     * @param {Date} dateObj Reference instant.
     * @returns {string} Value suitable for input[type="datetime-local"].
     */
    function getTimingLocalDateTimeString(dateObj) {
        const year = dateObj.getFullYear();
        const month = padTimingDateTimePart(dateObj.getMonth() + 1);
        const day = padTimingDateTimePart(dateObj.getDate());
        const hours = padTimingDateTimePart(dateObj.getHours());
        const minutes = padTimingDateTimePart(dateObj.getMinutes());
        return `${year}-${month}-${day}T${hours}:${minutes}`;
    }

    /**
     * Parses schedule field values to milliseconds since epoch (browser local clock).
     *
     * Supports HTML datetime-local (`YYYY-MM-DDTHH:mm`) and US-style strings with optional spaces
     * so ordering checks do not rely on broken string ordering.
     *
     * @param {string} value Raw `input.value` from the schedule field.
     * @returns {number|null} Epoch ms, or null when empty or unrecognized.
     */
    function parseScheduleFieldToLocalMs(value) {
        if (!value || typeof value !== 'string') {
            return null;
        }
        const v = value.trim();
        if (v === '') {
            return null;
        }
        if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/.test(v)) {
            const parsed = new Date(v);
            return Number.isNaN(parsed.getTime()) ? null : parsed.getTime();
        }
        const m = v.match(/^(\d{1,2})\s*\/\s*(\d{1,2})\s*\/\s*(\d{4})\s*,?\s*(\d{1,2})\s*:\s*(\d{2})\s*(AM|PM)\s*$/i);
        if (!m) {
            return null;
        }
        let hour24 = parseInt(m[4], 10);
        const minutes = parseInt(m[5], 10);
        const monthIndex = parseInt(m[1], 10) - 1;
        const dayNum = parseInt(m[2], 10);
        const yearNum = parseInt(m[3], 10);
        const ap = m[6].toUpperCase();
        if (ap === 'PM' && hour24 !== 12) {
            hour24 += 12;
        }
        if (ap === 'AM' && hour24 === 12) {
            hour24 = 0;
        }
        const d = new Date(yearNum, monthIndex, dayNum, hour24, minutes, 0, 0);
        return Number.isNaN(d.getTime()) ? null : d.getTime();
    }

    /**
     * True when both values parse as local instants and the first is strictly before the second.
     *
     * @param {string} a First schedule field value.
     * @param {string} b Second schedule field value.
     * @returns {boolean}
     */
    function scheduleValueLessThan(a, b) {
        if (!a || !b) {
            return false;
        }
        const ta = parseScheduleFieldToLocalMs(a);
        const tb = parseScheduleFieldToLocalMs(b);
        if (ta !== null && tb !== null) {
            return ta < tb;
        }
        return a < b;
    }

    /**
     * True when the value parses to a local instant before `nowMs`.
     *
     * @param {string} value Schedule field value.
     * @param {number} nowMs Result of `Date.now()`.
     * @returns {boolean}
     */
    function scheduleValueIsBeforeNow(value, nowMs) {
        const t = parseScheduleFieldToLocalMs(value);
        return t !== null && t < nowMs;
    }

    /**
     * Captures current start/end inputs into {@link campaignScheduleValidationSnapshot}.
     *
     * @returns {void}
     */
    function captureCampaignScheduleValidationSnapshot() {
        const form = document.getElementById('notifal-campaign-edit-form');
        const startDateField = form ? form.querySelector('input[name="start_date"]') : null;
        const endDateField = form ? form.querySelector('input[name="end_date"]') : null;
        campaignScheduleValidationSnapshot = {
            startDate: startDateField && startDateField.value ? String(startDateField.value) : '',
            endDate: endDateField && endDateField.value ? String(endDateField.value) : '',
        };
    }

    /**
     * Applies min/max rules and keeps start/end ordering consistent (aligned with on-page `timing.js`).
     * Past end dates are left visible so editors can adjust them (e.g. to clear an ended campaign).
     *
     * @param {HTMLInputElement} startDateField Campaign start input.
     * @param {HTMLInputElement} endDateField Campaign end input.
     * @returns {void}
     */
    function applyCampaignDateConstraints(startDateField, endDateField) {
        const nowStr = getTimingLocalDateTimeString(new Date());
        const nowMs = Date.now();

        const startVal = startDateField.value;
        if (!startVal) {
            startDateField.min = nowStr;
        } else if (scheduleValueIsBeforeNow(startVal, nowMs)) {
            startDateField.removeAttribute('min');
        } else {
            startDateField.min = nowStr;
        }

        if (endDateField.value) {
            startDateField.max = endDateField.value;
            if (startDateField.value && scheduleValueLessThan(endDateField.value, startDateField.value)) {
                startDateField.value = '';
            }
        } else {
            startDateField.removeAttribute('max');
        }

        if (startDateField.value) {
            endDateField.min = startDateField.value;
            if (endDateField.value && scheduleValueLessThan(endDateField.value, startDateField.value)) {
                endDateField.value = '';
            }
        } else {
            endDateField.min = nowStr;
        }
    }

    /**
     * Binds campaign schedule field listeners and runs an initial constraint pass.
     *
     * @returns {void}
     */
    function initCampaignScheduleConstraints() {
        const form = document.getElementById('notifal-campaign-edit-form');
        const startDateField = form ? form.querySelector('input[name="start_date"]') : null;
        const endDateField = form ? form.querySelector('input[name="end_date"]') : null;
        if (!form || !startDateField || !endDateField) {
            return;
        }

        /**
         * Re-applies constraints after a user edit.
         *
         * @returns {void}
         */
        function apply() {
            applyCampaignDateConstraints(startDateField, endDateField);
        }

        startDateField.addEventListener('change', function () {
            if (endDateField.value && startDateField.value && scheduleValueLessThan(endDateField.value, startDateField.value)) {
                endDateField.value = '';
            }
            apply();
        });

        endDateField.addEventListener('change', function () {
            if (startDateField.value && endDateField.value && scheduleValueLessThan(endDateField.value, startDateField.value)) {
                startDateField.value = '';
            }
            apply();
        });

        window.setTimeout(function () {
            apply();
            captureCampaignScheduleValidationSnapshot();
        }, 50);
    }

    /**
     * Validate that start datetime is not after end datetime.
     *
     * @param {string} startDate Value from datetime-local input.
     * @param {string} endDate Value from datetime-local input.
     * @returns {boolean} True when valid or when comparison cannot be made.
     */
    function validateStartBeforeEnd(startDate, endDate) {
        if (!startDate || !endDate) {
            return true;
        }
        if (scheduleValueLessThan(endDate, startDate)) {
            return false;
        }
        const start = new Date(startDate);
        const end = new Date(endDate);
        if (isNaN(start.getTime()) || isNaN(end.getTime())) {
            return true;
        }
        return end.getTime() >= start.getTime();
    }

    /**
     * Blocks saving a newly entered start that is already in the past unless it matches the
     * snapshot from initial load (same rule as on-page timing validation).
     *
     * @param {string} startRaw Value from the start field.
     * @returns {boolean} True when valid.
     */
    function validateCampaignStartNotStaleEdit(startRaw) {
        const nowMs = Date.now();
        if (!startRaw || !scheduleValueIsBeforeNow(startRaw, nowMs)) {
            return true;
        }
        const unchangedLoadedStart = campaignScheduleValidationSnapshot.startDate === startRaw;
        return unchangedLoadedStart;
    }

    /**
     * Campaign on-page picker: manages selected notifications and search UI.
     *
     * @param {HTMLElement} root Root element with `data-initial-items` JSON.
     */
    function CampaignOnpagePicker(root) {
        /** @type {Map<number, {id: number, title: string, editUrl: string}>} */
        const selected = new Map();
        const searchInput = root.querySelector('#notifal-campaign-onpage-search-input');
        const resultsBox = root.querySelector('#notifal-campaign-onpage-search-results');
        const selectedHeading = root.querySelector('#notifal-campaign-onpage-selected-heading');
        const selectedList = root.querySelector('#notifal-campaign-onpage-selected-list');
        let searchTimer = null;
        let activeController = null;

        /**
         * Parse initial items from the data attribute.
         *
         * @returns {Array<{id: number, title: string, edit_url?: string}>} Initial rows.
         */
        function readInitial() {
            const raw = root.getAttribute('data-initial-items') || '[]';
            try {
                const parsed = JSON.parse(raw);
                return Array.isArray(parsed) ? parsed : [];
            } catch (e) {
                return [];
            }
        }

        /**
         * Seed the map from server-rendered JSON.
         *
         * @returns {void}
         */
        function hydrateInitial() {
            readInitial().forEach(function (row) {
                const id = parseInt(String(row.id), 10);
                if (!id) {
                    return;
                }
                const title = typeof row.title === 'string' ? row.title : '';
                const editUrl = typeof row.edit_url === 'string' && row.edit_url
                    ? row.edit_url
                    : buildOnpageEditUrl(id);
                selected.set(id, { id: id, title: title, editUrl: editUrl });
            });
            renderSelected();
        }

        /**
         * Toggle visibility of the selected section heading.
         *
         * @returns {void}
         */
        function updateHeadingVisibility() {
            if (!selectedHeading) {
                return;
            }
            if (selected.size > 0) {
                selectedHeading.classList.remove('notifal-hidden');
            } else {
                selectedHeading.classList.add('notifal-hidden');
            }
        }

        /**
         * Render chips for all selected notifications.
         *
         * @returns {void}
         */
        function renderSelected() {
            if (!selectedList) {
                return;
            }
            selectedList.innerHTML = '';
            selected.forEach(function (item) {
                const chip = document.createElement('div');
                chip.className = 'notifal-campaign-onpage-chip';
                chip.setAttribute('role', 'listitem');
                chip.setAttribute('data-notification-id', String(item.id));

                const link = document.createElement('a');
                link.className = 'notifal-campaign-onpage-chip-link';
                link.href = item.editUrl;
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
                link.setAttribute('aria-label', getL10n('onpage_open_edit_aria'));
                link.textContent = item.title;

                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'notifal-campaign-onpage-chip-remove';
                removeBtn.setAttribute('aria-label', getL10n('onpage_remove_aria'));
                removeBtn.innerHTML = '<span class="notifal-icon notifal-icon-x-circle size-12" aria-hidden="true"></span>';

                removeBtn.addEventListener('click', function (ev) {
                    ev.preventDefault();
                    selected.delete(item.id);
                    renderSelected();
                });

                chip.appendChild(link);
                chip.appendChild(removeBtn);
                selectedList.appendChild(chip);
            });
            updateHeadingVisibility();
        }

        /**
         * Hide the search results dropdown.
         *
         * @returns {void}
         */
        function hideResults() {
            if (!resultsBox) {
                return;
            }
            resultsBox.classList.add('notifal-hidden');
            resultsBox.innerHTML = '';
        }

        /**
         * Render one row in the results list.
         *
         * @param {{id: number, title: string}} row Result row.
         * @returns {void}
         */
        function appendResultRow(row) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'notifal-campaign-onpage-search-result-item';
            btn.setAttribute('role', 'option');
            btn.setAttribute('data-notification-id', String(row.id));
            btn.innerHTML = '<span class="notifal-campaign-onpage-search-result-title">' + escapeHtml(row.title) + '</span>';

            btn.addEventListener('click', function () {
                const id = row.id;
                if (selected.has(id)) {
                    hideResults();
                    if (searchInput) {
                        searchInput.value = '';
                    }
                    return;
                }
                selected.set(id, {
                    id: id,
                    title: row.title,
                    editUrl: buildOnpageEditUrl(id),
                });
                renderSelected();
                hideResults();
                if (searchInput) {
                    searchInput.value = '';
                    searchInput.focus();
                }
            });

            resultsBox.appendChild(btn);
        }

        /**
         * Run AJAX search and populate the dropdown.
         *
         * @param {string} term Search string.
         * @returns {Promise<void>}
         */
        async function runSearch(term) {
            if (!resultsBox) {
                return;
            }

            if (term.length < 2) {
                resultsBox.classList.remove('notifal-hidden');
                resultsBox.innerHTML = '<div class="notifal-campaign-onpage-search-hint">' + escapeHtml(getL10n('onpage_search_type_more')) + '</div>';
                return;
            }

            if (activeController) {
                activeController.abort();
            }
            activeController = typeof AbortController !== 'undefined' ? new AbortController() : null;

            resultsBox.classList.remove('notifal-hidden');
            resultsBox.innerHTML = '<div class="notifal-campaign-onpage-search-loading">' + escapeHtml(getL10n('onpage_search_loading')) + '</div>';

            const ajax = window.NotifalCampaignAjax || {};
            const payload = new FormData();
            const actionName = ajax.ajax_actions && ajax.ajax_actions.search_onpage_for_campaign
                ? ajax.ajax_actions.search_onpage_for_campaign
                : 'notifal_search_onpage_notifications_for_campaign';
            payload.append('action', actionName);
            payload.append('nonce', ajax.nonce && ajax.nonce.search_onpage_for_campaign ? ajax.nonce.search_onpage_for_campaign : '');
            payload.append('search', term);

            try {
                const response = await fetch(ajax.ajax_url || '', {
                    method: 'POST',
                    body: payload,
                    credentials: 'same-origin',
                    signal: activeController ? activeController.signal : undefined,
                });
                const result = await response.json();
                if (!result || !result.success || !result.data || !Array.isArray(result.data.items)) {
                    resultsBox.innerHTML = '<div class="notifal-campaign-onpage-search-error">' + escapeHtml(getL10n('onpage_search_error')) + '</div>';
                    return;
                }
                const items = result.data.items;
                resultsBox.innerHTML = '';
                if (items.length === 0) {
                    resultsBox.innerHTML = '<div class="notifal-campaign-onpage-search-empty">' + escapeHtml(getL10n('onpage_search_no_results')) + '</div>';
                    return;
                }
                items.forEach(appendResultRow);
            } catch (err) {
                if (err && err.name === 'AbortError') {
                    return;
                }
                resultsBox.innerHTML = '<div class="notifal-campaign-onpage-search-error">' + escapeHtml(getL10n('onpage_search_error')) + '</div>';
            }
        }

        /**
         * Schedule debounced search.
         *
         * @returns {void}
         */
        function scheduleSearch() {
            if (!searchInput) {
                return;
            }
            const term = searchInput.value.trim();
            if (searchTimer) {
                window.clearTimeout(searchTimer);
            }
            searchTimer = window.setTimeout(function () {
                runSearch(term);
            }, 300);
        }

        if (searchInput) {
            searchInput.addEventListener('input', scheduleSearch);
            searchInput.addEventListener('focus', function () {
                const term = searchInput.value.trim();
                if (term.length < 2) {
                    if (resultsBox) {
                        resultsBox.classList.remove('notifal-hidden');
                        resultsBox.innerHTML = '<div class="notifal-campaign-onpage-search-hint">' + escapeHtml(getL10n('onpage_search_type_more')) + '</div>';
                    }
                }
            });
        }

        document.addEventListener('click', function (ev) {
            if (!root.contains(ev.target)) {
                hideResults();
            }
        });

        hydrateInitial();

        /**
         * Append selected notification IDs to a FormData payload.
         *
         * @param {FormData} payload Save payload.
         * @returns {void}
         */
        this.appendToFormData = function (payload) {
            selected.forEach(function (item) {
                payload.append('notification_ids[]', String(item.id));
            });
        };
    }

    /**
     * Main initializer for the campaign edit screen.
     *
     * @returns {void}
     */
    function initCampaignEditor() {
        const form = document.getElementById('notifal-campaign-edit-form');
        const saveButton = document.getElementById('notifal-save-campaign-btn');
        const pickerRoot = document.getElementById('notifal-campaign-onpage-picker-root');

        if (!form || !saveButton) {
            return;
        }

        initCampaignScheduleConstraints();

        /** @type {CampaignOnpagePicker|null} */
        let picker = null;
        if (pickerRoot) {
            picker = new CampaignOnpagePicker(pickerRoot);
        }

        const originalButtonText = saveButton.textContent;
        let isSaving = false;

        /**
         * Toggle disabled + loading styles on the save control (matches on-page save UX).
         *
         * @param {boolean} saving Whether a save is in progress.
         * @returns {void}
         */
        function setSavingState(saving) {
            isSaving = saving;
            if (saving) {
                saveButton.disabled = true;
                saveButton.textContent = getL10n('saving');
                saveButton.classList.add('notifal-loading');
            } else {
                saveButton.disabled = false;
                saveButton.textContent = originalButtonText;
                saveButton.classList.remove('notifal-loading');
            }
        }

        /**
         * Resolves campaign meta `status` from the edit form (checkbox + hidden fallback).
         *
         * @returns {string} `active` or `paused`.
         */
        function getCampaignStatusFromForm() {
            const checkbox = form.querySelector('input#campaign_status.notifal-toggle-input');
            if (checkbox && checkbox.checked) {
                return 'active';
            }
            return 'paused';
        }

        /**
         * Collect standard field values from the form (including `campaign_status` for validation parity with POST).
         *
         * @returns {Object<string, string>} Values keyed by field name (`campaign_status` is `active` or `paused`).
         */
        function getFormValues() {
            return {
                campaign_id: (form.querySelector('input[name="campaign_id"]') || {}).value || '',
                campaign_title: (form.querySelector('input[name="campaign_title"]') || {}).value || '',
                campaign_description: (form.querySelector('textarea[name="campaign_description"]') || {}).value || '',
                campaign_status: getCampaignStatusFromForm(),
                start_date: (form.querySelector('input[name="start_date"]') || {}).value || '',
                end_date: (form.querySelector('input[name="end_date"]') || {}).value || '',
                priority: (form.querySelector('input[name="priority"]') || {}).value || '5',
            };
        }

        saveButton.addEventListener('click', async function (e) {
            e.preventDefault();

            if (isSaving) {
                return;
            }

            const values = getFormValues();

            if (!values.campaign_title || values.campaign_title.trim() === '') {
                showToast('error', getL10n('campaign_title_required'));
                return;
            }

            if (!validateStartBeforeEnd(values.start_date, values.end_date)) {
                showToast('error', getL10n('validation_start_before_end'));
                return;
            }

            if (!validateCampaignStartNotStaleEdit(values.start_date)) {
                showToast('error', getL10n('validation_start_must_be_future'));
                return;
            }

            const ajax = window.NotifalCampaignAjax || {};
            const payload = new FormData(form);
            payload.append('action', 'notifal_save_campaign');
            if (ajax.nonce && ajax.nonce.save_campaign) {
                payload.set('nonce', ajax.nonce.save_campaign);
            }

            if (picker && typeof picker.appendToFormData === 'function') {
                picker.appendToFormData(payload);
            }

            setSavingState(true);

            try {
                const response = await fetch(ajax.ajax_url || '', {
                    method: 'POST',
                    body: payload,
                    credentials: 'same-origin',
                });

                const result = await response.json();

                if (!result || !result.success) {
                    const message = (result && result.data && result.data.message) ? result.data.message : (result && result.message) ? result.message : getL10n('save_error');
                    showToast('error', message);
                    setSavingState(false);
                    return;
                }

                const campaignId = result.data && result.data.campaign_id ? result.data.campaign_id : values.campaign_id;
                showToast('success', getL10n('save_success'));

                if (campaignId) {
                    const url = new URL(window.location.href);
                    url.searchParams.set('page', 'notifal-campaign');
                    url.searchParams.set('action', 'edit');
                    url.searchParams.set('id', String(campaignId));
                    window.location.href = url.toString();
                } else {
                    setSavingState(false);
                }
            } catch (err) {
                showToast('error', getL10n('save_error'));
                setSavingState(false);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            window.setTimeout(initCampaignEditor, 50);
        });
    } else {
        window.setTimeout(initCampaignEditor, 50);
    }
})();
