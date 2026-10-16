<?php

declare(strict_types=1);

return [
    'invalid_idempotency_key' => '幂等键必须为 8 到 128 位字母、数字或 . _ : -',
    'idempotency_conflict' => '该幂等键已用于不同请求，请使用新的键',
    'response_too_large' => '响应超过幂等结果保存上限',
    'invalid_import' => '请上传符合大小限制的 CSV 文件',
    'invalid_export' => '不支持该导出模块',
    'not_ready' => '服务依赖尚未就绪',
    'import_task' => '提交会员导入任务',
    'export_task' => '提交数据导出任务',
    'cancel_task' => '取消后台任务',
    'task_not_found' => '任务不存在或已过期',
    'task_not_ready' => '导出文件尚未就绪',
];
