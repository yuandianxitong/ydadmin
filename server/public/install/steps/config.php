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
            </div>
            <div class="form-control">
                <label class="label"><span class="label-text">端口</span></label>
                <input type="number" name="db_port" value="3306" class="input input-bordered" required>
            </div>
            <div class="form-control">
                <label class="label"><span class="label-text">数据库名</span></label>
                <input type="text" name="db_name" placeholder="yd_admin" class="input input-bordered" required>
            </div>
            <div class="form-control">
                <label class="label"><span class="label-text">用户名</span></label>
                <input type="text" name="db_user" placeholder="root" class="input input-bordered" required>
            </div>
            <div class="form-control">
                <label class="label"><span class="label-text">密码</span></label>
                <input type="password" name="db_pass" id="db_pass" class="input input-bordered" placeholder="数据库密码">
            </div>
            <div class="form-control">
                <label class="label"><span class="label-text">数据表前缀</span></label>
                <input type="text" name="db_prefix" placeholder="yd_" class="input input-bordered" pattern="^[a-zA-Z0-9_]*$">
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
            </div>
            <div class="form-control">
                <label class="label"><span class="label-text">管理员密码</span></label>
                <input type="password" name="admin_password" id="admin_pass" class="input input-bordered" placeholder="至少6位" required minlength="6">
            </div>
            <div class="form-control">
                <label class="label"><span class="label-text">管理员邮箱</span></label>
                <input type="email" name="admin_email" placeholder="admin@example.com" class="input input-bordered" required>
            </div>
            <div class="form-control">
                <label class="label"><span class="label-text">后台访问地址</span></label>
                <input type="text" name="admin_frontend_url" value="/admin" class="input input-bordered" placeholder="/admin">
            </div>
        </div>
    </fieldset>

    <!-- 安装选项 -->
    <fieldset class="p-4 sm:p-6 bg-slate-50 rounded-lg">
        <legend class="text-lg font-semibold text-slate-800 border-b pb-2 mb-4 w-full">
             <i class="fa fa-cog text-accent mr-2"></i>安装选项
        </legend>
        <div class="install-options">
            <div class="install-option" data-toggle="import_seed">
                <input type="checkbox" id="opt_import_seed" name="import_seed" value="1" class="checkbox checkbox-primary">
                <div class="install-option-text">导入演示数据 (方便快速体验系统)</div>
            </div>
            <div class="install-option install-option--danger" data-toggle="drop_existing">
                <input type="checkbox" id="opt_drop_existing" name="drop_existing" value="1" class="checkbox">
                <div class="install-option-text">清空同前缀的数据表 (危险操作!)</div>
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
    // Make option rows clickable (avoid label layout quirks).
    document.querySelectorAll('.install-option[data-toggle]').forEach(row => {
        row.addEventListener('click', (e) => {
            if (e.target && e.target.tagName === 'INPUT') return;
            const name = row.getAttribute('data-toggle');
            const input = form.querySelector(`input[name="${name}"]`);
            if (!input) return;
            input.checked = !input.checked;
        });
    });

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
        
        const dropExisting = form.querySelector('[name="drop_existing"]').checked;
        if (dropExisting) {
            const confirmed = await ui.confirm({
                title: '危险操作确认',
                message: '您勾选了“清空同前缀数据表”选项，这将删除现有数据。确定要继续吗？',
                okText: '确定，清空数据',
                danger: true,
            });
            if (!confirmed) return;
        }

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
        form.querySelectorAll('input[required]').forEach(input => {
            input.classList.remove('input-error');
            if (!input.checkValidity()) {
                input.classList.add('input-error');
                isValid = false;
            }
        });
        if (!isValid) {
            ui.toast('请检查所有必填项和格式要求', 'error');
        }
        return isValid;
    }
});
</script>
