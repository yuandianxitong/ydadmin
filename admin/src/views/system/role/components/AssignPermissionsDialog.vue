<template>
    <el-dialog
        :model-value="visible"
        title=""
        width="920px"
        :show-close="false"
        :close-on-click-modal="false"
        append-to-body
        class="rp-dialog"
        @update:model-value="visible = $event"
        @close="handleClose"
    >
        <template #header>
            <div class="modal-head">
                <div class="modal-title">
                    权限分配
                    <span class="sub">配置菜单权限，查看当前角色的数据范围</span>
                </div>
                <button class="modal-close" @click="handleClose">
                    <i class="i-svg:x" />
                </button>
            </div>
        </template>

        <!-- Role Header Card -->
        <div class="rp-role-hd">
            <div class="rp-role-av">{{ roleInitial }}</div>
            <div class="rp-role-meta">
                <div class="rp-role-nm">
                    {{ roleInfo?.title }}
                    <span
                        class="rp-role-status"
                        :class="roleInfo?.status === 1 ? 'is-on' : 'is-off'"
                    >
                        {{ roleInfo?.status === 1 ? '启用' : '停用' }}
                    </span>
                </div>
                <div class="rp-role-sub">
                    <span class="rp-role-code">{{ roleInfo?.name }}</span>
                    <span class="rp-role-dot">&middot;</span>
                    <span>{{ checkedCount }} 项权限</span>
                </div>
            </div>
            <div class="rp-role-warn">
                <i class="i-svg:triangle-alert" />
                权限变更将实时生效，请谨慎操作
            </div>
        </div>

        <!-- Tabs -->
        <div class="rp-tabs">
            <button
                class="rp-tab-n"
                :class="{ active: activeTab === 'perm' }"
                @click="activeTab = 'perm'"
            >
                功能权限
                <span class="rp-tab-badge">{{ checkedCount }}</span>
            </button>
            <button
                class="rp-tab-n"
                :class="{ active: activeTab === 'scope' }"
                @click="activeTab = 'scope'"
            >
                数据范围
                <span class="rp-tab-badge">{{ dataScopeLabel }}</span>
            </button>
        </div>

        <!-- 功能权限 Tab -->
        <div v-show="activeTab === 'perm'" class="rp-perm-panel">
            <!-- Toolbar -->
            <div class="rp-bar">
                <div class="rp-search">
                    <i class="i-svg:search rp-search-icon" />
                    <input
                        v-model="filterText"
                        type="text"
                        class="rp-search-input"
                        placeholder="搜索权限名称或标识"
                    />
                    <button v-show="filterText" class="rp-clr" @click="filterText = ''">
                        <i class="i-svg:x" />
                    </button>
                </div>
                <div class="rp-bar-sp" />
                <label class="rp-cascade">
                    <input v-model="cascadeChecked" type="checkbox" />
                    父子联动
                </label>
                <span class="rp-bar-sep" />
                <button class="rp-link" @click="expandAll">展开全部</button>
                <button class="rp-link" @click="collapseAll">收起全部</button>
                <span class="rp-bar-sep" />
                <button class="rp-link" @click="selectAll">全选</button>
                <button class="rp-link" @click="invertSelection">反选</button>
                <button class="rp-link" @click="clearAll">清空</button>
            </div>

            <!-- Permission Tree -->
            <div class="rp-tree">
                <el-tree
                    ref="menuTreeRef"
                    :data="menuTree"
                    :props="menuTreeProps"
                    show-checkbox
                    node-key="id"
                    :default-checked-keys="selectedMenus"
                    :default-expanded-keys="menuExpandKeys"
                    :check-strictly="!cascadeChecked"
                    :filter-node-method="filterNode"
                    class="rp-el-tree"
                    @check="handleMenuCheck"
                >
                    <template #default="{ node, data }">
                        <div
                            class="rp-node"
                            :class="[`rp-d${node.level - 1 > 2 ? 2 : node.level - 1}`]"
                        >
                            <span
                                class="rp-chev"
                                :class="{ expanded: node.expanded, leaf: node.isLeaf }"
                            />
                            <span
                                class="rp-type"
                                :class="{
                                    'is-dir': data.type === 1,
                                    'is-menu': data.type === 2,
                                    'is-btn': data.type === 3
                                }"
                            >
                                {{ getMenuTypeText(data.type) }}
                            </span>
                            <span class="rp-name">{{ data.title }}</span>
                            <span v-if="data.permission" class="rp-code">{{
                                data.permission
                            }}</span>
                        </div>
                    </template>
                </el-tree>
            </div>
        </div>

        <!-- 数据范围 Tab -->
        <div v-show="activeTab === 'scope'" class="rp-scope-panel">
            <div class="rp-hint">
                <i class="i-svg:info" />
                以下展示该角色当前的数据范围；如需调整，请使用角色列表的编辑表单。
            </div>
            <div class="rp-scope">
                <label
                    v-for="opt in dataScopeOptions"
                    :key="opt.value"
                    class="rp-scope-card"
                    :class="{ active: dataScope === opt.value }"
                >
                    <input
                        :checked="dataScope === opt.value"
                        disabled
                        type="radio"
                        :value="opt.value"
                        class="rp-scope-rd"
                    />
                    <span class="rp-scope-t">{{ opt.label }}</span>
                    <span class="rp-scope-d">{{ opt.desc }}</span>
                </label>
            </div>
        </div>

        <template #footer>
            <div class="rp-foot">
                <div class="rp-foot-info">
                    已选 <b>{{ checkedCount }}</b> 项
                    <span class="rp-foot-sep">&middot;</span>
                    目录 <b>{{ stats.dir }}</b>
                    <span class="rp-foot-sep">&middot;</span>
                    菜单 <b>{{ stats.menu }}</b>
                    <span class="rp-foot-sep">&middot;</span>
                    按钮 <b>{{ stats.btn }}</b>
                </div>
                <div class="rp-foot-actions">
                    <el-button @click="handleClose">取消</el-button>
                    <el-button type="primary" :loading="submitLoading" @click="handleSubmit">
                        保存
                    </el-button>
                </div>
            </div>
        </template>
    </el-dialog>
</template>

<script setup lang="ts" name="AssignPermissionsDialog">
import { ElMessage, ElTree } from 'element-plus'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

import { roleApi } from '@/api/role'
import type { MenuInfo, RoleInfo } from '@/types/system'

const { t } = useI18n()

interface Props {
    modelValue: boolean
    roleInfo: RoleInfo | null
}

interface Emits {
    (e: 'update:modelValue', value: boolean): void
    (e: 'success'): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const menuTreeRef = ref<InstanceType<typeof ElTree>>()

const visible = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value)
})

// 菜单相关数据
const menuTree = ref<MenuInfo[]>([])
const selectedMenus = ref<number[]>([])
const menuExpandKeys = ref<number[]>([])
const menuCheckAll = ref(false)
const menuIndeterminate = ref(false)
const filterText = ref('')
const submitLoading = ref(false)

const menuTreeProps = {
    children: 'children',
    label: 'title'
}

// 搜索过滤
watch(filterText, (val) => {
    menuTreeRef.value?.filter(val)
})

const filterNode = (value: string, data: any) => {
    if (!value) return true
    return data.title?.includes(value) || data.permission?.includes(value)
}

// 获取菜单树
const getMenuTree = async () => {
    try {
        const response = await roleApi.getMenuTree()
        const data = response.data ?? response
        const list = Array.isArray(data) ? data : ((data as any)?.data ?? [])
        menuTree.value = list
        menuExpandKeys.value = getExpandKeys(list)
    } catch (error) {
        console.error(t('message.fetchFailed'), error)
    }
}

// 获取角色已分配的权限
const getRolePermissions = async () => {
    if (!props.roleInfo?.id) return

    try {
        const response = await roleApi.getRolePermissions(props.roleInfo.id)
        selectedMenus.value = response.data.menu_ids || []
        updateMenuCheckAllStatus()
    } catch (error) {
        console.error(t('message.fetchFailed'), error)
    }
}

// 获取展开的菜单节点
const getExpandKeys = (menus: MenuInfo[]): number[] => {
    const keys: number[] = []
    const traverse = (items: MenuInfo[]) => {
        items.forEach((item) => {
            if (item.children && item.children.length > 0) {
                keys.push(item.id)
                traverse(item.children)
            }
        })
    }
    traverse(menus)
    return keys
}

// 菜单选择变化
const handleMenuCheck = () => {
    updateMenuCheckAllStatus()
}

// 更新菜单全选状态
const updateMenuCheckAllStatus = () => {
    const checkedKeys = menuTreeRef.value?.getCheckedKeys() || []
    const allKeys = getAllMenuKeys(menuTree.value)
    const checkedCount = checkedKeys.length
    const totalCount = allKeys.length

    menuCheckAll.value = checkedCount === totalCount && totalCount > 0
    menuIndeterminate.value = checkedCount > 0 && checkedCount < totalCount
}

// 获取所有菜单节点
const getAllMenuKeys = (tree: MenuInfo[]): number[] => {
    const keys: number[] = []
    const traverse = (items: MenuInfo[]) => {
        items.forEach((item) => {
            keys.push(item.id)
            if (item.children && item.children.length > 0) {
                traverse(item.children)
            }
        })
    }
    traverse(tree)
    return keys
}

// 菜单全选/取消全选
const handleMenuCheckAllChange = (checked: boolean | string | number) => {
    const allKeys = getAllMenuKeys(menuTree.value)
    if (checked) {
        menuTreeRef.value?.setCheckedKeys(allKeys)
    } else {
        menuTreeRef.value?.setCheckedKeys([])
    }
    updateMenuCheckAllStatus()
}

// 获取菜单类型文本
const getMenuTypeText = (type: number) => {
    const typeMap: Record<number, string> = {
        1: t('menu.typeOptions.directory'),
        2: t('menu.typeOptions.menu'),
        3: t('menu.typeOptions.button')
    }
    return typeMap[type] || ''
}

// 提交保存
const handleSubmit = async () => {
    if (!props.roleInfo?.id) return

    try {
        submitLoading.value = true

        // 获取选中的菜单ID（包含半选的父节点）
        const checkedKeys = (menuTreeRef.value?.getCheckedKeys() || []).map(Number).filter(Boolean)
        const halfCheckedKeys = (menuTreeRef.value?.getHalfCheckedKeys() || [])
            .map(Number)
            .filter(Boolean)

        const menuIds = [...checkedKeys, ...halfCheckedKeys]

        await roleApi.assignPermissions(props.roleInfo.id, {
            menu_ids: menuIds
        })

        ElMessage.success(t('role.assignSuccess'))
        emit('success')
        handleClose()
    } catch (error) {
        console.error('权限分配失败:', error)
    } finally {
        submitLoading.value = false
    }
}

// 关闭弹窗
const handleClose = () => {
    filterText.value = ''
    visible.value = false
}

// 监听角色信息变化
watch(
    () => props.roleInfo,
    (newRole) => {
        if (newRole && visible.value) {
            getRolePermissions()
        }
    },
    { immediate: true }
)

onMounted(() => {
    getMenuTree()
})

// Pro presentation state; API calls and the permission payload remain unchanged.
const activeTab = ref<'perm' | 'scope'>('perm')
const cascadeChecked = ref(true)
const roleInitial = computed(() => props.roleInfo?.title?.charAt(0) || 'R')
const dataScope = computed(() => props.roleInfo?.data_scope ?? 1)
const dataScopeOptions = computed(() => [
    { value: 1, label: t('role.dataScopeOptions.all'), desc: '可查看权限范围内的全部数据' },
    { value: 2, label: t('role.dataScopeOptions.department'), desc: '可查看本部门的数据' },
    {
        value: 3,
        label: t('role.dataScopeOptions.departmentAndBelow'),
        desc: '可查看本部门及下属部门的数据'
    },
    { value: 4, label: t('role.dataScopeOptions.self'), desc: '仅查看本人数据' },
    {
        value: 5,
        label: t('role.dataScopeOptions.custom'),
        desc: '按角色编辑中指定的部门范围查看数据'
    }
])
const dataScopeLabel = computed(
    () => dataScopeOptions.value.find((item) => item.value === dataScope.value)?.label || '-'
)
const checkedCount = computed(
    () =>
        (menuTreeRef.value?.getCheckedKeys().length || 0) +
        (menuTreeRef.value?.getHalfCheckedKeys().length || 0)
)
const stats = computed(() => {
    const ids = new Set([
        ...(menuTreeRef.value?.getCheckedKeys() || []),
        ...(menuTreeRef.value?.getHalfCheckedKeys() || [])
    ])
    const result = { dir: 0, menu: 0, btn: 0 }
    const visit = (items: MenuInfo[]) =>
        items.forEach((item) => {
            if (ids.has(item.id)) {
                if (item.type === 1) result.dir++
                else if (item.type === 2) result.menu++
                else if (item.type === 3) result.btn++
            }
            if (item.children?.length) visit(item.children)
        })
    visit(menuTree.value)
    return result
})
function setTreeExpanded(expanded: boolean) {
    for (const id of getAllMenuKeys(menuTree.value)) {
        const node = menuTreeRef.value?.getNode(id)
        if (node && !node.isLeaf) {
            if (expanded) node.expand()
            else node.collapse()
        }
    }
}
const expandAll = () => setTreeExpanded(true)
const collapseAll = () => setTreeExpanded(false)
const selectAll = () => handleMenuCheckAllChange(true)
const clearAll = () => handleMenuCheckAllChange(false)
function invertSelection() {
    const ids = new Set(menuTreeRef.value?.getCheckedKeys() || [])
    menuTreeRef.value?.setCheckedKeys(getAllMenuKeys(menuTree.value).filter((id) => !ids.has(id)))
    updateMenuCheckAllStatus()
}
</script>

<style lang="scss" scoped>
.rp-dialog :deep(.el-dialog__body) {
    padding: 20px 24px 4px;
}
.modal-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    width: 100%;
}
.modal-title {
    font-size: 15px;
    font-weight: 600;
    color: var(--ink-900);
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.modal-title .sub {
    font-size: 12px;
    font-weight: 400;
    color: var(--ink-400);
}
.modal-close {
    border: 0;
    background: transparent;
    color: var(--ink-400);
    width: 28px;
    height: 28px;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}
.modal-close:hover {
    background: var(--ink-100);
    color: var(--ink-700);
}
.rp-role-hd {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px;
    background: var(--ink-50);
    border: 1px solid var(--ink-100);
    border-radius: var(--r-md);
}
.rp-role-av {
    width: 44px;
    height: 44px;
    background: var(--brand-50);
    color: var(--brand-500);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    font-weight: 600;
    flex: none;
}
.rp-role-meta {
    min-width: 0;
}
.rp-role-nm {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    font-weight: 600;
    color: var(--ink-900);
}
.rp-role-status {
    font-size: 11px;
    font-weight: 400;
    border-radius: 4px;
    padding: 2px 6px;
    background: var(--ink-100);
    color: var(--ink-500);
}
.rp-role-status.is-on {
    background: var(--success-50);
    color: var(--success);
}
.rp-role-sub {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 5px;
    color: var(--ink-400);
    font-size: 12px;
}
.rp-role-code,
.rp-code {
    font-family: var(--font-num);
}
.rp-role-warn {
    margin-left: auto;
    font-size: 12px;
    color: var(--warning);
    display: flex;
    align-items: center;
    gap: 6px;
}
.rp-tabs {
    display: flex;
    gap: 24px;
    border-bottom: 1px solid var(--ink-100);
    margin: 20px 0 16px;
}
.rp-tab-n {
    border: 0;
    border-bottom: 2px solid transparent;
    background: transparent;
    padding: 0 0 12px;
    font: inherit;
    color: var(--ink-500);
    cursor: pointer;
}
.rp-tab-n.active {
    color: var(--brand-500);
    border-bottom-color: var(--brand-500);
    font-weight: 500;
}
.rp-tab-badge {
    font-size: 11px;
    padding: 2px 6px;
    border-radius: 4px;
    background: var(--ink-100);
    margin-left: 4px;
}
.rp-tab-n.active .rp-tab-badge {
    background: var(--brand-50);
}
.rp-bar {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 14px;
    font-size: 12px;
    color: var(--ink-500);
}
.rp-search {
    display: flex;
    align-items: center;
    gap: 6px;
    border: 1px solid var(--ink-200);
    border-radius: 6px;
    padding: 6px 8px;
    max-width: 100%;
}
.rp-search-input {
    width: 170px;
    min-width: 0;
    border: 0;
    background: transparent;
    font: inherit;
    color: var(--ink-700);
    outline: none;
}
.rp-clr {
    display: flex;
    background: transparent;
    border: 0;
    padding: 0;
    color: var(--ink-400);
    cursor: pointer;
}
.rp-bar-sp {
    flex: 1;
}
.rp-bar-sep {
    width: 1px;
    height: 14px;
    background: var(--ink-200);
}
.rp-cascade {
    display: flex;
    align-items: center;
    gap: 5px;
}
.rp-link {
    border: 0;
    background: transparent;
    padding: 0;
    color: var(--brand-500);
    cursor: pointer;
    font: inherit;
}
.rp-tree {
    border: 1px solid var(--ink-100);
    border-radius: var(--r-md);
    padding: 8px;
    max-height: 380px;
    min-height: 220px;
    overflow: auto;
}
.rp-node {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    min-width: 0;
    font-size: 12.5px;
}
.rp-chev {
    width: 0;
    height: 0;
    border-top: 4px solid transparent;
    border-bottom: 4px solid transparent;
    border-left: 4px solid var(--ink-400);
    transition: transform 0.15s;
    flex: none;
}
.rp-chev.expanded {
    transform: rotate(90deg);
}
.rp-chev.leaf {
    visibility: hidden;
}
.rp-type {
    padding: 2px 5px;
    border-radius: 4px;
    font-size: 10.5px;
    color: var(--ink-500);
    background: var(--ink-100);
    flex: none;
}
.rp-type.is-menu {
    color: var(--brand-500);
    background: var(--brand-50);
}
.rp-type.is-btn {
    color: var(--success);
    background: var(--success-50);
}
.rp-name {
    color: var(--ink-800);
    white-space: nowrap;
}
.rp-code {
    color: var(--ink-400);
    font-size: 11px;
    margin-left: auto;
    padding-right: 8px;
    white-space: nowrap;
}
.rp-d1 {
    padding-left: 16px;
}
.rp-d2 {
    padding-left: 32px;
}
.rp-el-tree {
    --el-tree-node-hover-bg-color: var(--ink-50);
    background: transparent;
}
.rp-el-tree :deep(.el-tree-node__content) {
    height: 34px;
    padding-left: 4px !important;
}
.rp-el-tree :deep(.el-tree-node__expand-icon) {
    display: none;
}
.rp-el-tree :deep(.el-checkbox) {
    margin-right: 4px;
}
.rp-hint {
    padding: 12px;
    border-radius: 6px;
    background: var(--brand-50);
    color: var(--brand-500);
    font-size: 12px;
    margin-bottom: 16px;
    line-height: 1.6;
}
.rp-scope {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
    min-height: 220px;
}
.rp-scope-card {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    flex-wrap: wrap;
    padding: 14px;
    border: 1px solid var(--ink-200);
    border-radius: 8px;
    color: var(--ink-700);
}
.rp-scope-card.active {
    border-color: var(--brand-500);
    background: var(--brand-50);
}
.rp-scope-rd {
    accent-color: var(--brand-500);
}
.rp-scope-t {
    font-weight: 500;
}
.rp-scope-d {
    display: block;
    width: 100%;
    color: var(--ink-400);
    font-size: 12px;
}
.rp-foot {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}
.rp-foot-info {
    color: var(--ink-500);
    font-size: 12px;
}
.rp-foot-info b {
    color: var(--ink-800);
}
.rp-foot-sep {
    margin: 0 8px;
    color: var(--ink-300);
}
.rp-foot-actions {
    display: flex;
    gap: 8px;
}
@media (max-width: 640px) {
    .rp-role-hd {
        flex-wrap: wrap;
    }
    .rp-role-warn {
        width: 100%;
        margin: 0;
    }
    .rp-scope {
        grid-template-columns: 1fr;
    }
    .rp-search {
        width: 100%;
    }
    .rp-search-input {
        flex: 1;
    }
    .rp-bar-sp {
        display: none;
    }
    .rp-foot-actions {
        margin-left: auto;
    }
}
</style>
