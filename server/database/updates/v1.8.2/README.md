# v1.8.2

## 变更说明

- 刷新系统页默认装修：`home`（轮播 + 公告 + 分类导航 + 内容列表）、`member`（用户信息卡 + 服务菜单），草稿与已发布同步覆盖为新默认组件树。
- `mobile_configs.tabbar_json`：将仍指向旧路径 `/static/tabbar/` 的图标批量改为 `/static/diy/tabbar/`（保留自定义项数与文案）。
- 静态素材随代码部署至 `public/static/diy/`，升级脚本不写 blob。

## 升级方式

```bash
cd server
php think yd:update
```
