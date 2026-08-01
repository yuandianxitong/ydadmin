<template>
    <el-select disabled :model-value="modelValue" placeholder="未启用">
        <el-option v-if="modelValue" :label="String(modelValue)" :value="modelValue" />
    </el-select>
</template>
<script setup lang="ts">
defineProps<{ modelValue?: any; url?: string; multiple?: boolean }>()
defineEmits<{ (e: 'update:modelValue', v: any): void }>()
</script>
