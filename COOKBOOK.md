# CRUD 开发 Cookbook

从零开始创建一个完整的管理模块，以「产品管理」为例。

## 第一步：创建数据库表

```sql
CREATE TABLE `products` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL COMMENT '产品名称',
    `category_id` int unsigned DEFAULT 0 COMMENT '分类ID',
    `price` decimal(10,2) DEFAULT 0 COMMENT '价格',
    `cover` varchar(255) DEFAULT '' COMMENT '封面图',
    `description` text COMMENT '描述',
    `status` tinyint DEFAULT 1 COMMENT '状态 1启用 0禁用',
    `sort` int DEFAULT 0 COMMENT '排序',
    `created_at` datetime DEFAULT NULL,
    `updated_at` datetime DEFAULT NULL,
    `deleted_at` datetime DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='产品表';
```

## 第二步：生成 CRUD 代码

```bash
cd server
php think make:crud products --module=product --model=Product
```

自动生成文件：

**后端：**
- `app/model/product/Product.php` — 模型
- `app/repository/product/ProductRepository.php` — 数据访问层
- `app/service/product/ProductService.php` — 业务逻辑层
- `app/adminapi/controller/v1/product/ProductController.php` — 控制器
- `app/adminapi/validate/v1/product/ProductValidate.php` — 验证器
- `app/adminapi/route/product.php` — 路由

**前端：**
- `admin/src/api/product.ts` — API 接口
- `admin/src/views/product/index.vue` — 列表页
- `admin/src/views/product/components/ProductForm.vue` — 表单组件

## 第三步：注册路由

检查 `app/adminapi/route/product.php` 是否已自动注册。ThinkPHP 的多应用模式会自动加载 `route/` 目录下的路由文件。

## 第四步：添加菜单和权限

在管理后台「系统管理 → 菜单管理」中添加：

1. **一级菜单**：产品管理（目录类型）
2. **二级菜单**：产品列表（菜单类型，组件路径：`product/index`）
3. **按钮权限**：新增/编辑/删除（按钮类型，权限标识：`product.product.store` 等）

## 第五步：验证

访问后台，应能看到产品管理菜单，点击进入列表页，测试增删改查功能。

---

## 常见扩展场景

### 自定义搜索条件

在 `ProductRepository.php` 的 `getSearchList()` 方法中修改查询条件：

```php
public function getSearchList(array $params, int $page = 1, int $size = 20): array
{
    $query = $this->model->where('deleted_at', null);

    if (!empty($params['keyword'])) {
        $query->whereLike('name', "%{$params['keyword']}%");
    }

    if (isset($params['status']) && $params['status'] !== '') {
        $query->where('status', $params['status']);
    }

    if (!empty($params['category_id'])) {
        $query->where('category_id', $params['category_id']);
    }

    return $query->order('sort', 'asc')
        ->order('id', 'desc')
        ->paginate(['page' => $page, 'list_rows' => $size])
        ->toArray();
}
```

### 文件上传字段

前端表单中使用 Upload 组件：

```vue
<Upload v-model="formData.cover" type="image" />
```

后端 Model 中无需特殊处理，字段存储相对路径。前端通过 `appStore.getImageUrl()` 拼接完整 URL 展示。

### 字典值关联

前端列表中使用 DictValue 组件展示：

```vue
<DictValue :options="dictOptions.product_type" :value="row.type" />
```

在页面中获取字典数据：

```typescript
const { optionsData: dictOptions } = useDictOptions<{
    product_type: any[]
}>(['product_type'])
```

### 树形/层级数据

如果表有 `parent_id` 字段，Repository 中返回树形结构：

```php
public function getTree(): array
{
    $list = $this->model->where('deleted_at', null)
        ->order('sort', 'asc')
        ->select()->toArray();

    return $this->listToTree($list);
}
```

前端使用 `el-tree` 或 `el-table`（带 `row-key` 和 `tree-props`）展示。

### 关联关系（下拉选择）

当产品需要选择分类时，Controller 提供 options 接口：

```php
public function categoryOptions()
{
    $options = $this->productService->getCategoryOptions();
    return $this->success('获取成功', $options);
}
```

Service 调用 Repository 获取数据：

```php
public function getCategoryOptions(): array
{
    return $this->categoryRepository->getAll(['id', 'name'], ['status' => 1]);
}
```

前端表单中使用 `el-select`：

```vue
<el-select v-model="formData.category_id" placeholder="请选择分类">
    <el-option
        v-for="item in categoryOptions"
        :key="item.id"
        :label="item.name"
        :value="item.id"
    />
</el-select>
```
