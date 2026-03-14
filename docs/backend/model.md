# Model 开发

Model 是 ORM 映射层，负责定义表结构、关联关系、访问器和修改器。所有 Model 继承 `core\base\Model` 基类。

## 基类功能

基类 `core\base\Model` 继承自 `think\Model`，预置了以下通用配置：

```php
abstract class Model extends BaseModel
{
    use SoftDelete;                        // 统一使用软删除

    protected $deleteTime = 'deleted_at';  // 软删除字段
    protected $autoWriteTimestamp = true;   // 自动写入时间戳
    protected $createTime = 'created_at';  // 创建时间字段
    protected $updateTime = 'updated_at';  // 更新时间字段
    protected $dateFormat = 'Y-m-d H:i:s'; // 时间格式

    protected $hidden = ['deleted_at'];    // 默认隐藏软删除字段
}
```

### 内置访问器

基类提供时间相关的通用访问器：

```php
$admin->created_at_text;       // 格式化创建时间: "2024-01-15 10:30:00"
$admin->updated_at_text;       // 格式化更新时间
$admin->created_at_timestamp;  // 创建时间戳: 1705285800
$admin->updated_at_timestamp;  // 更新时间戳
```

### 辅助方法

```php
// 获取状态文本
protected function getStatusText(int $status, array $statusMap): string

// JSON 字段获取器
protected function getJsonAttr($value): array

// JSON 字段修改器
protected function setJsonAttr($value): string
```

## 时间戳字段约定

所有表统一使用以下时间戳字段：

| 字段 | 类型 | 说明 |
|------|------|------|
| `created_at` | `datetime` | 创建时间，自动填充 |
| `updated_at` | `datetime` | 更新时间，自动填充 |
| `deleted_at` | `datetime NULL` | 软删除时间，NULL 表示未删除 |

迁移文件示例：

```php
$table->dateTime('created_at')->null()->comment('创建时间');
$table->dateTime('updated_at')->null()->comment('更新时间');
$table->dateTime('deleted_at')->null()->comment('删除时间');
```

## 软删除

基类已集成 `SoftDelete` trait，删除操作自动变为软删除（填充 `deleted_at`）：

```php
// 通过 Repository 删除时自动软删除
$this->adminRepository->delete($id);

// 查询时自动排除已删除记录
$this->adminRepository->getAll();

// 如需查询包含已删除的记录，在 Repository 中：
$this->model->withTrashed()->where($where)->select();

// 只查询已删除的记录
$this->model->onlyTrashed()->where($where)->select();
```

## 关联关系

### 定义关联

```php
namespace app\model\system;

use core\base\Model;
use think\model\relation\BelongsToMany;
use think\model\relation\HasMany;

class Admin extends Model
{
    protected $table = 'admins';

    // 多对多：管理员 ↔ 角色
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,        // 关联模型
            'admin_roles',      // 中间表
            'admin_id',         // 当前模型外键
            'role_id'           // 关联模型外键
        );
    }

    // 一对多：管理员 → 登录日志
    public function loginLogs(): HasMany
    {
        return $this->hasMany(AdminLoginLog::class, 'admin_id');
    }

    // 一对多：管理员 → 操作日志
    public function operationLogs(): HasMany
    {
        return $this->hasMany(AdminOperationLog::class, 'admin_id');
    }
}
```

### 常用关联类型

```php
use think\model\relation\HasOne;
use think\model\relation\HasMany;
use think\model\relation\BelongsTo;
use think\model\relation\BelongsToMany;

// 一对一
public function profile(): HasOne
{
    return $this->hasOne(UserProfile::class, 'user_id');
}

// 反向一对一/一对多
public function department(): BelongsTo
{
    return $this->belongsTo(Department::class, 'department_id');
}
```

## 访问器

访问器用于在读取属性时自动转换或计算值：

```php
class Admin extends Model
{
    // 状态文本
    public function getStatusTextAttr($value, $data): string
    {
        $status = [1 => '正常', 0 => '禁用'];
        return $status[$data['status']] ?? '未知';
    }

    // 头像完整 URL
    public function getAvatarUrlAttr($value, $data): string
    {
        if (empty($data['avatar'])) {
            return '/static/images/default-avatar.png';
        }
        if (strpos($data['avatar'], 'http') === 0) {
            return $data['avatar'];
        }
        return request()->domain() . $data['avatar'];
    }
}
```

使用方式：

```php
$admin->status_text;   // "正常"
$admin->avatar_url;    // "https://example.com/uploads/avatar.jpg"
```

## 修改器

修改器用于在写入属性时自动转换值：

```php
class Admin extends Model
{
    // 密码自动加密
    public function setPasswordAttr($value): string
    {
        return password_hash($value, PASSWORD_DEFAULT);
    }
}
```

写入时自动触发：

```php
// password 自动被 password_hash 加密
$this->adminRepository->create(['username' => 'admin', 'password' => '123456']);
```

## 字段配置

```php
class Admin extends Model
{
    protected $table = 'admins';

    // 允许批量赋值的字段
    protected $fillable = [
        'username', 'email', 'mobile', 'password', 'nickname',
        'avatar', 'department', 'position', 'status',
    ];

    // 序列化时隐藏的字段
    protected $hidden = ['password', 'deleted_at'];

    // 字段类型自动转换
    protected $type = [
        'status'      => 'integer',
        'login_count' => 'integer',
        'last_login_time' => 'datetime',
    ];
}
```

## 完整示例

```php
namespace app\model\system;

use core\base\Model;
use think\model\relation\BelongsToMany;
use think\model\relation\HasMany;

class Admin extends Model
{
    protected $table = 'admins';

    protected $fillable = [
        'username', 'email', 'mobile', 'password', 'nickname',
        'avatar', 'department', 'position', 'status',
        'last_login_ip', 'last_login_time', 'login_count',
    ];

    protected $hidden = ['password', 'deleted_at'];

    protected $type = [
        'status'      => 'integer',
        'login_count' => 'integer',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'admin_roles', 'admin_id', 'role_id');
    }

    public function getStatusTextAttr($value, $data): string
    {
        return [1 => '正常', 0 => '禁用'][$data['status']] ?? '未知';
    }

    public function setPasswordAttr($value): string
    {
        return password_hash($value, PASSWORD_DEFAULT);
    }
}
```

## 注意事项

- Model 只做 ORM 映射，不包含业务逻辑
- 状态字段约定：`status`（1 启用，0 禁用）
- 所有 Model 默认启用软删除，`deleted_at` 默认隐藏
- 关联查询在 Repository 中通过 `with()` 加载，避免 N+1 问题
