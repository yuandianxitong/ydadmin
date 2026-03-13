<template>
    <el-dialog
        v-model="visible"
        :title="$t('role.assignPermissions')"
        width="600px"
        :close-on-click-modal="false"
        @close="handleClose"
    >
        <div class="assign-header">
            <div class="role-info">
                <span class="label">{{ $t('role.roleLabel') }}</span>
                <el-tag type="primary">{{ roleInfo?.title }}</el-tag>
                <span class="label">{{ $t('role.codeLabel') }}</span>
                <el-tag>{{ roleInfo?.name }}</el-tag>
            </div>
        </div>

        <div class="tree-actions">
            <el-checkbox
                v-model="menuCheckAll"
                :indeterminate="menuIndeterminate"
                @change="handleMenuCheckAllChange"
            >
                {{ $t('role.selectAll') }}
            </el-checkbox>
            <el-input
                v-model="filterText"
                :placeholder="$t('role.searchMenuPerm')"
                clearable
                style="width: 200px"
                size="small"
            />
        </div>

        <el-tree
            ref="menuTreeRef"
            :data="menuTree"
            :props="menuTreeProps"
            show-checkbox
            node-key="id"
            :default-checked-keys="selectedMenus"
            :default-expanded-keys="menuExpandKeys"
            :filter-node-method="filterNode"
            class="menu-tree"
            @check="handleMenuCheck"
        >
            <template #default="{ data }">
                <div class="tree-node">
                    <el-icon v-if="data.icon" class="node-icon">
                        <component :is="data.icon" />
                    </el-icon>
                    <span class="node-label">{{ data.title }}</span>
                    <el-tag
                        v-if="data.type"
                        :type="getMenuTypeTag(data.type)"
                        size="small"
                        class="node-tag"
                    >
                        {{ getMenuTypeText(data.type) }}
                    </el-tag>
                    <span v-if="data.permission" class="node-permission">{{
                        data.permission
                    }}</span>
                </div>
            </template>
        </el-tree>

        <template #footer>
            <span class="dialog-footer">
                <el-button @click="handleClose">{{ $t('common.cancel') }}</el-button>
                <el-button type="primary" :loading="submitLoading" @click="handleSubmit">
                    {{ $t('common.confirm') }}
                </el-button>
            </span>
        </template>
    </el-dialog>
</template>

<script setup lang="ts" name="AssignPermissionsDialog">
import { ElMessage, ElTree } from 'element-plus'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

import { roleApi } from '@/api/role'
import type { MenuInfo, RoleInfo } from '@/types/api'

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
const handleMenuCheckAllChange = (checked: boolean) => {
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

// 获取菜单类型标签
const getMenuTypeTag = (type: number) => {
    const typeMap: Record<number, string> = { 1: 'info', 2: 'success', 3: 'warning' }
    return typeMap[type] || 'info'
}

// 提交保存
const handleSubmit = async () => {
    if (!props.roleInfo?.id) return

    try {
        submitLoading.value = true

        // 获取选中的菜单ID（包含半选的父节点）
        const checkedKeys = (menuTreeRef.value?.getCheckedKeys() || []).filter(
            (key: any) => typeof key === 'number'
        ) as number[]
        const halfCheckedKeys = (menuTreeRef.value?.getHalfCheckedKeys() || []).filter(
            (key: any) => typeof key === 'number'
        ) as number[]

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
</script>

<style lang="scss" scoped>
.assign-header {
    margin-bottom: 16px;

    .role-info {
        display: flex;
        align-items: center;
        gap: 12px;

        .label {
            color: var(--el-text-color-regular);
            font-size: 14px;
        }
    }
}

.tree-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--el-border-color-light);
}

.menu-tree {
    max-height: 450px;
    overflow-y: auto;
    border: 1px solid var(--el-border-color-light);
    border-radius: 4px;
    padding: 8px;
}

.tree-node {
    display: flex;
    align-items: center;
    gap: 6px;
    flex: 1;

    .node-icon {
        font-size: 16px;
        color: var(--el-color-primary);
    }

    .node-label {
        font-size: 14px;
        color: var(--el-text-color-primary);
    }

    .node-tag {
        margin-left: 4px;
    }

    .node-permission {
        margin-left: auto;
        font-size: 12px;
        color: var(--el-text-color-placeholder);
    }
}

.dialog-footer {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}
</style>
