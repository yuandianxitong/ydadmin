<template>
    <div class="comp-rail">
        <div class="comp-rail__body">
            <div v-for="g in groups" :key="g.group" class="comp-group">
                <div class="comp-group__label">{{ g.group }}</div>
                <div class="comp-group__grid">
                    <div
                        v-for="it in g.items"
                        :key="it.type"
                        class="rail-card"
                        @click="$emit('add', it.type)"
                    >
                        <CompGlyph
                            class="rail-card__glyph"
                            :type="it.type"
                            :icon-url="it.icon_url"
                        />
                        <span class="rail-card__label">{{ it.label }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue'

import CompGlyph from './CompGlyph.vue'
import { buildComponentGroups } from './componentGroups'
import { usePluginWidgets } from './usePluginWidgets'

const props = withDefaults(defineProps<{ pageKey?: string }>(), { pageKey: 'home' })
defineEmits<{ (e: 'add', type: string): void }>()

const { pluginWidgets, load } = usePluginWidgets()
onMounted(load)

const groups = computed(() => buildComponentGroups(props.pageKey, pluginWidgets.value))
</script>

<style scoped lang="scss">
.comp-rail {
    display: flex;
    flex-direction: column;
    height: 100%;
    overflow: hidden;
}

.comp-rail__body {
    flex: 1;
    overflow-y: auto;
    padding: 12px 8px 18px;
}

.comp-group {
    margin-top: 10px;

    &__label {
        padding: 4px 6px;
        font-size: var(--font-size-small);
        font-weight: var(--font-weight-medium);
        color: var(--color-text-tertiary);
    }

    &__grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 6px;
        padding: 4px 2px;
    }
}

.rail-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    padding: 10px 8px;
    background: var(--color-surface-sunken);
    border-radius: 4px;
    cursor: pointer;
    transition:
        border-color 0.15s ease,
        background 0.15s ease,
        color 0.15s ease;

    &__glyph {
        color: var(--color-text-tertiary);
        transition: color 0.15s ease;
    }

    &__label {
        font-size: var(--font-size-caption);
        font-weight: var(--font-weight-medium);
        color: var(--color-text-secondary);
        transition: color 0.15s ease;
    }

    &:hover {
        background: var(--el-color-primary-light-9);
        border-color: var(--el-color-primary-light-7);

        .rail-card__glyph,
        .rail-card__label {
            color: var(--el-color-primary);
        }
    }
}
</style>
