<template>
    <div class="sidebar-brand-mark" @click="handleClick">
        <img :src="appStore.getImageUrl(config.site_logo)" class="sidebar-brand-image" />
    </div>
</template>

<script setup lang="ts">
import useAppStore from '@/store/modules/app.store'

defineProps({
    szie: { type: Number, default: 34 },
    title: { type: String },
    theme: { type: String },
    showTitle: { type: Boolean, default: true }
})
const appStore = useAppStore()
const config = computed(() => appStore.config)
const router = useRouter()

const handleClick = () => {
    router.push('/')
}
</script>
<style lang="scss" scoped>
.sidebar-brand-mark {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    cursor: pointer;
}

.sidebar-brand-image {
    display: block;
    width: 42px;
    height: 42px;
    object-fit: contain;
    border-radius: 2px;
}

.logo {
    height: var(--navbar-height);
    overflow: hidden;
    cursor: pointer;
    user-select: none;
    @apply flex items-center p-2 relative;

    .logo-title {
        width: 70%;
        position: absolute;
        @apply text-xl;
    }

    .title-width-enter-active {
        opacity: 0;
        transition: all 0.3s ease-out;
    }

    .title-width-leave-active {
        transition: all 0.3s cubic-bezier(1, 0.5, 0.8, 1);
    }

    .title-width-enter-from,
    .title-width-leave-to {
        width: 0;
        opacity: 0;
    }
}
</style>
