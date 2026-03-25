<h2 class="text-2xl font-bold text-slate-800 mb-6">参数配置</h2>
<form id="config-form" class="space-y-6">

    <!-- 数据库配置 -->
    <fieldset class="space-y-4 p-4 sm:p-6 bg-slate-50 rounded-lg">
        <legend class="text-lg font-semibold text-slate-800 border-b pb-2 mb-4 w-full">
            <i class="fa fa-database text-primary mr-2"></i>数据库配置
        </legend>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="form-control">
                <label class="label"><span class="label-text">数据库主机</span></label>
                <input type="text" name="db_host" value="127.0.0.1" class="input input-bordered" required>
                <span class="field-error">请填写数据库主机地址</span>
            </div>
            <div class="form-control">
                <label class="label"><span class="label-text">端口</span></label>
                <input type="number" name="db_port" value="3306" class="input input-bordered" required>
                <span class="field-error">请填写端口号</span>
            </div>
            <div class="form-control">
                <label class="label"><span class="label-text">数据库名</span></label>
                <input type="text" name="db_name" placeholder="yd_admin" class="input input-bordered" required>
                <span class="field-error">请填写数据库名</span>
            </div>
            <div class="form-control">
                <label class="label"><span class="label-text">用户名</span></label>
                <input type="text" name="db_user" placeholder="root" class="input input-bordered" required>
                <span class="field-error">请填写数据库用户名</span>
            </div>
            <div class="form-control">
                <label class="label"><span class="label-text">密码</span></label>
                <input type="password" name="db_pass" id="db_pass" class="input input-bordered" placeholder="数据库密码">
            </div>
            <div class="form-control">
                <label class="label"><span class="label-text">数据表前缀</span></label>
                <input type="text" name="db_prefix" placeholder="yd_" class="input input-bordered" pattern="^[a-zA-Z0-9_]*$">
                <span class="field-error">只允许字母、数字和下划线</span>
            </div>
        </div>
        <div class="flex items-center gap-4 pt-2">
            <button type="button" id="test-db-btn" class="btn btn-secondary">
                <span class="loading loading-spinner hidden"></span>
                <span>测试连接</span>
            </button>
            <div id="db-test-result" class="text-sm font-medium"></div>
        </div>
    </fieldset>

    <!-- 管理员配置 -->
    <fieldset class="space-y-4 p-4 sm:p-6 bg-slate-50 rounded-lg">
        <legend class="text-lg font-semibold text-slate-800 border-b pb-2 mb-4 w-full">
            <i class="fa fa-user-secret text-secondary mr-2"></i>管理员配置
        </legend>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="form-control">
                <label class="label"><span class="label-text">管理员用户名</span></label>
                <input type="text" name="admin_username" value="admin" class="input input-bordered" required pattern="^[a-zA-Z][a-zA-Z0-9_]{3,15}$">
                <span class="field-error">请填写4-16位用户名（字母开头）</span>
            </div>
            <div class="form-control">
                <label class="label"><span class="label-text">管理员密码</span></label>
                <input type="password" name="admin_password" id="admin_pass" class="input input-bordered" placeholder="至少6位" required minlength="6">
                <span class="field-error">密码至少6位</span>
            </div>
            <div class="form-control">
                <label class="label"><span class="label-text">管理员邮箱</span></label>
                <input type="email" name="admin_email" placeholder="admin@example.com" class="input input-bordered" required>
                <span class="field-error">请填写有效的邮箱地址</span>
            </div>
        </div>
    </fieldset>

</form>

<div class="flex justify-between mt-10">
    <a href="?step=environment" class="btn btn-ghost">
        <i class="fa fa-arrow-left mr-2"></i>
        上一步
    </a>
    <button type="button" id="start-install-btn" class="btn btn-primary">
        <span class="loading loading-spinner hidden"></span>
        <span>开始安装</span>
        <i class="fa fa-arrow-right ml-2"></i>
    </button>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const ui = window.InstallUI;
    const form = document.getElementById('config-form');
    // DB Test Handler
    const testBtn = document.getElementById('test-db-btn');
    testBtn.addEventListener('click', () => {
        ui.setLoading(testBtn, true);
        const dbResultEl = document.getElementById('db-test-result');
        dbResultEl.innerHTML = '';
        
        ui.fetch('test_database', new FormData(form))
            .then(data => {
                const icon = data.success ? 'fa-check-circle text-success' : 'fa-exclamation-triangle text-error';
                const color = data.success ? 'text-success' : 'text-error';
                dbResultEl.innerHTML = `<span class="${color} flex items-center gap-2"><i class="fa ${icon}"></i> ${data.message}</span>`;
            })
            .catch(err => {
                dbResultEl.innerHTML = `<span class="text-error flex items-center gap-2"><i class="fa fa-exclamation-triangle"></i> ${err.message}</span>`;
            })
            .finally(() => ui.setLoading(testBtn, false));
    });

    // Install Handler
    const installBtn = document.getElementById('start-install-btn');
    installBtn.addEventListener('click', async () => {
        if (!validateForm()) return;
        
        ui.setLoading(installBtn, true, '正在启动...');
        
        ui.fetch('start_install', new FormData(form))
            .then(data => {
                if (data.success) {
                    window.location.href = '?step=install';
                } else {
                    throw new Error(data.message || '未知错误');
                }
            })
            .catch(err => {
                ui.toast(`安装启动失败: ${err.message}`, 'error');
                ui.setLoading(installBtn, false);
            });
    });

    function validateForm() {
        let isValid = true;
        let firstError = null;
        // Clear all previous errors
        form.querySelectorAll('.input-error').forEach(el => el.classList.remove('input-error'));
        form.querySelectorAll('.field-error.visible').forEach(el => el.classList.remove('visible'));
        // Validate inputs that have constraints (required, pattern, minlength, type)
        form.querySelectorAll('input').forEach(input => {
            if (!input.checkValidity()) {
                input.classList.add('input-error');
                const errorSpan = input.parentElement.querySelector('.field-error');
                if (errorSpan) errorSpan.classList.add('visible');
                if (!firstError) firstError = input;
                isValid = false;
            }
        });
        if (firstError) {
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstError.focus();
        }
        return isValid;
    }
});
</script>
