<h2 class="text-2xl font-bold text-slate-800 mb-2">正在安装系统...</h2>
<p class="text-slate-500 mb-6">请不要关闭此页面，安装过程可能需要几分钟。</p>

<!-- Progress Bar -->
<div class="space-y-2 mb-6">
    <div class="flex justify-between items-baseline">
        <span id="progress-message" class="text-sm font-medium text-slate-600">正在准备...</span>
        <span id="progress-percent" class="text-lg font-bold text-primary">0%</span>
    </div>
    <progress id="progress-bar" class="progress progress-primary w-full" value="0" max="100"></progress>
</div>

<!-- Step Details -->
<div class="bg-slate-50 p-4 rounded-lg">
    <ul class="space-y-3" id="install-steps-list">
        <!-- JS will inject steps here -->
        <li class="flex items-center text-slate-400">
            <span class="loading loading-spinner loading-xs mr-3"></span>
            <span>正在获取安装步骤...</span>
        </li>
    </ul>
</div>

<!-- Log Output -->
<div class="collapse collapse-arrow bg-slate-50 mt-4">
    <input type="checkbox" id="log-toggle"/>
    <div class="collapse-title text-sm font-medium text-slate-600">
        查看实时日志
    </div>
    <div class="collapse-content">
        <div id="install-log" class="bg-black text-gray-300 p-3 rounded-lg font-mono text-xs max-h-48 overflow-y-auto custom-scrollbar">
            [INFO] Waiting for installation to start...
        </div>
    </div>
</div>

<div class="flex justify-end gap-3 mt-10">
    <button id="retry-btn" class="btn btn-secondary hidden">
        <i class="fa fa-repeat mr-2"></i>
        重试
    </button>
    <a href="?step=complete" id="next-btn" class="btn btn-primary hidden">
        安装完成，查看结果
        <i class="fa fa-arrow-right ml-2"></i>
    </a>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const ui = window.InstallUI;

    const state = {
        intervalId: null,
        logLines: ['[INFO] Initializing...'],
        steps: [],
    };

    const elements = {
        progressBar: document.getElementById('progress-bar'),
        progressPercent: document.getElementById('progress-percent'),
        progressMessage: document.getElementById('progress-message'),
        stepsList: document.getElementById('install-steps-list'),
        logContainer: document.getElementById('install-log'),
        nextBtn: document.getElementById('next-btn'),
        retryBtn: document.getElementById('retry-btn'),
    };
    
    function addLog(message, type = 'INFO') {
        const logMessage = `[${new Date().toLocaleTimeString()}] [${type}] ${message}`;
        state.logLines.push(logMessage);
        if (state.logLines.length > 100) state.logLines.shift();
        elements.logContainer.textContent = state.logLines.join('\n');
        elements.logContainer.scrollTop = elements.logContainer.scrollHeight;
    }

    function renderStepsList(steps) {
        state.steps = steps;
        elements.stepsList.innerHTML = steps.map(step => `
            <li data-step-key="${step.key}" class="flex items-center gap-3 p-2 rounded text-slate-500">
                <span class="step-icon w-6 text-center"><i class="fa fa-clock-o"></i></span>
                <span class="step-name flex-1">${step.name}</span>
                <span class="step-status text-xs">等待中...</span>
            </li>
        `).join('');
    }

    function updateUi(data) {
        addLog(`Progress: ${data.percent}%, ${data.message}`);
        
        elements.progressBar.value = data.percent;
        elements.progressPercent.textContent = `${data.percent}%`;
        elements.progressMessage.textContent = data.message;
        
        const stepEl = elements.stepsList.querySelector(`[data-step-key="${data.step_key}"]`);
        if (stepEl) {
            // Mark previous steps as complete
            let current = stepEl.previousElementSibling;
            while(current) {
                current.classList.remove('text-slate-500', 'font-bold');
                current.classList.add('text-success');
                const iconEl = current.querySelector('.step-icon');
                if (iconEl) iconEl.innerHTML = '<i class="fa fa-check"></i>';
                const statusEl = current.querySelector('.step-status');
                if (statusEl) statusEl.textContent = '完成';
                current = current.previousElementSibling;
            }
            
            // Mark current step as active
            stepEl.classList.add('font-bold', 'text-primary');
            const iconEl = stepEl.querySelector('.step-icon');
            if (iconEl) iconEl.innerHTML = '<span class="loading loading-spinner loading-sm"></span>';
            const statusEl = stepEl.querySelector('.step-status');
            if (statusEl) statusEl.textContent = '进行中...';
        }

        if (data.status === 'completed') {
            clearInterval(state.intervalId);
            addLog('Installation completed successfully!', 'SUCCESS');
            // Only mark structured step rows; ignore the initial placeholder row.
            elements.stepsList.querySelectorAll('li[data-step-key]').forEach(li => {
                li.classList.remove('text-slate-500', 'font-bold', 'text-primary');
                li.classList.add('text-success');
                const iconEl = li.querySelector('.step-icon');
                if (iconEl) iconEl.innerHTML = '<i class="fa fa-check"></i>';
                const statusEl = li.querySelector('.step-status');
                if (statusEl) statusEl.textContent = '完成';
            });
            elements.progressMessage.textContent = "系统安装成功！";
            elements.nextBtn.classList.remove('hidden');
            elements.retryBtn.classList.add('hidden');
        } else if (data.status === 'error') {
            clearInterval(state.intervalId);
            addLog(`Installation failed: ${data.message}`, 'ERROR');
            if(stepEl) {
                stepEl.classList.remove('font-bold', 'text-primary');
                stepEl.classList.add('text-error');
                const iconEl = stepEl.querySelector('.step-icon');
                if (iconEl) iconEl.innerHTML = '<i class="fa fa-times"></i>';
                const statusEl = stepEl.querySelector('.step-status');
                if (statusEl) statusEl.textContent = '失败';
            }
            elements.progressMessage.textContent = `错误: ${data.message}`;
            elements.retryBtn.classList.remove('hidden');
        }
    }

    function checkProgress() {
        ui.fetch('get_install_progress')
            .then(data => {
                if (!data || !data.success) throw new Error(data.message || '返回数据格式不正确');
                if (data.steps && state.steps.length === 0) renderStepsList(data.steps);
                updateUi(data);
            })
            .catch(err => {
                clearInterval(state.intervalId);
                addLog(`Error fetching progress: ${err.message}`, 'ERROR');
                elements.progressMessage.textContent = `错误: ${err.message}`;
                elements.retryBtn.classList.remove('hidden');
            });
    }

    elements.retryBtn.addEventListener('click', () => {
        ui.confirm({ title: '确认重试', message: '将返回到配置页面，之前填写的参数需要重新确认。', okText: '返回配置' })
          .then(ok => { if(ok) window.location.href = '?step=config'; });
    });
    
    // Prevent accidental navigation
    window.addEventListener('beforeunload', e => {
        if (elements.progressBar.value > 0 && elements.progressBar.value < 100) {
            e.preventDefault();
            e.returnValue = '安装正在进行中，离开此页面可能会导致安装失败。确定要离开吗？';
        }
    });

    // Start
    checkProgress();
    state.intervalId = setInterval(checkProgress, 1500);
});
</script>
