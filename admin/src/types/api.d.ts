// API响应通用类型
export interface ApiResponse<T = any> {
    code: number
    message: string
    data: T
    timestamp: number
}

// 分页请求参数
export interface PageQuery {
    page?: number
    limit?: number
    keyword?: string
}

// 分页响应数据
export interface PageResult<T> {
    list: T[]
    pagination: {
        current_page: number
        per_page: number
        total: number
        last_page: number
    }
}

// 登录相关类型
export interface LoginReq {
    username: string
    password: string
    captcha: string
    captcha_key: string
}

export interface CaptchaRes {
    key: string
    image: string
}

export interface LoginRes {
    token: string
    admin: AdminInfo
}

export interface AdminInfo {
    id: number
    username: string
    email?: string
    mobile?: string
    nickname?: string
    avatar?: string
    department_id?: number | null
    department?: string
    position?: string
    status: number
    last_login_ip?: string
    last_login_time?: string
    login_count?: number
    roles?: RoleInfo[]
    permissions?: string[]
}

// 角色类型
export interface RoleInfo {
    id: number
    name: string
    title: string
    description?: string
    data_scope?: number
    is_system: boolean
    status: number
    created_at?: string
    updated_at?: string
}

// 权限类型
export interface PermissionInfo {
    id: number
    name: string
    title: string
    group: string
    description?: string
    guard_name: string
    created_at?: string
    updated_at?: string
}

// 菜单类型
export interface MenuInfo {
    id: number
    parent_id: number
    type: number // 1目录/2菜单/3按钮
    title: string
    name?: string
    path?: string
    component?: string
    icon?: string
    permission?: string
    sort?: number
    status: number
    redirect?: string
    meta?: MenuMeta
    children?: MenuInfo[]
    created_at?: string
    updated_at?: string
}

export interface MenuMeta {
    title?: string
    icon?: string
    permission?: string
    activeMenu?: string
    hidden?: boolean
    cache?: boolean
    affix?: boolean
    badge?: string
    dot?: boolean
    iframe?: string
}

// 管理员管理
export interface AdminReq {
    username: string
    email?: string
    mobile?: string
    password?: string
    nickname?: string
    avatar?: string
    department_id?: number | null
    position?: string
    status: number
    role_ids?: number[]
}

export interface AdminQuery extends PageQuery {
    status?: number
    department?: string
}

// 角色管理
export interface RoleReq {
    name: string
    title: string
    description?: string
    data_scope?: number
    status: number
}

export interface RoleQuery extends PageQuery {
    status?: number
}

// 权限分配（统一使用菜单树）
export interface AssignPermissionsReq {
    menu_ids: number[]
}

// 菜单管理
export interface MenuReq {
    parent_id: number
    type: number
    title: string
    name?: string
    path?: string
    component?: string
    icon?: string
    permission?: string
    sort?: number
    status: number
    meta?: MenuMeta
}

export interface MenuQuery {
    status?: number
    type?: number
    title?: string
}

// 系统配置
export interface ConfigInfo {
    id: number
    name: string
    title: string
    value: any
    type: string
    group: string
    description?: string
    sort?: number
    created_at?: string
    updated_at?: string
}

export interface ConfigReq {
    name: string
    title: string
    value: any
    type: string
    group: string
    description?: string
    sort?: number
}

// 操作日志
export interface LogInfo {
    id: number
    admin_id: number
    admin_name: string
    action: string
    module: string
    description?: string
    ip: string
    user_agent?: string
    request_data?: any
    response_data?: any
    created_at: string
}

export interface LogQuery extends PageQuery {
    admin_id?: number
    action?: string
    module?: string
    start_date?: string
    end_date?: string
}

// 修改密码
export interface ChangePasswordReq {
    old_password: string
    new_password: string
}

// 重置密码
export interface ResetPasswordReq {
    password: string
}

// 状态更新
export interface StatusReq {
    status: number
}

// 批量删除
export interface BatchDeleteReq {
    ids: number[]
}

// 通用选项类型
export interface SelectOption {
    label: string
    value: string | number
}

export interface TreeOption {
    id: number
    title: string
    children?: TreeOption[]
}

// 角色选项（用于下拉选择）
export interface RoleOption {
    id: number
    name: string
    title: string
}

// ========== 登录日志 ==========
export interface LoginLogInfo {
    id: number
    admin_id?: number
    username: string
    ip: string
    user_agent?: string
    login_time: string
    login_result: number
    login_message?: string
}

export interface OperationLogInfo {
    id: number
    admin_id: number
    admin_name: string
    module: string
    action: string
    method: string
    url: string
    ip: string
    request_data?: Record<string, unknown>
    response_code?: number
    duration?: number
    created_at: string
}

// ========== 数据字典 ==========
export interface DictionaryInfo {
    id: number
    name: string
    code: string
    description?: string
    status: number
    sort?: number
    created_at?: string
    updated_at?: string
}

export interface DictionaryItemInfo {
    id: number
    dictionary_id: number
    label: string
    value: string
    tag_type?: string
    description?: string
    status: number
    sort?: number
    created_at?: string
    updated_at?: string
}

export interface DictionaryQuery extends PageQuery {
    status?: number
}

export interface DictionaryReq {
    name: string
    code: string
    description?: string
    status?: number
    sort?: number
}

export interface DictionaryItemReq {
    dictionary_id: number
    label: string
    value: string
    tag_type?: string
    description?: string
    status?: number
    sort?: number
}

// ========== 部门管理 ==========
export interface DepartmentInfo {
    id: number
    parent_id: number
    name: string
    sort?: number
    status: number
    leader?: string
    phone?: string
    email?: string
    children?: DepartmentInfo[]
    created_at?: string
    updated_at?: string
}

export interface DepartmentReq {
    parent_id: number
    name: string
    sort?: number
    status?: number
    leader?: string
    phone?: string
    email?: string
}

// ========== 通知管理 ==========
export interface NotificationInfo {
    id: number
    title: string
    content: string
    type: number
    status: number
    send_to?: string
    read_count?: number
    created_at?: string
    updated_at?: string
}

export interface NotificationReq {
    title: string
    content: string
    type: number
    send_to?: string | number[]
}

export interface NotificationQuery extends PageQuery {
    type?: number
}

// ========== 定时任务 ==========
export interface CronJobInfo {
    id: number
    name: string
    command: string
    cron_expression: string
    description?: string
    status: number
    last_run_at?: string
    next_run_at?: string
    created_at?: string
    updated_at?: string
}

export interface CronJobReq {
    name: string
    command: string
    cron_expression: string
    description?: string
    status?: number
}

export interface CronJobLogInfo {
    id: number
    cron_job_id: number
    status: number
    output?: string
    duration?: number
    started_at: string
    finished_at?: string
}

// ========== 文件管理 ==========
export interface FileInfo {
    id: number
    name: string
    path: string
    url: string
    mime_type: string
    extension: string
    size: number
    group: string
    storage: string
    upload_by?: number
    created_at?: string
}

export interface FileQuery extends PageQuery {
    group?: string
    mime_type?: string
}

// ========== 仪表板 ==========
export interface DashboardTrend {
    value: number
    type: 'up' | 'down'
    unit?: 'percent'
}

export interface DashboardStats {
    adminCount: number
    roleCount: number
    menuCount: number
    configCount: number
    todayLoginCount: number
    todayNewUsers: number
    activeUsers: number
    totalUsers: number
    trends: {
        totalUsers: DashboardTrend
        activeUsers: DashboardTrend
        todayNewUsers: DashboardTrend
        todayLoginCount: DashboardTrend
    }
    newAdmins: number
    newRoles: number
    newMenus: number
    operationLogCount: number
    loginTrend: Array<{ date: string; count: number }>
    registerTrend: Array<{ date: string; count: number }>
}

export interface ActivityItem {
    type: 'login_success' | 'login_failed' | 'operation'
    username: string
    description: string
    time: string
    relative_time: string
}

export interface RankingItem {
    rank: number
    username: string
    count: number
}

export interface ActiveRanking {
    period: string
    list: RankingItem[]
}

// ========== 代码生成器 ==========
export interface GeneratorTableInfo {
    name: string
    comment: string
    engine: string
    rows: number
}

export interface GeneratorColumnInfo {
    name: string
    type: string
    raw_type: string
    nullable: boolean
    default: string | null
    comment: string
    key: string
    extra: string
    form_type: string
    searchable: boolean
    in_list: boolean
    in_form: boolean
}

export interface GeneratorConfig {
    table_name: string
    module_name: string
    model_name: string
    table_comment?: string
    columns?: GeneratorColumnInfo[]
}

// ========== 消息管理 ==========
export interface MessageTemplateInfo {
    id: number
    name: string
    code: string
    remark?: string
    status: number
    sms_enabled?: number
    sms_template_id?: string
    sms_content?: string
    wechat_official_enabled?: number
    wechat_official_template_id?: string
    wechat_official_url?: string
    wechat_mini_enabled?: number
    wechat_mini_template_id?: string
    wechat_mini_page?: string
    // Legacy fields
    channel?: string
    content?: string
    variables?: string
    description?: string
    created_at?: string
    updated_at?: string
}

export interface MessageTemplateReq {
    name: string
    code: string
    remark?: string
    status?: number
    sms_enabled?: number
    sms_template_id?: string
    sms_content?: string
    wechat_official_enabled?: number
    wechat_official_template_id?: string
    wechat_official_url?: string
    wechat_mini_enabled?: number
    wechat_mini_template_id?: string
    wechat_mini_page?: string
    // Legacy fields
    channel?: string
    content?: string
    variables?: string
    description?: string
}

export interface MessageLogInfo {
    id: number
    template_id?: number
    channel: string
    to: string
    content: string
    status: number
    error_message?: string
    sent_at?: string
    created_at?: string
}

// ========== 微信管理 ==========
export interface AutoReplyInfo {
    id: number
    type: string
    keyword: string
    match_type: string
    content: string
    sort_order?: number
    status: number
    created_at?: string
    updated_at?: string
}

export interface AutoReplyReq {
    type: string
    keyword?: string
    match_type?: string
    content: string
    sort_order?: number
    status?: number
}

export interface WechatFollowerInfo {
    openid: string
    nickname?: string
    sex?: number
    city?: string
    province?: string
    country?: string
    headimgurl?: string
    subscribe_time?: number
}

// ========== 系统配置（补充） ==========
export interface ConfigGroup {
    name: string
    title: string
    icon?: string
}

export interface ConfigBatchUpdateItem {
    config_key: string
    config_value: string | number | boolean
}

// ========== 用户认证信息（含路由和权限） ==========
export interface AuthInfoRes {
    admin: AdminInfo
    routes: MenuInfo[]
    permissions: string[]
}

// ========== 公告管理 ==========
export interface AnnouncementInfo {
    id: number
    title: string
    content: string
    type: number
    status: number
    sort: number
    publish_at?: string
    admin_id?: number
    created_at?: string
    updated_at?: string
}

export interface AnnouncementReq {
    title: string
    content: string
    type?: number
    status?: number
    sort?: number
}

export interface AnnouncementQuery extends PageQuery {
    status?: number
    type?: number
}

// ========== 协议管理 ==========
export interface AgreementInfo {
    id: number
    title: string
    code: string
    content: string
    status: number
    created_at?: string
    updated_at?: string
}

export interface AgreementReq {
    title: string
    code: string
    content: string
    status?: number
}

export interface AgreementQuery extends PageQuery {
    status?: number
}

// ========== 地区数据 ==========
export interface RegionInfo {
    id: number
    parent_id: number
    name: string
    code: string
    level: number
    sort: number
    status: number
    children?: RegionInfo[]
}

export interface RegionReq {
    parent_id?: number
    name: string
    code: string
    level?: number
    sort?: number
    status?: number
}

// ========== 版本管理 ==========
export interface AppVersionInfo {
    id: number
    platform: string
    version: string
    version_code: number
    download_url: string
    description?: string
    force_update: number
    status: number
    created_at?: string
    updated_at?: string
}

export interface AppVersionReq {
    platform: string
    version: string
    version_code: number
    download_url: string
    description?: string
    force_update?: number
    status?: number
}

export interface AppVersionQuery extends PageQuery {
    platform?: string
    status?: number
}
