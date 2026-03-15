<h2 class="text-2xl font-bold text-slate-800 mb-6">环境检测</h2>

<div id="environment-content">
    <div class="text-center py-12">
        <span class="loading loading-spinner loading-lg text-primary"></span>
        <p class="mt-4 text-lg text-slate-500">正在检测运行环境...</p>
    </div>
</div>

<div class="flex justify-between mt-10">
    <a href="?step=license" class="btn btn-ghost">
        <i class="fa fa-arrow-left mr-2"></i>
        上一步
    </a>
    <a href="?step=config" id="env-next-btn" class="btn btn-primary btn-disabled" aria-disabled="true">
        下一步
        <i class="fa fa-arrow-right ml-2"></i>
    </a>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const ui = window.InstallUI;
    const contentEl = document.getElementById('environment-content');
    const nextBtn = document.getElementById('env-next-btn');

    function statusBadge(item) {
        if (item.status) {
            return '<span style="color:#16a34a;font-weight:500"><i class="fa fa-check-circle"></i> 通过</span>';
        } else if (item.type === 'critical') {
            return '<span style="color:#dc2626;font-weight:500"><i class="fa fa-times-circle"></i> 失败</span>';
        } else {
            return '<span style="color:#d97706;font-weight:500"><i class="fa fa-exclamation-circle"></i> 建议</span>';
        }
    }

    function renderTableRows(items, isPermission) {
        return Object.values(items).map(item => {
            const detail = isPermission
                ? `<code style="font-size:0.8rem;color:#64748b">${item.path}</code>`
                : `要求: ${item.required} | 当前: ${item.current}`;
            return `<tr>
                <td style="padding:10px 12px;font-weight:500;color:#334155;white-space:nowrap">${item.name}</td>
                <td style="padding:10px 12px;color:#64748b;font-size:0.9rem">${detail}</td>
                <td style="padding:10px 12px;text-align:center;white-space:nowrap">${statusBadge(item)}</td>
            </tr>`;
        }).join('');
    }

    function renderResult(data) {
        const { requirements, permissions, critical_passed, warning_count } = data;
        let suggestions = [];
        Object.values(requirements).forEach(r => { if(!r.status) suggestions.push(`<strong>${r.name}:</strong> ${r.type === 'critical' ? '必须安装' : '建议安装'}`) });
        Object.values(permissions).forEach(p => { if(!p.status) suggestions.push(`<strong>${p.name}:</strong> 目录需要可读可写权限。`) });

        const tableStyle = 'width:100%;border-collapse:collapse;font-size:0.95rem';
        const thStyle = 'padding:10px 12px;text-align:left;font-weight:500;color:#64748b;font-size:0.85rem;border-bottom:2px solid #e5e7eb;background:#f8fafc';
        const trBorder = 'border-bottom:1px solid #f1f5f9';

        let html = `
            <div class="notice ${critical_passed ? 'notice--success' : 'notice--error'} mb-6">
                <i class="fa ${critical_passed ? 'fa-check-circle' : 'fa-exclamation-triangle'}"></i>
                <div>
                    <div class="notice-title">检测完成: ${critical_passed ? '环境满足要求' : '部分环境不满足'}</div>
                    <div class="notice-text">${critical_passed ? '所有必需项目已通过，您可以继续安装。' : '必需项目未通过，继续安装可能失败。'}</div>
                    ${warning_count > 0 ? `<div class="notice-text mt-1">${warning_count}个建议项目未满足，但不影响安装。</div>` : ''}
                </div>
            </div>

            <div class="space-y-6">
                <div>
                    <h3 class="text-lg font-semibold mb-3 text-slate-800 border-l-4 border-primary pl-3">PHP 环境</h3>
                    <div style="overflow-x:auto;border:1px solid #e5e7eb;border-radius:4px">
                        <table style="${tableStyle}">
                            <thead><tr>
                                <th style="${thStyle}">检测项</th>
                                <th style="${thStyle}">版本要求</th>
                                <th style="${thStyle};text-align:center">状态</th>
                            </tr></thead>
                            <tbody style="background:#fff">${renderTableRows(requirements, false).replace(/<tr>/g, '<tr style="' + trBorder + '">')}</tbody>
                        </table>
                    </div>
                </div>
                <div>
                    <h3 class="text-lg font-semibold mb-3 text-slate-800 border-l-4 border-primary pl-3">目录权限</h3>
                    <div style="overflow-x:auto;border:1px solid #e5e7eb;border-radius:4px">
                        <table style="${tableStyle}">
                            <thead><tr>
                                <th style="${thStyle}">目录</th>
                                <th style="${thStyle}">路径</th>
                                <th style="${thStyle};text-align:center">状态</th>
                            </tr></thead>
                            <tbody style="background:#fff">${renderTableRows(permissions, true).replace(/<tr>/g, '<tr style="' + trBorder + '">')}</tbody>
                        </table>
                    </div>
                </div>
                ${suggestions.length > 0 ? `
                <div class="notice notice--warning">
                    <i class="fa fa-wrench"></i>
                    <div>
                        <div class="notice-title">修复建议</div>
                        <ul class="list-disc list-inside space-y-1 notice-text">
                            ${suggestions.map(s => `<li>${s}</li>`).join('')}
                        </ul>
                    </div>
                </div>
                ` : ''}
            </div>
        `;
        contentEl.innerHTML = html;

        // Handle next button state
        nextBtn.classList.remove('btn-disabled');
        nextBtn.setAttribute('aria-disabled', 'false');

        if (!critical_passed) {
            nextBtn.addEventListener('click', function(e) {
                e.preventDefault();
                ui.confirm({
                    title: '环境不满足',
                    message: '检测到必需环境未满足，继续安装可能会失败。确定要继续吗？',
                    okText: '强制继续',
                    cancelText: '取消',
                    danger: true,
                }).then(confirmed => {
                    if (confirmed) window.location.href = nextBtn.href;
                });
            });
        }
    }

    function showError(message) {
        contentEl.innerHTML = `
            <div class="notice notice--error">
                <i class="fa fa-exclamation-triangle"></i>
                <div>
                    <div class="notice-title">检测失败</div>
                    <div class="notice-text">${message}</div>
                </div>
            </div>
            <div class="text-center mt-6">
                <button onclick="location.reload()" class="btn btn-primary">
                    <i class="fa fa-refresh mr-2"></i>重新检测
                </button>
            </div>`;
    }

    ui.fetch('check_environment')
        .then(data => {
            if (data.success) {
                renderResult(data);
            } else {
                showError(data.message || '未知错误');
            }
        })
        .catch(err => {
            console.error('Environment check error:', err);
            showError(err.message);
        });
});
</script>
