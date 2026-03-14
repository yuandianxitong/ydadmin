# 请求封装

## 概述

HTTP 请求基于 Axios 封装，提供了统一的请求/响应拦截、Token 管理、错误处理和国际化支持。核心文件位于 `src/utils/request.ts`。

## myRequest 工具函数

`myRequest` 封装了 `get`、`post`、`put`、`delete`、`patch` 五个方法，返回泛型 `Promise<ApiResponse<T>>`：

```ts
import { myRequest } from '@/utils/request'

// GET 请求
const res = await myRequest.get<UserInfo>('/adminapi/user/info')

// POST 请求
const res = await myRequest.post<void>('/adminapi/admin', { username: 'test', password: '123456' })

// PUT 请求
const res = await myRequest.put<void>('/adminapi/admin/1', { nickname: '新昵称' })

// DELETE 请求
const res = await myRequest.delete<void>('/adminapi/admin/1')
```

## 请求拦截器

每个请求发出前自动执行以下操作：

### 1. 自动添加 Token

从本地缓存读取 Token，添加到请求头 `Authorization: Bearer <token>`：

```ts
const token = getToken()
if (token && config.headers) {
    config.headers.Authorization = `Bearer ${token}`
}
```

### 2. 同步语言标识

将前端当前语言同步到请求头 `think-lang`，确保后端返回对应语言的提示信息：

```ts
const locale = getLocale() // 'zh-CN' | 'en-US'
config.headers['think-lang'] = locale === 'zh-CN' ? 'zh-cn' : 'en'
```

### 3. 链路追踪

自动生成并附加 `X-Trace-Id` 请求头，方便日志追踪：

```ts
config.headers['X-Trace-Id'] = 'trace_' + Date.now() + '_' + Math.random().toString(36).substring(2, 11)
```

## 响应拦截器

### 成功响应

当业务状态码为 `200` 或 `0` 时，直接返回响应数据对象：

```ts
if (data.code === 200 || data.code === 0) {
    return data
}
```

### Token 过期处理

当响应中返回 `401` 状态码或 Token 相关错误信息时：

1. 调用 `clearAuthInfo()` 清除本地登录态
2. 避免在登录页重复跳转
3. 自动跳转到登录页

```ts
if (data.code === 401 || data.message?.includes('Token验证失败')) {
    clearAuthInfo()
    if (router.currentRoute.value.path !== PageEnum.LOGIN) {
        router.push(PageEnum.LOGIN)
    }
}
```

### HTTP 错误码处理

| 状态码 | 处理方式 |
|--------|----------|
| 401 | 清除登录态，跳转登录页 |
| 403 | 提示"没有权限" |
| 404 | 提示"资源不存在" |
| 422 | 显示后端返回的验证错误信息 |
| 500 | 显示后端错误信息或默认提示 |
| 503 | 检测系统是否未安装，提示对应信息 |

所有错误均通过 `ElMessage.error()` 提示用户。

## API 文件组织约定

API 文件位于 `src/api/` 目录，按业务模块划分：

```
src/api/
├── auth.ts          # 认证（登录、登出、刷新Token）
├── admin.ts         # 管理员管理
├── menu.ts          # 菜单管理
├── role.ts          # 角色管理
├── department.ts    # 部门管理
├── dictionary.ts    # 字典管理
├── file.ts          # 文件上传
├── log.ts           # 日志查询
├── system/          # 系统设置模块
└── ...
```

### API 文件编写规范

```ts
// src/api/admin.ts
import { myRequest } from '@/utils/request'

export const adminApi = {
    /** 获取管理员列表 */
    list(params: Record<string, any>) {
        return myRequest.get('/adminapi/admin', { params })
    },

    /** 新增管理员 */
    add(data: AdminForm) {
        return myRequest.post('/adminapi/admin', data)
    },

    /** 编辑管理员 */
    edit(id: number, data: AdminForm) {
        return myRequest.put(`/adminapi/admin/${id}`, data)
    },

    /** 删除管理员 */
    delete(id: number) {
        return myRequest.delete(`/adminapi/admin/${id}`)
    }
}
```

## TypeScript 类型定义

API 相关的类型定义在 `src/types/api.ts` 中：

```ts
/** 通用 API 响应结构 */
interface ApiResponse<T = any> {
    code: number
    message: string
    data: T
}

/** 分页结果 */
interface PageResult<T> {
    lists: T[]
    count: number
    extend?: Record<string, any>
}

/** 菜单信息 */
interface MenuInfo {
    path: string
    name?: string
    component?: string
    type?: number
    redirect?: string
    children?: MenuInfo[]
    meta?: {
        title?: string
        icon?: string
        hidden?: boolean
        cache?: boolean
        permission?: string
        activeMenu?: string
    }
}
```

## 注意事项

- 开发环境下 `baseURL` 为空字符串，API 请求通过 Vite proxy 转发
- 生产环境使用 `VITE_APP_API_URL` 作为 `baseURL`
- 请求超时时间默认 30 秒（`timeout: 30000`）
- 内容类型默认 `application/json;charset=UTF-8`
