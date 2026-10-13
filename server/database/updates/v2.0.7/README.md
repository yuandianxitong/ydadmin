# 2.0.7 授权与请求一致性修复

在 `server/` 目录执行 `php webman yd:update`，随后重启全部 HTTP、队列和实时通信进程，并发布更新后的 Admin 前端。

本次迁移新增两张表，不修改现有业务行：

- `authorization_state`：权限和数据范围缓存的数据库版本，与授权变更在同一个事务中递增。
- `auth_revocations`：按 scope 隔离的 jti / sid 吊销记录，Redis 丢失后仍然有效；写入时有界清理过期记录。

令牌增加 `revocation_version` 协议标记。升级前的令牌拒绝继续使用，所有用户需要重新登录一次。旧协议退出记录只保存在 Redis 中，不能可靠地补齐历史吊销记录。

代码生成器的 API 输出改为 `admin/src/api/{module}/{model-kebab}.ts`。新生成的页面和表单会引用此路径；已有生成模块可继续使用原文件，重新生成时应核对自定义代码并迁移引用。
