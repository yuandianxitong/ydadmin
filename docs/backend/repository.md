# Repository 开发

Repository 是数据访问层，封装所有 ORM 查询，是唯一与 Model 交互的层。所有 Repository 继承 `core\base\Repository` 基类。

## 基类功能

### getModel 抽象方法

每个 Repository 必须实现 `getModel()` 方法，返回对应的 Model 实例：

```php
namespace app\repository\system;

use app\model\system\Admin;
use core\base\Repository;
use think\Model;

class AdminRepository extends Repository
{
    protected function getModel(): Model
    {
        return new Admin();
    }
}
```

基类在构造函数中调用 `getModel()` 并赋值给 `$this->model`，后续所有查询基于此实例。

## 内置 CRUD 方法

基类提供以下开箱即用的方法，覆盖常见数据操作：

### 查询方法

```php
// 按主键查找
$admin = $this->adminRepository->find(1);
// 返回: ['id' => 1, 'username' => 'admin', ...] 或 null

// 按条件查找单条
$admin = $this->adminRepository->findWhere(['username' => 'admin']);

// 获取分页列表
$result = $this->adminRepository->getList(
    where: ['status' => 1],      // 查询条件
    page: 1,                      // 页码
    limit: 15,                    // 每页条数
    order: 'id desc'              // 排序
);
// 返回: { list: [...], pagination: { current_page, per_page, total, last_page } }

// 获取所有记录（不分页）
$list = $this->adminRepository->getAll(['status' => 1], 'created_at desc');

// 统计数量
$count = $this->adminRepository->count(['status' => 1]);

// 检查是否存在
$exists = $this->adminRepository->exists(['username' => 'admin']);

// 获取单个字段值
$email = $this->adminRepository->value(['id' => 1], 'email');

// 获取字段列表
$usernames = $this->adminRepository->column(['status' => 1], 'username');
$map = $this->adminRepository->column(['status' => 1], 'username', 'id');
// 返回: [1 => 'admin', 2 => 'editor', ...]
```

### 写入方法

```php
// 创建记录
$admin = $this->adminRepository->create([
    'username' => 'newadmin',
    'password' => 'hashed_password',
    'email'    => 'admin@example.com',
]);
// 返回创建后的完整数组

// 批量创建
$collection = $this->adminRepository->createAll([
    ['username' => 'admin1', ...],
    ['username' => 'admin2', ...],
]);

// 更新记录
$success = $this->adminRepository->update(1, ['nickname' => '新昵称']);
// 返回 bool

// 按条件更新
$affectedRows = $this->adminRepository->updateWhere(
    ['status' => 0],
    ['status' => 1]
);

// 删除记录（软删除）
$success = $this->adminRepository->delete(1);

// 按条件删除
$affectedRows = $this->adminRepository->deleteWhere(['status' => 0]);

// 字段自增/自减
$this->adminRepository->inc(['id' => 1], 'login_count');
$this->adminRepository->dec(['id' => 1], 'balance', 100);
```

## 自定义查询方法

当内置方法无法满足需求时，在 Repository 中添加自定义方法：

### 关联查询

```php
class AdminRepository extends Repository
{
    protected function getModel(): Model
    {
        return new Admin();
    }

    /**
     * 获取管理员列表（包含角色）
     */
    public function getListWithRoles(array $where = [], int $page = 1, int $limit = 15): array
    {
        $query = $this->model->with(['roles'])->where($where);

        $total = $query->count();
        $list = $query->page($page, $limit)
            ->order('created_at desc')
            ->select()
            ->toArray();

        return [
            'list'       => $list,
            'pagination' => [
                'current_page' => $page,
                'per_page'     => $limit,
                'total'        => $total,
                'last_page'    => ceil($total / $limit),
            ],
        ];
    }
}
```

### 条件查找

```php
/**
 * 根据用户名查找管理员（含密码字段）
 */
public function findByUsername(string $username): ?array
{
    $result = $this->model->where('username', $username)->find();
    if (!$result) {
        return null;
    }
    // 登录需要访问密码字段，临时清空隐藏
    $result->hidden([]);
    return $result->toArray();
}

/**
 * 检查用户名是否已存在（排除指定ID）
 */
public function existsUsername(string $username, int $excludeId = 0): bool
{
    $query = $this->model->where('username', $username);
    if ($excludeId > 0) {
        $query->where('id', '<>', $excludeId);
    }
    return $query->count() > 0;
}
```

### 复杂更新

```php
/**
 * 更新最后登录信息
 */
public function updateLastLogin(int $id, string $ip): bool
{
    return $this->update($id, [
        'last_login_ip'   => $ip,
        'last_login_time' => date('Y-m-d H:i:s'),
        'login_count'     => $this->model->where('id', $id)->value('login_count') + 1,
    ]);
}

/**
 * 分配角色（多对多关联同步）
 */
public function assignRoles(int $adminId, array $roleIds): bool
{
    $admin = $this->model->find($adminId);
    if (!$admin) {
        return false;
    }
    return $admin->roles()->sync($roleIds) !== false;
}
```

## 返回值约定

- 查询方法统一返回**数组**（`toArray()`），而非 Model 对象
- 单条查询找不到返回 `null`
- 写入方法失败时抛出 `BusinessException`，不返回 `false`
- 分页查询返回 `{ list, pagination }` 结构

## 注意事项

- Repository 是唯一与 Model 交互的层，Service 和 Controller 不直接使用 Model
- 所有异常由基类自动包装为 `BusinessException`
- 自定义查询方法通过 `$this->model` 访问 ORM，保持查询逻辑集中
- 分页返回格式与前端分页组件对接，包含 `current_page`、`per_page`、`total`、`last_page`
