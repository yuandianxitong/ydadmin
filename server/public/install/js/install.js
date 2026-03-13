/* ============================================================
 * 项目：元点Admin
 * 安装程序：通用UI脚本
 *
 * IMPORTANT:
 * Step pages register their own DOMContentLoaded handlers before this file
 * is executed (because this script is included at the end of <body>).
 * So we must expose window.InstallUI immediately (not inside DOMContentLoaded),
 * otherwise those handlers may run first and see InstallUI as undefined.
 * ============================================================ */

(function() {
    const InstallUI = {

        /**
         * 封装的 fetch API，用于调用安装程序的后端操作
         * @param {string} action - The action name to be posted.
         * @param {URLSearchParams} [params] - Optional URLSearchParams to send.
         * @returns {Promise<any>}
         */
        fetch: async function(action, params = new URLSearchParams()) {
            if (!(params instanceof URLSearchParams)) {
                params = new URLSearchParams(params);
            }
            params.set('action', action);

            const response = await fetch('./index.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: params.toString()
            });

            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`HTTP ${response.status}: ${errorText || response.statusText}`);
            }

            const text = await response.text();
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Failed to parse JSON response:', text);
                throw new Error('服务器返回了无效的响应格式。');
            }
        },

        /**
         * 显示一个 toast 消息
         * @param {string} message - Message to display.
         * @param {'success'|'error'|'warning'|'info'|'loading'} type - Type of toast.
         * @param {number} duration - Duration in ms. 0 for permanent loading.
         * @returns {string} Toast ID.
         */
        toast: function(message, type = 'info', duration = 3000) {
            const id = `toast-${Date.now()}-${Math.random()}`;
            const toastContainer = document.getElementById('toast-container') || this._createToastContainer();

            const alertClasses = {
                success: 'alert-success',
                error: 'alert-error',
                warning: 'alert-warning',
                info: 'alert-info',
                loading: 'alert-info'
            };

            const iconClasses = {
                success: 'fa fa-check-circle',
                error: 'fa fa-exclamation-triangle',
                warning: 'fa fa-exclamation-circle',
                info: 'fa fa-info-circle',
                loading: 'loading loading-spinner'
            };

            const toastElement = document.createElement('div');
            toastElement.id = id;
            toastElement.className = `alert ${alertClasses[type]} flex items-center shadow-lg animate-fade-in-down`;
            toastElement.innerHTML = `
                <span class="${iconClasses[type]}"></span>
                <span>${message}</span>
            `;

            toastContainer.appendChild(toastElement);

            if (duration > 0) {
                setTimeout(() => {
                    toastElement.classList.add('animate-fade-out-up');
                    toastElement.addEventListener('animationend', () => toastElement.remove());
                }, duration);
            }
            
            return id;
        },

        /**
         * 隐藏一个持久化的 toast
         * @param {string} toastId - ID of the toast to hide.
         */
        hideToast: function(toastId) {
            const toastElement = document.getElementById(toastId);
            if (toastElement) {
                toastElement.classList.add('animate-fade-out-up');
                toastElement.addEventListener('animationend', () => toastElement.remove());
            }
        },
        
        _createToastContainer: function() {
            let container = document.getElementById('toast-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'toast-container';
                container.className = 'fixed top-5 right-5 z-50 space-y-2';
                document.body.appendChild(container);
            }
            return container;
        },

        /**
         * 显示一个确认对话框
         * @param {object} options
         * @param {string} options.title
         * @param {string} options.message
         * @param {string} [options.okText='OK']
         * @param {string} [options.cancelText='Cancel']
         * @param {boolean} [options.danger=false]
         * @returns {Promise<boolean>}
         */
        confirm: function({ title, message, okText = '确认', cancelText = '取消', danger = false }) {
            return new Promise(resolve => {
                const id = `modal-${Date.now()}`;
                const modal = document.createElement('div');
                modal.id = id;
                modal.className = 'modal modal-open';
                modal.innerHTML = `
                    <div class="modal-box">
                        <h3 class="font-bold text-lg">${title}</h3>
                        <p class="py-4">${message}</p>
                        <div class="modal-action">
                            <button class="btn btn-ghost" data-action="cancel">${cancelText}</button>
                            <button class="btn ${danger ? 'btn-error' : 'btn-primary'}" data-action="ok">${okText}</button>
                        </div>
                    </div>
                `;
                document.body.appendChild(modal);

                const closeModal = (result) => {
                    modal.remove();
                    resolve(result);
                };

                modal.querySelector('[data-action="ok"]').onclick = () => closeModal(true);
                modal.querySelector('[data-action="cancel"]').onclick = () => closeModal(false);
            });
        },
        
         /**
         * 显示一个提示对话框
         * @param {object} options
         * @param {string} options.title
         * @param {string} options.message
         * @param {boolean} [options.error=false]
         */
        alert: function({ title, message, error = false }) {
             const id = `modal-${Date.now()}`;
             const modal = document.createElement('div');
             modal.id = id;
             modal.className = 'modal modal-open';
             modal.innerHTML = `
                <div class="modal-box">
                    <h3 class="font-bold text-lg ${error ? 'text-error' : ''}">${title}</h3>
                    <div class="py-4">${message}</div>
                    <div class="modal-action">
                        <button class="btn btn-primary" data-action="ok">关闭</button>
                    </div>
                </div>
            `;
             document.body.appendChild(modal);
             modal.querySelector('[data-action="ok"]').onclick = () => modal.remove();
        },

        /**
         * 设置按钮的加载状态
         * @param {HTMLElement} btn - The button element.
         * @param {boolean} isLoading - Whether to show loading state.
         * @param {string} [loadingText] - Optional text to show during loading.
         */
        setLoading: function(btn, isLoading, loadingText) {
            if (!btn) return;
            
            const textEl = btn.querySelector('span:not(.loading)');
            const spinnerEl = btn.querySelector('.loading');

            if (isLoading) {
                btn.disabled = true;
                if(textEl) textEl.dataset.originalText = textEl.textContent;
                if(loadingText && textEl) textEl.textContent = loadingText;
                if(spinnerEl) spinnerEl.classList.remove('hidden');
            } else {
                btn.disabled = false;
                if (textEl && textEl.dataset.originalText) textEl.textContent = textEl.dataset.originalText;
                if(spinnerEl) spinnerEl.classList.add('hidden');
            }
        },
        
        /**
         * 切换密码可见性
         * @param {string} inputId - ID of the password input.
         */
        togglePassword: function(inputId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(`${inputId}_icon`);
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fa fa-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'fa fa-eye';
            }
        }
    };

    window.InstallUI = InstallUI;
})();
