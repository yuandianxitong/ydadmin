<template>
    <footer class="layout-footer">
        <div class="text-center p-2 text-xs text-tx-secondary max-w-[900px] mx-auto">
            <a
                v-for="item in copyright"
                :key="item.key"
                class="mx-1 hover:underline"
                :href="item.value"
                target="_blank"
            >
                {{ item.key }}
            </a>
        </div>
    </footer>
</template>

<script setup lang="ts">
import useAppStore from '@/store/modules/app.store'

const appStore = useAppStore()
const copyright = computed(() => appStore.config.copyright_config || [])
</script>
