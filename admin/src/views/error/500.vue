<template>
    <div class="error500">
        <error code="500" :title="$t('errorPage.serverError')"></error>
    </div>
</template>

<script lang="ts" setup>
import Error from './components/error.vue'
</script>
