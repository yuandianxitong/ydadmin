# 2.0.8：可靠投递、幂等与异步任务

新增 queue_outbox、http_idempotency、background_tasks，SQL 可重复执行，不清空已有记录。

在备份后执行 `php webman yd:update --dry-run` 和 `php webman yd:update`，然后重启全部进程。
本次测试只使用专用测试库，不自动升级业务库。

多机部署应配置共享的 TASK_STORAGE_PATH。
