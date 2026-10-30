<script setup lang="ts">
import { onMounted, onUnmounted,ref } from 'vue'

import useSettingStore from '@/store/modules/settings.store'
import { PRO_PRESET_COLORS as PRESET_COLORS } from '@/utils/theme'
const emit = defineEmits<{ advanced: [] }>()

const settingStore = useSettingStore()
const visible = ref(false)
const panelRef = ref<HTMLElement | null>(null)

function toggle() {
    visible.value = !visible.value
}

function onClickOutside(e: MouseEvent) {
    if (e.target instanceof Element && e.target.closest('[data-appearance-trigger]')) return
    if (panelRef.value && !panelRef.value.contains(e.target as Node)) {
        visible.value = false
    }
}

function openAdvanced() {
    visible.value = false
    emit('advanced')
}

function selectColor(color: string) {
    settingStore.setSetting({ key: 'theme', value: color })
    settingStore.applyThemeMode()
}

function toggleCompact() {
    settingStore.setSetting({ key: 'compact', value: !settingStore.compact })
}

function toggleLabels() {
    settingStore.setSetting({ key: 'sidebarLabels', value: !settingStore.sidebarLabels })
}

onMounted(() => {
    document.addEventListener('mousedown', onClickOutside)
})

onUnmounted(() => {
    document.removeEventListener('mousedown', onClickOutside)
})

defineExpose({ toggle })
</script>

<template>
    <Teleport to="body">
        <div v-if="visible" ref="panelRef" class="tweaks">
            <div class="tweaks-header">
                <span class="tweaks-title">{{ $t('proPage.appearanceTitle') }}</span>
                <button
                    class="tweaks-close"
                    :aria-label="$t('common.close')"
                    @click="visible = false"
                >
                    &times;
                </button>
            </div>

            <div class="tweaks-section">
                <div class="tweaks-label">{{ $t('proPage.appearanceColor') }}</div>
                <div class="tweaks-colors">
                    <div
                        v-for="color in PRESET_COLORS"
                        :key="color"
                        class="color-swatch"
                        :class="{ active: settingStore.theme === color }"
                        :style="{ backgroundColor: color }"
                        @click="selectColor(color)"
                    >
                        <span v-if="settingStore.theme === color" class="check">✓</span>
                    </div>
                </div>
            </div>

            <div class="tweaks-row">
                <div>
                    <div class="tweaks-label">{{ $t('proPage.appearanceCompact') }}</div>
                    <div class="tweaks-hint">{{ $t('proPage.appearanceCompactHint') }}</div>
                </div>
                <el-switch :model-value="settingStore.compact" @change="toggleCompact" />
            </div>

            <div class="tweaks-row">
                <div>
                    <div class="tweaks-label">{{ $t('proPage.appearanceLabels') }}</div>
                    <div class="tweaks-hint">{{ $t('proPage.appearanceLabelsHint') }}</div>
                </div>
                <el-switch :model-value="settingStore.sidebarLabels" @change="toggleLabels" />
            </div>
            <el-button class="tweaks-advanced" @click="openAdvanced">{{
                $t('proPage.appearanceAdvanced')
            }}</el-button>
        </div>
    </Teleport>
</template>

<style scoped>
.tweaks {
    position: fixed;
    right: 20px;
    bottom: 20px;
    z-index: 3200;
    background: var(--card-bg);
    border-radius: 14px;
    box-shadow: 0 20px 60px rgba(15, 23, 42, 0.18);
    border: 1px solid var(--ink-200);
    width: 300px;
    max-width: calc(100vw - 32px);
    max-height: calc(100dvh - 40px);
    overflow-y: auto;
    padding: 18px;
}

.tweaks-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 18px;
}

.tweaks-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--ink-900);
}

.tweaks-close {
    border: 0;
    background: transparent;
    padding: 0;
    color: var(--ink-400);
    cursor: pointer;
    font-size: 18px;
    line-height: 1;
}

.tweaks-close:hover {
    color: var(--ink-600);
}

.tweaks-section {
    margin-bottom: 20px;
}

.tweaks-label {
    font-size: 12.5px;
    color: var(--ink-600);
    margin-bottom: 10px;
}

.tweaks-hint {
    font-size: 11px;
    color: var(--ink-400);
    margin-top: 2px;
}

.tweaks-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}

.tweaks-row:last-child {
    margin-bottom: 0;
}

.tweaks-row .tweaks-label {
    margin-bottom: 0;
}

.tweaks-colors {
    display: flex;
    gap: 10px;
}

.color-swatch {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.15s;
}

.color-swatch:hover {
    transform: scale(1.1);
}

.color-swatch.active {
    box-shadow:
        0 0 0 2px var(--card-bg),
        0 0 0 4px var(--brand-500);
}

.check {
    color: #fff;
    font-size: 14px;
}
.tweaks-advanced {
    width: 100%;
    margin-top: 18px;
}
</style>
