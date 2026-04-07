<template>
    <el-dialog
        v-model="visible"
        :title="form.id ? $t('menu.editMenu') : $t('menu.addMenu')"
        width="600px"
        :close-on-click-modal="false"
        @closed="handleDialogClosed"
    >
        <el-form ref="formRef" :model="form" :rules="rules" label-width="100px">
            <!-- 上级菜单 -->
            <el-form-item :label="$t('menu.parentMenu')" prop="parent_id">
                <el-tree-select
                    v-model="form.parent_id"
                    :data="parentTreeData"
                    node-key="id"
                    :props="{ label: 'title', children: 'children' }"
                    :placeholder="$t('menu.parentMenu')"
                    check-strictly
                    clearable
                    default-expand-all
                    style="width: 100%"
                />
            </el-form-item>

            <!-- 菜单类型 -->
            <el-form-item :label="$t('menu.menuType')" prop="type">
                <el-radio-group v-model="form.type" @change="handleTypeChange">
                    <el-radio :value="1">{{ $t('menu.typeOptions.directory') }}</el-radio>
                    <el-radio :value="2">{{ $t('menu.typeOptions.menu') }}</el-radio>
                    <el-radio :value="3">{{ $t('menu.typeOptions.button') }}</el-radio>
                </el-radio-group>
            </el-form-item>

            <!-- 菜单名称 -->
            <el-form-item :label="$t('menu.menuName')" prop="title">
                <el-input v-model="form.title" :placeholder="$t('menu.namePlaceholder')" />
            </el-form-item>

            <!-- 路由名称（按钮类型不显示） -->
            <el-form-item v-if="form.type !== 3" :label="$t('menu.routeName')" prop="name">
                <el-input v-model="form.name" :placeholder="$t('menu.routeNamePlaceholder')" />
            </el-form-item>

            <!-- 路由路径（按钮类型不显示） -->
            <el-form-item v-if="form.type !== 3" :label="$t('menu.routePath')" prop="path">
                <el-input v-model="form.path" :placeholder="$t('menu.routePathPlaceholder')" />
            </el-form-item>

            <!-- 组件路径（仅菜单类型显示） -->
            <el-form-item
                v-if="form.type === 2"
                :label="$t('menu.componentPath')"
                prop="component"
            >
                <el-input
                    v-model="form.component"
                    :placeholder="$t('menu.componentPlaceholder')"
                >
                    <template #prepend>src/views/</template>
                    <template #append>.vue</template>
                </el-input>
            </el-form-item>

            <!-- 图标（按钮类型不显示） -->
            <el-form-item v-if="form.type !== 3" :label="$t('menu.icon')" prop="icon">
                <IconSelect v-model="form.icon" width="100%" />
            </el-form-item>

            <!-- 权限标识 -->
            <el-form-item :label="$t('menu.permCode')" prop="permission">
                <el-input
                    v-model="form.permission"
                    :placeholder="$t('menu.permPlaceholder')"
                />
            </el-form-item>

            <!-- 排序 -->
            <el-form-item :label="$t('common.sort')" prop="sort">
                <el-input-number
                    v-model="form.sort"
                    :min="0"
                    :max="9999"
                    controls-position="right"
                    style="width: 100%"
                />
            </el-form-item>

            <!-- 状态 -->
            <el-form-item :label="$t('common.status')" prop="status">
                <el-radio-group v-model="form.status">
                    <el-radio :value="1">{{ $t('common.enable') }}</el-radio>
                    <el-radio :value="0">{{ $t('common.disable') }}</el-radio>
                </el-radio-group>
            </el-form-item>

            <!-- ============ 高级选项（按钮类型不显示） ============ -->
            <template v-if="form.type !== 3">
                <el-divider content-position="left">
                    <span class="section-title">{{ $t('menu.advancedOptions') }}</span>
                </el-divider>

                <el-form-item :label="$t('menu.isHidden')">
                    <el-switch v-model="metaForm.hidden" />
                </el-form-item>

                <el-form-item :label="$t('menu.isCache')">
                    <el-switch v-model="metaForm.cache" />
                </el-form-item>

                <el-form-item :label="$t('menu.isAffix')">
                    <el-switch v-model="metaForm.affix" />
                </el-form-item>

                <el-form-item :label="$t('menu.badgeText')">
                    <el-input
                        v-model="metaForm.badge"
                        :placeholder="$t('menu.badgeText')"
                    />
                </el-form-item>

                <el-form-item :label="$t('menu.externalLink')">
                    <el-input
                        v-model="metaForm.iframe"
                        :placeholder="$t('menu.externalLinkPlaceholder')"
                    />
                </el-form-item>
            </template>
        </el-form>

        <template #footer>
            <span class="dialog-footer">
                <el-button @click="handleClose">{{ $t('common.cancel') }}</el-button>
                <el-button type="primary" :loading="submitting" @click="handleSubmit">
                    {{ $t('common.confirm') }}
                </el-button>
            </span>
        </template>
    </el-dialog>
</template>

<script setup lang="ts" name="MenuForm">
import type { FormRules } from 'element-plus'
import { computed, reactive, watch } from 'vue'
import { useI18n } from 'vue-i18n'

import IconSelect from '@/components/IconSelect/index.vue'
import { menuApi } from '@/api/menu'
import { useFormDialog } from '@/hooks/useFormDialog'
import type { MenuInfo, MenuMeta, MenuReq } from '@/types/api'

const { t } = useI18n()

type MenuFormData = MenuReq & { id?: number }

interface Props {
    modelValue: boolean
    formData: Partial<MenuInfo>
    /**
     * 父级菜单选项（树形结构，后端返回带 children 的嵌套数据）
     *
     * 注意：这里是 any[] 而非扁平类型——后端 MenuRepository::getMenuOptions
     * 使用 ArrayHelper::toTree() 返回树形结构，直接传给 el-tree-select 即可。
     */
    parentOptions: any[]
}

interface Emits {
    (e: 'update:modelValue', value: boolean): void
    (e: 'success'): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

// 元数据表单（独立于主 form，因为接口字段是嵌套的 meta 对象）
const defaultMeta: MenuMeta = {
    hidden: false,
    cache: true,
    affix: false,
    badge: '',
    iframe: ''
}

const metaForm = reactive<MenuMeta>({ ...defaultMeta })

const { form, formRef, submitting, visible, handleSubmit, handleClose, resetForm } =
    useFormDialog<MenuFormData>({
        defaultForm: {
            id: undefined,
            parent_id: 0,
            type: 1,
            title: '',
            name: '',
            path: '',
            component: '',
            icon: '',
            permission: '',
            sort: 100,
            status: 1
        },
        modelValue: () => props.modelValue,
        onUpdate: (v) => emit('update:modelValue', v),
        onSuccess: () => emit('success'),
        // 创建/更新时合并 metaForm 到 meta 字段（按钮类型不需要 meta）
        createFn: (data) => {
            const { id: _id, ...payload } = data
            return menuApi.createMenu({
                ...payload,
                meta: payload.type !== 3 ? { ...metaForm } : undefined
            } as MenuReq)
        },
        updateFn: (id, data) => {
            const { id: _id, ...payload } = data
            return menuApi.updateMenu(id, {
                ...payload,
                meta: payload.type !== 3 ? { ...metaForm } : undefined
            } as MenuReq)
        },
        sourceData: () => props.formData as Partial<MenuFormData>
    })

// 同步外部 formData.meta 到 metaForm
watch(
    () => props.formData,
    (newData) => {
        if (newData?.meta) {
            Object.assign(metaForm, defaultMeta, newData.meta)
        } else {
            Object.assign(metaForm, defaultMeta)
        }
    },
    { deep: true, immediate: true }
)

/**
 * 父级菜单树数据
 *
 * 后端 getMenuOptions 已经返回带 children 的树结构（通过 ArrayHelper::toTree），
 * 直接传给 el-tree-select 即可。不要再做 .map() 扁平化，否则会丢失子节点。
 *
 * 顶部额外插入"顶级目录"占位项，让用户可以把菜单挂在根下（parent_id = 0）。
 */
const parentTreeData = computed(() => [
    { id: 0, title: t('menu.topLevel'), children: [] },
    ...(props.parentOptions || [])
])

// 表单验证规则
const rules = computed<FormRules>(() => ({
    title: [{ required: true, message: t('menu.validate.nameRequired'), trigger: 'blur' }],
    type: [{ required: true, message: t('menu.validate.typeRequired'), trigger: 'change' }],
    name: [
        {
            required: true,
            message: t('menu.routeNamePlaceholder'),
            trigger: 'blur',
            validator: (rule: any, value: string, callback: (error?: Error) => void) => {
                if (form.type === 3) {
                    callback()
                    return
                }
                if (!value) {
                    callback(new Error(t('menu.routeNamePlaceholder')))
                    return
                }
                callback()
            }
        }
    ],
    path: [
        {
            required: true,
            message: t('menu.routePathPlaceholder'),
            trigger: 'blur',
            validator: (rule: any, value: string, callback: (error?: Error) => void) => {
                if (form.type === 3) {
                    callback()
                    return
                }
                if (!value) {
                    callback(new Error(t('menu.routePathPlaceholder')))
                    return
                }
                callback()
            }
        }
    ],
    component: [
        {
            required: true,
            message: t('menu.componentPlaceholder'),
            trigger: 'blur',
            validator: (rule: any, value: string, callback: (error?: Error) => void) => {
                if (form.type !== 2) {
                    callback()
                    return
                }
                if (!value) {
                    callback(new Error(t('menu.componentPlaceholder')))
                    return
                }
                callback()
            }
        }
    ]
}))

// 类型变更处理
const handleTypeChange = () => {
    if (form.type === 3) {
        // 按钮类型清空路由相关字段
        form.name = ''
        form.path = ''
        form.component = ''
        form.icon = ''
    }
}

// 弹窗关闭后重置表单与 metaForm
const handleDialogClosed = () => {
    resetForm()
    Object.assign(metaForm, defaultMeta)
}
</script>

<style lang="scss" scoped>
.section-title {
    font-size: 13px;
    font-weight: 500;
    color: var(--el-text-color-secondary);
}

.dialog-footer {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}
</style>
