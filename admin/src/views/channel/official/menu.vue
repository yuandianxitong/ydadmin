<template>
    <div class="wechat-menu">
        <el-card shadow="never">
            <template #header>
                <div class="card-header">
                    <span>{{ $t('channel.menu.title') }}</span>
                    <div>
                        <el-button type="danger" :loading="deleting" @click="handleDeleteMenu"
                            >{{ $t('channel.menu.deleteMenu') }}</el-button
                        >
                        <el-button type="primary" :loading="saving" @click="handleSaveMenu"
                            >{{ $t('channel.menu.publishMenu') }}</el-button
                        >
                    </div>
                </div>
            </template>

            <el-alert type="warning" :closable="false" show-icon class="mb-16">
                {{ $t('channel.menu.alertTip') }}
            </el-alert>

            <div class="menu-editor">
                <!-- 左侧：手机模拟器 -->
                <div class="phone-mockup">
                    <div class="phone-screen">
                        <div class="phone-header">{{ $t('channel.menu.title') }}</div>
                        <div class="phone-body">
                            <!-- 子菜单区域（悬浮在一级菜单上方） -->
                            <div v-if="activeIndex[0] >= 0 && menuData[activeIndex[0]]?.sub_button?.length" class="sub-menu-popup">
                                <div
                                    v-for="(sub, sIdx) in menuData[activeIndex[0]].sub_button"
                                    :key="sIdx"
                                    class="sub-menu-btn"
                                    :class="{ active: activeIndex[1] === sIdx }"
                                    @click="selectMenu(activeIndex[0], sIdx)"
                                >
                                    <span>{{ sub.name || $t('channel.menu.subMenuPrefix') + (sIdx + 1) }}</span>
                                    <el-icon class="del-icon" @click.stop="removeSubMenu(activeIndex[0], sIdx)"><Close /></el-icon>
                                </div>
                                <div
                                    v-if="(menuData[activeIndex[0]].sub_button || []).length < 5"
                                    class="sub-menu-btn add"
                                    @click="addSubMenu(activeIndex[0])"
                                >
                                    <el-icon :size="14"><Plus /></el-icon>
                                </div>
                            </div>
                        </div>
                        <!-- 底部菜单栏 -->
                        <div class="phone-menu-bar">
                            <div class="menu-btn keyboard-btn">
                                <el-icon :size="18"><Keyboard /></el-icon>
                            </div>
                            <div
                                v-for="(menu, idx) in menuData"
                                :key="idx"
                                class="menu-btn"
                                :class="{ active: activeIndex[0] === idx }"
                                @click="selectMenu(idx, -1)"
                            >
                                <span class="menu-btn-text">{{ menu.name || $t('channel.menu.menuPrefix') + (idx + 1) }}</span>
                                <el-icon class="del-icon" @click.stop="removeMenu(idx)"><Close /></el-icon>
                            </div>
                            <div v-if="menuData.length < 3" class="menu-btn add" @click="addMenu">
                                <el-icon :size="14"><Plus /></el-icon>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 右侧：编辑面板 -->
                <div v-if="currentMenu" class="menu-form">
                    <el-form label-width="100px" label-position="left">
                        <el-form-item :label="$t('channel.menu.menuName')">
                            <el-input v-model="currentMenu.name" maxlength="16" show-word-limit />
                        </el-form-item>
                        <el-form-item :label="$t('channel.menu.menuType')">
                            <el-select v-model="currentMenu.type" style="width: 100%">
                                <el-option :label="$t('channel.menu.typeView')" value="view" />
                                <el-option :label="$t('channel.menu.typeClick')" value="click" />
                                <el-option :label="$t('channel.menu.typeMiniprogram')" value="miniprogram" />
                            </el-select>
                        </el-form-item>
                        <el-form-item v-if="currentMenu.type === 'view'" :label="$t('channel.menu.webUrl')">
                            <el-input v-model="currentMenu.url" placeholder="https://" />
                        </el-form-item>
                        <el-form-item v-if="currentMenu.type === 'click'" :label="$t('channel.menu.eventKey')">
                            <el-input v-model="currentMenu.key" :placeholder="$t('channel.menu.eventKeyPlaceholder')" />
                        </el-form-item>
                        <template v-if="currentMenu.type === 'miniprogram'">
                            <el-form-item :label="$t('channel.menu.miniprogramAppId')">
                                <el-input v-model="currentMenu.appid" />
                            </el-form-item>
                            <el-form-item :label="$t('channel.menu.miniprogramPath')">
                                <el-input v-model="currentMenu.pagepath" />
                            </el-form-item>
                            <el-form-item :label="$t('channel.menu.fallbackUrl')">
                                <el-input
                                    v-model="currentMenu.url"
                                    :placeholder="$t('channel.menu.fallbackUrlPlaceholder')"
                                />
                            </el-form-item>
                        </template>
                    </el-form>
                </div>
            </div>
        </el-card>
    </div>
</template>

<script setup lang="ts" name="WechatOfficialMenu">
import { Close, Keyboard, Plus } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { officialAccountApi } from '@/api/wechat'

const { t } = useI18n()
const menuData = reactive<any[]>([])
const activeIndex = ref<[number, number]>([0, -1])
const saving = ref(false)
const deleting = ref(false)

const currentMenu = computed(() => {
    const [pIdx, sIdx] = activeIndex.value
    if (pIdx < 0 || !menuData[pIdx]) return null
    if (sIdx >= 0) return menuData[pIdx].sub_button?.[sIdx] || null
    return menuData[pIdx]
})

const selectMenu = (pIdx: number, sIdx: number) => {
    activeIndex.value = [pIdx, sIdx]
}

const addMenu = () => {
    menuData.push({ name: '', type: 'view', url: '', sub_button: [] })
    activeIndex.value = [menuData.length - 1, -1]
}

const removeMenu = (idx: number) => {
    menuData.splice(idx, 1)
    if (activeIndex.value[0] >= menuData.length) {
        activeIndex.value = [Math.max(0, menuData.length - 1), -1]
    }
}

const addSubMenu = (pIdx: number) => {
    if (!menuData[pIdx].sub_button) menuData[pIdx].sub_button = []
    menuData[pIdx].sub_button.push({ name: '', type: 'view', url: '' })
    activeIndex.value = [pIdx, menuData[pIdx].sub_button.length - 1]
}

const removeSubMenu = (pIdx: number, sIdx: number) => {
    menuData[pIdx].sub_button.splice(sIdx, 1)
    activeIndex.value = [pIdx, -1]
}

const handleSaveMenu = async () => {
    if (menuData.length === 0) {
        ElMessage.warning(t('channel.menu.atLeastOneMenu'))
        return
    }
    try {
        saving.value = true
        await officialAccountApi.createMenu({ button: menuData })
        ElMessage.success(t('channel.menu.publishSuccess'))
    } catch (e: any) {
        ElMessage.error(e?.message || t('channel.menu.publishFailed'))
    } finally {
        saving.value = false
    }
}

const handleDeleteMenu = async () => {
    try {
        await ElMessageBox.confirm(t('channel.menu.deleteConfirm'), t('common.tip'), { type: 'warning' })
        deleting.value = true
        await officialAccountApi.deleteMenu()
        menuData.splice(0, menuData.length)
        ElMessage.success(t('channel.menu.deleteSuccess'))
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(t('channel.menu.deleteFailed'))
    } finally {
        deleting.value = false
    }
}

onMounted(async () => {
    try {
        const res = await officialAccountApi.getMenu()
        const selfMenuInfo = res.data?.selfmenu_info?.button || []
        // 转换微信返回格式为编辑格式
        selfMenuInfo.forEach((item: any) => {
            const menu: any = {
                name: item.name,
                type: item.type || 'view',
                url: item.url || '',
                key: item.key || '',
                appid: item.appid || '',
                pagepath: item.pagepath || '',
                sub_button: []
            }
            if (item.sub_button?.list) {
                menu.sub_button = item.sub_button.list.map((sub: any) => ({
                    name: sub.name,
                    type: sub.type || 'view',
                    url: sub.url || '',
                    key: sub.key || '',
                    appid: sub.appid || '',
                    pagepath: sub.pagepath || ''
                }))
            }
            menuData.push(menu)
        })
    } catch {
        // 可能没有菜单
    }
})
</script>

<style lang="scss" scoped>
.wechat-menu {
    .mb-16 {
        margin-bottom: 16px;
    }
    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        span {
            font-size: 16px;
            font-weight: 600;
        }
    }
    .menu-editor {
        display: flex;
        gap: 24px;
        min-height: 400px;
    }
    .phone-mockup {
        width: 340px;
        flex-shrink: 0;
    }
    .phone-screen {
        border: 1px solid var(--el-border-color);
        border-radius: 8px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        height: 540px;
        background: #f5f5f5;
    }
    .phone-header {
        height: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 15px;
        font-weight: 600;
        background: #ededed;
        border-bottom: 1px solid var(--el-border-color-lighter);
        color: var(--el-text-color-primary);
    }
    .phone-body {
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        padding: 8px;
    }
    .sub-menu-popup {
        display: flex;
        flex-direction: column;
        gap: 4px;
        padding: 8px;
        margin-bottom: 4px;
        background: #fff;
        border-radius: 6px;
        border: 1px solid var(--el-border-color-lighter);
    }
    .sub-menu-btn {
        padding: 8px 12px;
        font-size: 13px;
        text-align: center;
        cursor: pointer;
        border-radius: 4px;
        position: relative;
        transition: background 0.15s;
        &:hover {
            background: var(--el-fill-color-light);
        }
        &.active {
            color: var(--el-color-primary);
            background: var(--el-color-primary-light-9);
        }
        &.add {
            border: 1px dashed var(--el-border-color);
            color: var(--el-text-color-placeholder);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .del-icon {
            position: absolute;
            top: -4px;
            right: -4px;
            font-size: 10px;
            background: var(--el-color-danger);
            color: #fff;
            border-radius: 50%;
            padding: 2px;
            display: none;
        }
        &:hover .del-icon {
            display: block;
        }
    }
    .phone-menu-bar {
        display: flex;
        border-top: 1px solid var(--el-border-color);
        background: #fafafa;
    }
    .menu-btn {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 12px 4px;
        font-size: 14px;
        cursor: pointer;
        position: relative;
        border-right: 1px solid var(--el-border-color-lighter);
        transition: background 0.15s;
        &:last-child {
            border-right: none;
        }
        &:hover {
            background: var(--el-fill-color-light);
        }
        &.active {
            color: var(--el-color-primary);
            background: var(--el-color-primary-light-9);
            font-weight: 500;
        }
        &.add {
            color: var(--el-text-color-placeholder);
            max-width: 50px;
        }
        &.keyboard-btn {
            max-width: 50px;
            cursor: default;
            color: var(--el-text-color-secondary);
            &:hover {
                background: transparent;
            }
        }
        .del-icon {
            position: absolute;
            top: -4px;
            right: -4px;
            font-size: 10px;
            background: var(--el-color-danger);
            color: #fff;
            border-radius: 50%;
            padding: 2px;
            display: none;
        }
        &:hover .del-icon {
            display: block;
        }
    }
    .menu-form {
        flex: 1;
        border: 1px solid var(--el-border-color);
        border-radius: 6px;
        padding: 20px;
    }
}
</style>
