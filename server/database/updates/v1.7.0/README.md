# v1.7.0 升级说明

## 变更
- 新增 `ai_artifacts` 表：AI 编译工件状态机（compiled/checking/checked_passed/checked_failed/applied/superseded）。
- 补齐「AI 建模向导」菜单（id 222，随子项目2编译链路引入但未随 v1.6.0 发行）及权限点 `ai.ydspec.use`（补齐历史缺失）。
- 新增权限点 `ai.ydspec.apply`（编译结果门禁应用）与「AI 建模向导 / 应用」按钮菜单（id 223）。

## 影响
- YdSpec 编译后自动跑 7 项检查（php_lint/required_files/layer_compliance/path_convention/forbidden_patterns/ddl_executability/route_loading）；仅检查通过的工件可经门禁 apply。
- 无破坏性变更；`ai.studio.*` 与既有业务表不受影响。

## 升级
```bash
cd server
php think yd:update
```
