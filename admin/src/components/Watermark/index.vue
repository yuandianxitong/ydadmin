<template>
    <div ref="wrapperRef" class="watermark-wrapper">
        <slot />
        <div class="watermark-layer" :style="watermarkStyle" />
    </div>
</template>

<script lang="ts" setup>
interface Props {
    text?: string
    fontSize?: number
    color?: string
    rotate?: number
    gap?: number
    zIndex?: number
}

const props = withDefaults(defineProps<Props>(), {
    text: '',
    fontSize: 16,
    color: 'rgba(0, 0, 0, 0.08)',
    rotate: -22,
    gap: 100,
    zIndex: 9
})

const watermarkUrl = ref('')

function generateWatermark() {
    if (!props.text) {
        watermarkUrl.value = ''
        return
    }
    const canvas = document.createElement('canvas')
    const ctx = canvas.getContext('2d')
    if (!ctx) return

    const ratio = window.devicePixelRatio || 1
    const size = props.gap + props.fontSize * props.text.length
    canvas.width = size * ratio
    canvas.height = size * ratio
    ctx.scale(ratio, ratio)

    ctx.translate(size / 2, size / 2)
    ctx.rotate((props.rotate * Math.PI) / 180)
    ctx.font = `${props.fontSize}px Arial`
    ctx.fillStyle = props.color
    ctx.textAlign = 'center'
    ctx.textBaseline = 'middle'
    ctx.fillText(props.text, 0, 0)

    watermarkUrl.value = canvas.toDataURL()
}

const watermarkStyle = computed(() => ({
    position: 'absolute' as const,
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    pointerEvents: 'none' as const,
    backgroundImage: watermarkUrl.value ? `url(${watermarkUrl.value})` : 'none',
    backgroundRepeat: 'repeat',
    zIndex: props.zIndex
}))

onMounted(generateWatermark)
watch(() => [props.text, props.fontSize, props.color, props.rotate, props.gap], generateWatermark)
</script>

<style scoped lang="scss">
.watermark-wrapper {
    position: relative;
}
</style>
