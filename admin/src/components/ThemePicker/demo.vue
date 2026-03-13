<template>
    <div class="p-4">
        <ThemePicker />
        <div class="card mt-4 p-4">
            <div class="text-title text-[color:var(--color-text-primary)]">Design Tokens 示例</div>
            <div class="mt-3 flex gap-3">
                <el-button type="primary">主按钮</el-button>
                <el-button type="success">成功</el-button>
                <el-button type="warning">警告</el-button>
                <el-button type="danger">错误</el-button>
                <button class="btn-primary">UnoCSS 自定义按钮</button>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts"></script>
