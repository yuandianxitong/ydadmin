<?php
$config = $_SESSION['install_config'] ?? [];
?>
<div class="text-center">
    <h2 class="text-2xl font-bold text-slate-800 sm:text-3xl">🎉 安装完成！</h2>
    <p class="text-slate-500 mt-2">恭喜，您的系统已准备就绪。</p>
</div>

<div class="my-8 w-full max-w-2xl mx-auto space-y-6">
    <!-- 主要操作 -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <a href="../index.php" target="_blank" class="btn btn-primary btn-lg h-auto py-4 flex-col">
            <i class="fa fa-home text-xl mb-1"></i>
            <span>访问网站前台</span>
        </a>
         <a href="/admin" target="_blank" class="btn btn-secondary btn-lg h-auto py-4 flex-col">
            <i class="fa fa-user-secret text-xl mb-1"></i>
            <span>登录系统后台</span>
        </a>
    </div>

    <!-- 登录信息 -->
     <div class="text-left bg-slate-50 p-4 rounded-lg text-sm">
         <p class="flex items-center">
             <strong class="w-24 shrink-0">管理员账号:</strong>
             <code class="bg-slate-200 px-2 py-1 rounded"><?= htmlspecialchars($config['admin_username'] ?? 'admin') ?></code>
         </p>
         <p class="flex items-center mt-2">
             <strong class="w-24 shrink-0">管理员密码:</strong>
             <span class="text-warning font-bold">您在安装时设置的密码</span>
         </p>
     </div>
</div>

<!-- 安全提示 -->
<div class="notice notice--error max-w-2xl mx-auto">
     <i class="fa fa-shield"></i>
     <div>
         <div class="notice-title">重要安全提示</div>
         <div class="notice-text">为保障系统安全，请立即删除安装程序。</div>
         <div class="mt-3">
            <button id="delete-installer-btn" class="btn btn-error btn-sm">
                <i class="fa fa-trash mr-2"></i>
                立即删除安装程序
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const ui = window.InstallUI;

    const deleteBtn = document.getElementById('delete-installer-btn');
    deleteBtn.addEventListener('click', () => {
        ui.confirm({
            title: '确认删除安装程序?',
            message: '这是重要的安全措施。删除后将无法重新安装。确定要永久删除 public/install 目录吗?',
            okText: '是的, 删除',
            danger: true
        }).then(confirmed => {
            if (!confirmed) return;
            
            const toastId = ui.toast('正在删除...', 'loading', 0);
            ui.fetch('delete_installer')
                .then(data => {
                    ui.hideToast(toastId);
                    if (data.success) {
                        ui.toast('安装程序已成功删除！', 'success');
                        deleteBtn.disabled = true;
                        deleteBtn.innerHTML = '<i class="fa fa-check mr-2"></i>已删除';
                    } else {
                        throw new Error(data.message);
                    }
                })
                .catch(err => {
                    ui.hideToast(toastId);
                    ui.alert({
                        title: '删除失败',
                        message: `自动删除失败，请手动删除 public/install 目录。<br><br><strong>错误:</strong> ${err.message}`,
                        error: true
                    });
                });
        });
    });
    
    // Cleanup
    setTimeout(() => {
        ui.fetch('clear_session').catch(err => console.error("Session clear failed:", err));
    }, 10000);
});
</script>
