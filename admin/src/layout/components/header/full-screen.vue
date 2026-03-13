<template>
    <div class="full-screen h-full cursor-pointer flex items-center px-2" @click="toggleFullscreen">
        <div :class="`i-svg:${isFullscreen ? 'fullscreen-exit' : 'fullscreen'}`"></div>
    </div>
</template>

<script setup lang="ts">
import { useFullscreen } from '@vueuse/core'

const { toggle: toggleFullscreen, isFullscreen } = useFullscreen()
</script>
