<template>
    <el-space>
        <el-tag type="info">{{ $t('component.themePicker.label') }}</el-tag>
        <div class="flex gap-2">
            <button
                v-for="opt in options"
                :key="opt.name"
                class="w-8 h-8 rounded-full border border-[var(--color-border)] hover:scale-110 transition-all"
                :style="{ backgroundColor: opt.color }"
                :title="opt.name"
                @click="setTheme(opt.name)"
            />
        </div>
    </el-space>
</template>

<script setup lang="ts">
import { applyTheme, type ThemeName } from '@/theme/apply'

const options: { name: ThemeName; color: string }[] = [
    { name: 'blue', color: '#0b51b7' },
    { name: 'cyan', color: '#0aa0b7' },
    { name: 'green', color: '#2da44e' },
    { name: 'orange', color: '#ff6a00' },
    { name: 'purple', color: '#6f42c1' }
]

function setTheme(name: ThemeName) {
    applyTheme(name)
}
</script>
