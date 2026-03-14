# 贡献指南

感谢你对 Dev007 Framework 的关注！本文档将帮助你了解如何参与贡献。

## 开发环境搭建

### 系统要求

| 工具 | 版本 | 用途 |
|------|------|------|
| PHP | >= 8.0 | 后端运行环境 |
| Composer | >= 2.0 | PHP 依赖管理 |
| Node.js | >= 18 | 前端构建 |
| pnpm | >= 8 | 前端包管理 |
| MySQL | >= 5.7 | 数据库 |

### 拉取代码

```bash
git clone https://github.com/your-org/dev007-framework.git
cd dev007-framework
```

### 后端环境

```bash
cd server
composer install

# 复制环境配置
cp .env.example .env

# 修改数据库连接信息
# 编辑 .env 文件

# 执行数据库迁移
php think migrate:run

# 执行数据填充
php think seed:run
```

### 前端环境（管理后台）

```bash
cd admin
pnpm install
pnpm dev
```

### 移动端环境

```bash
cd uniapp
pnpm install
pnpm dev:h5       # H5 开发
pnpm dev:mp-weixin  # 微信小程序开发
```

### 文档环境

```bash
cd docs
pnpm install
pnpm dev
```

## 代码规范

### 后端（PHP）

遵循 **PSR-12** 编码规范。

#### 关键要求

- 使用 `declare(strict_types=1);` 严格类型声明
- 类名使用 PascalCase，方法名使用 camelCase
- 文件头部包含项目版权注释
- 所有公共方法必须添加 PHPDoc 注释
- 时间戳字段统一使用 `created_at`、`updated_at`、`deleted_at`
- 状态字段使用 `status`（1 启用，0 禁用）

#### 分层架构规范

- **Controller** 只调用 Service，不直接操作 Repository 或 Model
- **Service** 只调用 Repository，不直接使用 `Db::table()` 或 Model 静态方法
- **Repository** 封装所有 ORM 查询，是唯一与 Model 交互的层
- 事务管理（`Db::startTrans()` / `commit()` / `rollback()`）放在 Service 层
- 副作用逻辑放在 Listener 中，通过事件系统触发

```php
// 正确 - Service 调用 Repository
class UserService extends Service
{
    protected UserRepository $userRepository;

    public function getUser(int $id): array
    {
        return $this->userRepository->findById($id);
    }
}

// 错误 - Service 直接操作 Model
class UserService extends Service
{
    public function getUser(int $id): array
    {
        return User::find($id)->toArray(); // 禁止
    }
}
```

### 前端（TypeScript / Vue）

遵循项目 **ESLint** 配置规则。

#### 关键要求

- 使用 TypeScript 严格模式
- Vue 组件使用 Composition API + `<script setup lang="ts">`
- 组件名使用 PascalCase，文件名使用 kebab-case
- 使用 Element Plus 组件库
- API 调用统一使用 `src/utils/request.ts` 封装的 `myRequest`

```bash
# 运行代码检查
cd admin
pnpm lint
```

### 移动端（UniApp）

- 使用 TypeScript
- 自定义组件以 `d-` 为前缀
- 利用 easycom 自动注册
- 遵循 UniApp 条件编译规范（`#ifdef` / `#ifndef`）

## 分支策略

项目采用 Git Flow 变体，维护以下长期分支：

```
main          ← 生产环境代码，始终可部署
  └── develop ← 开发主分支，所有功能分支合入此处
       ├── feature/xxx ← 新功能开发
       ├── fix/xxx     ← Bug 修复
       └── docs/xxx    ← 文档更新
```

### 分支命名规范

| 分支类型 | 命名格式 | 示例 |
|---------|---------|------|
| 功能分支 | `feature/{描述}` | `feature/add-export-module` |
| 修复分支 | `fix/{描述}` | `fix/login-token-expired` |
| 文档分支 | `docs/{描述}` | `docs/add-payment-guide` |
| 重构分支 | `refactor/{描述}` | `refactor/optimize-query` |

### 工作流程

1. 从 `develop` 分支创建功能分支
2. 在功能分支上开发和提交
3. 完成后创建 Pull Request 合入 `develop`
4. 定期从 `develop` 合入 `main` 发布新版本

```bash
# 创建功能分支
git checkout develop
git pull origin develop
git checkout -b feature/my-feature

# 开发完成后推送
git push -u origin feature/my-feature
```

## 提交信息格式

遵循 **Conventional Commits** 规范。

### 格式

```
<type>(<scope>): <description>

[optional body]

[optional footer(s)]
```

### 类型（type）

| 类型 | 说明 | 示例 |
|------|------|------|
| `feat` | 新功能 | `feat(admin): add export to Excel` |
| `fix` | Bug 修复 | `fix(auth): resolve token refresh loop` |
| `docs` | 文档变更 | `docs: add payment integration guide` |
| `style` | 代码格式（不影响逻辑） | `style: fix indentation` |
| `refactor` | 重构（不新增功能、不修复 Bug） | `refactor(service): extract base query` |
| `perf` | 性能优化 | `perf(query): add database index` |
| `test` | 测试相关 | `test: add user service unit tests` |
| `chore` | 构建/工具/依赖变更 | `chore: upgrade element-plus to v2.8` |
| `ci` | CI/CD 变更 | `ci: add deploy workflow` |

### 范围（scope）

可选项，标识影响的模块范围：

- `admin` — 管理后台前端
- `server` — 后端
- `uniapp` — 移动端
- `auth` — 认证模块
- `plugin` — 插件系统
- `docs` — 文档

### 示例

```
feat(server): add announcement management module

- Add AnnouncementController, Service, Repository
- Add database migration for announcements table
- Register CRUD routes

Closes #42
```

```
fix(uniapp): prevent duplicate payment requests

Add loading state check before calling pay API to
prevent users from triggering multiple payments.
```

## PR 流程

### 创建 Pull Request

1. 确保代码通过本地检查
2. 推送分支到远程仓库
3. 在 GitHub 创建 Pull Request
4. 填写 PR 描述模板

### PR 描述模板

```markdown
## 概述
简要说明本次变更的目的和内容。

## 变更内容
- 具体变更点 1
- 具体变更点 2

## 测试方案
- [ ] 测试项 1
- [ ] 测试项 2

## 关联 Issue
Closes #xxx
```

### PR 检查清单

在提交 PR 前，请确认以下事项：

- [ ] 代码符合项目代码规范
- [ ] 后端代码遵循分层架构（Controller -> Service -> Repository -> Model）
- [ ] 新增的副作用通过 Listener 处理
- [ ] 数据库变更通过迁移文件管理
- [ ] TypeScript 类型检查通过（`pnpm type-check`）
- [ ] ESLint 检查通过（`pnpm lint`）
- [ ] 新功能已编写对应文档或注释
- [ ] 提交信息符合 Conventional Commits 规范

### 审查流程

1. 至少需要 **1 位** 团队成员审查通过
2. CI 流水线检查通过（代码检查、类型检查）
3. 审查通过后由审查者合并到目标分支

## 测试要求

### 后端测试

推荐对 Service 层和 Repository 层编写单元测试：

```bash
cd server
php think test
```

#### 测试目录结构

```
server/
└── tests/
    ├── unit/
    │   ├── service/       # Service 层单元测试
    │   └── repository/    # Repository 层单元测试
    └── feature/
        └── api/           # API 接口测试
```

#### 测试编写规范

- 测试类名以 `Test` 结尾
- 测试方法名以 `test` 开头，使用 camelCase
- 每个测试方法只验证一个行为
- 使用有意义的断言消息

```php
class UserServiceTest extends TestCase
{
    public function testCreateUserReturnsUserId(): void
    {
        $service = app(UserService::class);
        $userId = $service->createUser([
            'mobile' => '13800138000',
            'password' => '123456',
        ]);

        $this->assertIsInt($userId);
        $this->assertGreaterThan(0, $userId);
    }
}
```

### 前端测试

前端代码建议使用 TypeScript 类型系统作为第一道防线：

```bash
cd admin
pnpm type-check   # TypeScript 类型检查
pnpm lint          # ESLint 代码规范检查
```

## 版本发布

版本号遵循 [语义化版本 2.0.0](https://semver.org/lang/zh-CN/)：

- **主版本号（MAJOR）**：不兼容的 API 变更
- **次版本号（MINOR）**：向下兼容的功能新增
- **修订号（PATCH）**：向下兼容的 Bug 修复

发布流程：

1. 从 `develop` 创建 release 分支
2. 更新版本号和更新日志
3. 合并到 `main` 并打 tag
4. 合并回 `develop`
