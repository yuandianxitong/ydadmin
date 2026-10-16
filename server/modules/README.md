# 模块描述

每个实体使用 `{module}/{model_snake}.json`，模块标识是 `{module}.{model_snake}`。
当前格式 `schema_version=1`，声明表、字段、权限、路由、产物以及软删除、状态和数据权限能力。

内置模块保持既有权限码，可通过 `permission_prefix` 声明其权限命名空间。
新生成模块的描述和控制器从同一个 `ModuleBlueprint` 路由定义生成。
描述是 JSON 数据，不执行脚本，不自动授权、安装模块或加载插件。

运行 `php webman module:check` 检查描述；完整开发仓库可加 `--files`。
