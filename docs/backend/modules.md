# 内置模块

框架提供以下内置业务模块，开箱即用。每个模块均遵循 `Controller → Service → Repository → Model` 分层架构。

## 公告管理 (Announcement)

公告模块支持草稿/发布状态管理，发布时自动记录发布时间。

### 管理端 API

| 方法 | 路径 | 说明 | 权限 |
|------|------|------|------|
| GET | /adminapi/announcement/list | 公告列表 | announcement.list |
| GET | /adminapi/announcement/detail/:id | 公告详情 | announcement.detail |
| POST | /adminapi/announcement | 创建公告 | announcement.create |
| PUT | /adminapi/announcement/:id | 更新公告 | announcement.update |
| PUT | /adminapi/announcement/:id/status | 更新状态 | announcement.update |
| DELETE | /adminapi/announcement/:id | 删除公告 | announcement.delete |

### C端 API

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | /api/announcement/list | 已发布公告列表 |
| GET | /api/announcement/detail/:id | 公告详情 |

### 请求参数

**创建/更新公告：**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| title | string | 是 | 公告标题 |
| content | string | 是 | 公告内容（富文本） |
| type | int | 是 | 公告类型 |
| status | int | 否 | 状态：0=草稿，1=已发布 |
| sort | int | 否 | 排序值，默认0 |

### 响应示例

```json
{
    "code": 200,
    "message": "success",
    "data": {
        "id": 1,
        "title": "系统升级通知",
        "content": "<p>系统将于今晚进行升级维护...</p>",
        "type": 1,
        "status": 1,
        "sort": 0,
        "publish_at": "2025-03-14 10:00:00",
        "created_at": "2025-03-14 09:30:00",
        "updated_at": "2025-03-14 10:00:00"
    }
}
```

### 业务逻辑

- 当 `status` 设置为已发布（1）时，系统自动填充 `publish_at` 为当前时间
- 从草稿变为已发布时自动设置发布时间，反复切换不会覆盖已有发布时间
- 创建公告后触发 `announcement.created` 事件

---

## 协议管理 (Agreement)

协议模块用于管理用户协议、隐私政策等法律文本，支持通过唯一标识码（code）在C端获取。

### 管理端 API

| 方法 | 路径 | 说明 | 权限 |
|------|------|------|------|
| GET | /adminapi/agreement/list | 协议列表 | agreement.list |
| GET | /adminapi/agreement/detail/:id | 协议详情 | agreement.detail |
| POST | /adminapi/agreement | 创建协议 | agreement.create |
| PUT | /adminapi/agreement/:id | 更新协议 | agreement.update |
| DELETE | /adminapi/agreement/:id | 删除协议 | agreement.delete |

### C端 API

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | /api/agreement/:code | 根据标识码获取协议 |

### 请求参数

**创建/更新协议：**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| title | string | 是 | 协议标题 |
| code | string | 是 | 唯一标识码（如 `user_agreement`、`privacy_policy`） |
| content | string | 是 | 协议内容（富文本） |
| status | int | 否 | 状态：0=禁用，1=启用 |

### 响应示例

```json
{
    "code": 200,
    "message": "success",
    "data": {
        "id": 1,
        "title": "用户服务协议",
        "code": "user_agreement",
        "content": "<p>欢迎使用本服务...</p>",
        "status": 1,
        "created_at": "2025-01-01 00:00:00",
        "updated_at": "2025-03-14 10:00:00"
    }
}
```

### 业务逻辑

- `code` 字段在创建时进行唯一性校验，重复会抛出 `BusinessException`
- C端通过 `code` 获取协议内容，无需知道数据库 ID

---

## 地区数据 (Region)

地区模块提供省市区三级联动数据管理，支持树形结构查询。

### 管理端 API

| 方法 | 路径 | 说明 | 权限 |
|------|------|------|------|
| GET | /adminapi/region/list | 地区列表 | region.list |
| GET | /adminapi/region/tree | 地区树 | - |
| GET | /adminapi/region/detail/:id | 地区详情 | region.detail |
| POST | /adminapi/region | 创建地区 | region.create |
| PUT | /adminapi/region/:id | 更新地区 | region.update |
| DELETE | /adminapi/region/:id | 删除地区 | region.delete |

### C端 API

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | /api/region/tree | 地区树数据 |
| GET | /api/region/children | 子级地区列表 |

### 请求参数

**C端获取子级地区：**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| parent_id | int | 否 | 父级地区 ID，默认0（获取省级） |

### 响应示例（树形结构）

```json
{
    "code": 200,
    "message": "success",
    "data": [
        {
            "id": 1,
            "name": "北京市",
            "parent_id": 0,
            "level": 1,
            "children": [
                {
                    "id": 2,
                    "name": "市辖区",
                    "parent_id": 1,
                    "level": 2,
                    "children": [
                        {
                            "id": 3,
                            "name": "东城区",
                            "parent_id": 2,
                            "level": 3,
                            "children": []
                        }
                    ]
                }
            ]
        }
    ]
}
```

---

## 版本管理 (App Version)

版本模块用于管理客户端（Android/iOS/HarmonyOS）的版本发布和更新检测。

### 管理端 API

| 方法 | 路径 | 说明 | 权限 |
|------|------|------|------|
| GET | /adminapi/version/list | 版本列表 | version.list |
| GET | /adminapi/version/detail/:id | 版本详情 | version.detail |
| POST | /adminapi/version | 创建版本 | version.create |
| PUT | /adminapi/version/:id | 更新版本 | version.update |
| DELETE | /adminapi/version/:id | 删除版本 | version.delete |

### C端 API

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | /api/version/check | 检查版本更新 |

**请求参数：**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| platform | string | 是 | 平台标识：android / ios / harmony |
| version_code | int | 是 | 当前客户端版本号 |

**响应示例（有更新）：**

```json
{
    "code": 200,
    "message": "success",
    "data": {
        "need_update": true,
        "force_update": false,
        "version": "1.1.0",
        "version_code": 110,
        "download_url": "https://example.com/app.apk",
        "description": "Bug fixes and improvements"
    }
}
```

**响应示例（无更新）：**

```json
{
    "code": 200,
    "message": "success",
    "data": {
        "need_update": false,
        "force_update": false
    }
}
```

### 业务逻辑

- `checkUpdate` 方法查找指定平台中 `version_code` 大于客户端当前值的最新启用版本
- 如果无更新，仅返回 `need_update: false`
- 支持 `force_update` 标记，客户端可据此决定是否强制用户更新

---

## 数据导入 (Data Import)

数据导入模块提供通用的文件上传与批量导入功能。

### 管理端 API

| 方法 | 路径 | 说明 | 权限 |
|------|------|------|------|
| POST | /adminapi/dataimport/upload | 上传导入文件 | dataimport.upload |
| GET | /adminapi/dataimport/history | 导入历史记录 | dataimport.history |

### 请求参数

**上传导入文件：**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| file | file | 是 | 导入文件（支持 xlsx、csv） |
| type | string | 是 | 导入类型标识 |

### 响应示例

```json
{
    "code": 200,
    "message": "导入成功",
    "data": {
        "total": 100,
        "success": 98,
        "failed": 2,
        "errors": [
            { "row": 5, "message": "手机号格式不正确" },
            { "row": 23, "message": "必填字段为空" }
        ]
    }
}
```

### 导入历史

```json
{
    "code": 200,
    "message": "success",
    "data": {
        "list": [
            {
                "id": 1,
                "type": "user",
                "filename": "users_20250314.xlsx",
                "total": 100,
                "success": 98,
                "failed": 2,
                "status": 1,
                "created_at": "2025-03-14 10:00:00"
            }
        ],
        "pagination": {
            "current_page": 1,
            "per_page": 20,
            "total": 1,
            "last_page": 1
        }
    }
}
```
