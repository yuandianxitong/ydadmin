# v1.8.1

## 变更说明

- `mobile_configs` 补齐 `home_app_code` 字段（启动首页所属应用 code），供底部导航「首页入口」落库。
- 修复已执行过 v1.8.0、但建表时尚未包含该列的环境。

## 升级方式

```bash
cd server
php think yd:update
```
