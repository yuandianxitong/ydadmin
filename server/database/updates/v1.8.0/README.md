# v1.8.0 升级说明

## 变更内容

新增移动端装修能力：

- 表：`diy_pages`、`diy_page_versions`、`diy_links`、`mobile_configs`
- 菜单：一级「装修」及子菜单（页面装修、自定义页面、底部导航、主题风格、链接管理）
- 权限：`diy.*`、`mobile.config.*`

## 升级步骤

```bash
cd server
php think yd:update --dry-run
php think yd:update
```

## 说明

- `update.sql` 使用 `CREATE TABLE IF NOT EXISTS` / `INSERT IGNORE`，可安全重跑。
- 种子首页/个人中心装修数据与默认 `mobile_configs` 仅写入全新安装的 `init.sql`；老环境升级后首次进入编辑器保存即可生成系统页，主题/tabBar 可在后台配置页保存生成。
