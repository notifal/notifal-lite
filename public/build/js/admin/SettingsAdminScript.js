(function(){class a{constructor(t){this.settings=t,this.activeModals=new Set,this.showProUpgradeModal=this.showProUpgradeModal.bind(this),this.setupModalCloseHandlers=this.setupModalCloseHandlers.bind(this)}showProUpgradeModal(t,e){const s=document.getElementById("notifal-pro-upgrade-modal");s&&s.remove();const i=`
            <div class="notifal-modal-backdrop" id="notifal-pro-upgrade-modal">
                <div class="notifal-modal notifal-pro-upgrade-modal">
                    <div class="notifal-modal-header">
                        <h3>${this.settings.getString("notifal_pro_required")}</h3>
                        <button type="button" class="notifal-modal-close" title="${this.settings.getString("close")}">
                            <span class="notifal-icon notifal-icon-x-circle"></span>
                        </button>
                    </div>
                    <div class="notifal-modal-body">
                        <div class="notifal-pro-upgrade-content">
                            <div class="notifal-pro-icon">
                                <span class="notifal-icon notifal-icon-star" style="font-size: 48px; color: #ff6b35;"></span>
                            </div>
                            <div class="notifal-pro-message">
                                <p><strong>${window.NotifalUtils.escapeHtml(t)}</strong></p>
                                <p>${this.settings.getString("unlock_advanced_features")}</p>
                                <ul class="notifal-pro-features">
                                    <li>${this.settings.getString("advanced_tag_generation")}</li>
                                    <li>${this.settings.getString("multiple_display_rules")}</li>
                                    <li>${this.settings.getString("enhanced_analytics")}</li>
                                    <li>${this.settings.getString("custom_css_styling")}</li>
                                    <li>${this.settings.getString("unlimited_notifications")}</li>
                                    <li>${this.settings.getString("comment_tags_more")}</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="notifal-modal-footer">
                        <div class="notifal-modal-actions">
                            <button type="button" class="notifal-btn notifal-btn-secondary notifal-modal-close">
                                ${this.settings.getString("maybe_later")}
                            </button>
                            <a href="${window.NotifalUtils.escapeHtml(e||"https://notifal.com/pricing/?utm_source=wordpress_plugin&utm_medium=plugin&utm_campaign=notifal_pro_upgrade")}"
                               target="_blank"
                               class="notifal-btn notifal-btn-primary">
                                ${this.settings.getString("upgrade_to_pro")}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        `;document.body.insertAdjacentHTML("beforeend",i);const n=document.getElementById("notifal-pro-upgrade-modal");this.setupModalCloseHandlers(n),this.activeModals.add(n)}setupModalCloseHandlers(t){t.querySelectorAll(".notifal-modal-close").forEach(i=>{i.addEventListener("click",()=>{this.closeModal(t)})}),t.addEventListener("click",i=>{i.target===t&&this.closeModal(t)});const s=i=>{i.key==="Escape"&&(this.closeModal(t),document.removeEventListener("keydown",s))};document.addEventListener("keydown",s)}closeModal(t){t&&t.parentNode&&(t.remove(),this.activeModals.delete(t))}closeAllModals(){this.activeModals.forEach(t=>{this.closeModal(t)}),this.activeModals.clear()}showConfirmationModal(t){const{title:e=this.settings.getString("confirm"),message:s="",confirmText:i=this.settings.getString("confirm"),cancelText:n=this.settings.getString("cancel"),onConfirm:c=()=>{},onCancel:d=()=>{}}=t,r=document.getElementById("notifal-confirmation-modal");r&&r.remove();const g=`
            <div class="notifal-modal-backdrop" id="notifal-confirmation-modal">
                <div class="notifal-modal notifal-confirmation-modal">
                    <div class="notifal-modal-header">
                        <h3>${window.NotifalUtils.escapeHtml(e)}</h3>
                        <button type="button" class="notifal-modal-close" title="${this.settings.getString("close")}">
                            <span class="notifal-icon notifal-icon-x-circle"></span>
                        </button>
                    </div>
                    <div class="notifal-modal-body">
                        <div class="notifal-confirmation-content">
                            <div class="notifal-confirmation-icon">
                                <span class="notifal-icon notifal-icon-question-circle" style="font-size: 48px; color: #007cba;"></span>
                            </div>
                            <div class="notifal-confirmation-message">
                                <p>${window.NotifalUtils.escapeHtml(s)}</p>
                            </div>
                        </div>
                    </div>
                    <div class="notifal-modal-footer">
                        <div class="notifal-modal-actions">
                            <button type="button" class="notifal-btn notifal-btn-secondary notifal-modal-close" data-action="cancel">
                                ${window.NotifalUtils.escapeHtml(n)}
                            </button>
                            <button type="button" class="notifal-btn notifal-btn-primary" data-action="confirm">
                                ${window.NotifalUtils.escapeHtml(i)}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;document.body.insertAdjacentHTML("beforeend",g);const o=document.getElementById("notifal-confirmation-modal"),f=o.querySelector('[data-action="confirm"]'),h=o.querySelector('[data-action="cancel"]');f.addEventListener("click",()=>{c(),this.closeModal(o)}),h.addEventListener("click",()=>{d(),this.closeModal(o)}),this.setupModalCloseHandlers(o),this.activeModals.add(o)}}window.ModalManager=a})();(function(){class a{constructor(t){this.settings=t,this.form=null,this.saveButton=null,this.resetButton=null,this.saveStateTimeout=null,this.handleFormSubmit=this.handleFormSubmit.bind(this),this.handleResetClick=this.handleResetClick.bind(this),this.setSavingState=this.setSavingState.bind(this)}init(){this.form=document.querySelector(".notifal-settings-form"),this.saveButton=document.querySelector("#notifal-save-settings"),this.resetButton=document.querySelector("#notifal-reset-settings"),this.setupEventListeners()}setupEventListeners(){this.form&&this.form.addEventListener("submit",this.handleFormSubmit),this.resetButton&&this.resetButton.addEventListener("click",this.handleResetClick)}async handleFormSubmit(t){if(t.preventDefault(),this.saveButton&&this.saveButton.disabled)return;this.setSavingState("loading");const e=this.collectFormData();await this.sendSaveRequest(e)}handleResetClick(t){t.preventDefault();const e=this.resetButton.getAttribute("data-confirm")||this.settings.getString("confirm_reset");confirm(e)&&this.resetSettingsToDefaults()}async resetSettingsToDefaults(){this.setResetButtonState("loading"),await this.sendResetRequest()}showResetFeedback(){const t=document.createElement("div");t.className="notice notice-info is-dismissible",t.innerHTML=`
            <p><strong>${this.settings.getString("settings_reset_to_defaults")}</strong> ${this.settings.getString("click_save_to_apply")}</p>
        `;const e=document.querySelector(".notifal-settings-title");e&&e.parentNode&&(e.parentNode.insertBefore(t,e.nextSibling),setTimeout(()=>{t.parentNode&&t.parentNode.removeChild(t)},5e3))}async sendResetRequest(){try{const t=new FormData;t.append("action","notifal_reset_settings"),t.append("nonce",window.notifal_settings?.nonce||"");const e=await fetch(window.notifal_settings?.ajax_url||"/wp-admin/admin-ajax.php",{method:"POST",body:t,credentials:"same-origin"});if(!e.ok)throw new Error(`HTTP ${e.status}: ${e.statusText}`);const s=await e.text();try{const i=JSON.parse(s);i.success?this.handleResetSuccess(i.data):this.handleResetError(i.data?.message||this.settings.getString("reset_error"))}catch(i){console.error("JSON parse error in reset:",i),console.error("Response text:",s),this.handleResetError(this.settings.getString("reset_error"))}}catch(t){console.error("Notifal Settings: Reset request error:",t),this.handleResetError(this.settings.getString("network_error"))}finally{this.resetButton&&!this.resetButton.innerHTML.includes("Reset!")&&this.setResetButtonState(!1)}}handleResetSuccess(t){t.settings&&window.notifal_settings&&(window.notifal_settings.tag_settings||(window.notifal_settings.tag_settings={}),window.notifal_settings.tag_settings={}),this.setResetButtonState("success"),typeof window.NotifalUtils<"u"&&window.NotifalUtils.showMessage?(window.NotifalUtils.showMessage(t.message||this.settings.getString("reset_success"),"success",3e3),setTimeout(()=>{window.location.reload()},2e3)):setTimeout(()=>{window.location.reload()},1e3)}handleResetError(t){this.setResetButtonState(!1),typeof window.NotifalUtils<"u"&&window.NotifalUtils.showMessage&&window.NotifalUtils.showMessage(t,"error",5e3)}setResetButtonState(t){this.resetButton&&(this.resetButton.resetStateTimeout&&clearTimeout(this.resetButton.resetStateTimeout),t==="loading"?(this.resetButton.disabled=!0,this.resetButton.innerHTML=`
                <span class="notifal-button-spinner"></span>
                ${this.settings.getString("resetting")}
            `):t==="success"?(this.resetButton.disabled=!0,this.resetButton.innerHTML=`
                <span class="notifal-button-check"><span class="notifal-icon notifal-icon-check size-16"></span></span>
                ${this.settings.getString("reset")}
            `,this.resetButton.resetStateTimeout=setTimeout(()=>{this.setResetButtonState(!1)},2e3)):(this.resetButton.disabled=!1,this.resetButton.innerHTML=`
                <span class="notifal-icon notifal-icon-refresh"></span>
                ${this.settings.getString("reset_button")}
            `))}collectFormData(){const t=new FormData;return t.append("action","notifal_save_settings"),t.append("nonce",window.notifal_settings?.nonce||""),this.form.querySelectorAll(".notifal-setting-checkbox").forEach(s=>{s.name&&t.append(s.name,s.checked?"1":"0")}),t}async sendSaveRequest(t){try{if(this.settings.postTypeGenerator&&this.settings.postTypeGenerator.selectedPostTypes?this.settings.postTypeGenerator.selectedPostTypes.size>0:!1){const n=`
                    <strong>${this.settings.getString("complete_tag_generation_first")}</strong><br>
                    ${this.settings.getString("selected_post_types_not_generated")}<br>
                    ${this.settings.getString("click_generate_tags_option")}<br>
                    ${this.settings.getString("uncheck_post_types_option")}<br><br>
                    ${this.settings.getString("then_try_saving_again")}
                `;window.NotifalUtils&&window.NotifalUtils.showMessage(n,"warning");return}const s=await fetch(window.notifal_settings?.ajax_url||"/wp-admin/admin-ajax.php",{method:"POST",body:t,credentials:"same-origin"});if(!s.ok)throw new Error(`HTTP ${s.status}: ${s.statusText}`);const i=await s.text();try{const n=JSON.parse(i);n.success?(this.handleSaveSuccess(n.data),setTimeout(()=>{window.location.reload()},1500)):this.handleSaveError(n.data?.message||this.settings.getString("save_error"))}catch(n){console.error("JSON parse error in save:",n),console.error("Response text:",i),this.handleSaveError(this.settings.getString("save_error"))}}catch(e){console.error("Notifal Settings: Save request error:",e),this.handleSaveError(this.settings.getString("network_error"))}finally{this.saveButton&&!this.saveButton.innerHTML.includes("Saved!")&&this.setSavingState(!1)}}handleSaveSuccess(t){t.settings&&window.notifal_settings&&(window.notifal_settings.tag_settings||(window.notifal_settings.tag_settings={}),Object.assign(window.notifal_settings.tag_settings,t.settings)),this.setSavingState("success"),typeof window.NotifalUtils<"u"&&window.NotifalUtils.showMessage?(window.NotifalUtils.showMessage(t.message||this.settings.getString("save_success"),"success",3e3),setTimeout(()=>{window.location.reload()},2e3)):setTimeout(()=>{window.location.reload()},1e3)}handleSaveError(t){this.setSavingState(!1),typeof window.NotifalUtils<"u"&&window.NotifalUtils.showMessage&&window.NotifalUtils.showMessage(t,"error",5e3)}setSavingState(t){this.saveButton&&(this.saveStateTimeout&&clearTimeout(this.saveStateTimeout),t==="loading"?(this.saveButton.disabled=!0,this.saveButton.innerHTML=`
                <span class="notifal-button-spinner"></span>
                ${this.settings.getString("saving")}
            `):t==="success"?(this.saveButton.disabled=!0,this.saveButton.innerHTML=`
                <span class="notifal-button-check"><span class="notifal-icon notifal-icon-check size-16"></span></span>
                ${this.settings.getString("saved")}
            `,this.saveStateTimeout=setTimeout(()=>{this.setSavingState(!1)},2e3)):(this.saveButton.disabled=!1,this.saveButton.innerHTML=`
                <span class="notifal-icon notifal-icon-check"></span>
                ${this.settings.getString("save_button")}
            `))}}window.FormHandler=a})();(function(){window.notifal_settings=window.notifal_settings||{};class a{constructor(){this.formHandler=null,this.modalManager=null,document.readyState==="loading"?document.addEventListener("DOMContentLoaded",()=>this.init()):this.init()}init(){this.modalManager=new ModalManager(this),this.formHandler=new FormHandler(this),this.formHandler.init(),this.setupUIEnhancements(),this.autoDismissNotices()}setupUIEnhancements(){this.setupTabSwitching(),this.setupCheckboxEffects(),this.setupKeyboardNavigation(),this.setupDisabledTooltips()}setupTabSwitching(){document.querySelectorAll(".notifal-nav-tab-wrapper .nav-tab").forEach(e=>{e.addEventListener("click",s=>{e.style.transform="scale(0.98)",setTimeout(()=>{e.style.transform=""},150)})})}setupCheckboxEffects(){document.querySelectorAll(".notifal-setting-checkbox").forEach(e=>{e.addEventListener("change",s=>{const i=e.closest(".notifal-setting-item");i&&(i.style.transform="scale(1.02)",i.style.transition="transform 0.2s ease",setTimeout(()=>{i.style.transform=""},200),e.checked?i.classList.add("notifal-setting-enabled"):i.classList.remove("notifal-setting-enabled"))})})}setupKeyboardNavigation(){document.querySelectorAll(".notifal-setting-item").forEach(e=>{const s=e.querySelector(".notifal-setting-checkbox"),i=e.querySelector(".notifal-setting-label");s&&i&&(e.setAttribute("tabindex","0"),e.addEventListener("keydown",n=>{(n.key==="Enter"||n.key===" ")&&(n.preventDefault(),s.disabled||(s.checked=!s.checked,s.dispatchEvent(new Event("change"))))}),e.addEventListener("focus",()=>{e.style.outline="2px solid #007cba",e.style.outlineOffset="2px"}),e.addEventListener("blur",()=>{e.style.outline="",e.style.outlineOffset=""}))})}setupDisabledTooltips(){document.querySelectorAll(".notifal-setting-disabled").forEach(e=>{const s=e.querySelector(".notifal-plugin-required");s&&e.setAttribute("title",s.textContent)})}autoDismissNotices(){document.querySelectorAll(".notice.is-dismissible").forEach(e=>{e.classList.contains("notifal-pro-notice")||e.classList.contains("notifal-persistent-notice")||setTimeout(()=>{e.parentNode&&(e.style.opacity="0",e.style.transition="opacity 0.3s ease",setTimeout(()=>{e.parentNode&&e.parentNode.removeChild(e)},300))},1e4)})}getString(t){return(window.notifal_settings?.strings||{})[t]||t}showProUpgradeModal(t,e){this.modalManager.showProUpgradeModal(t,e)}}(function(){const l=new a;window.NotifalSettingsInstance=l})()})();
