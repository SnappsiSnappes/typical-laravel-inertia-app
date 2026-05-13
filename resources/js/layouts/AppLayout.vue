<script setup lang="ts">
/* 
тут можно одной кнопкой поменять свое решение
сделай этот импорт если хочешь хедеры в заголовках
import AppLayout from '@/layouts/app/AppHeaderLayout.vue';

сделай этот импорт если хочешь хедеры в сайдбаре
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';

*/
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import type { BreadcrumbItem } from '@/types';

const { breadcrumbs = [] } = defineProps<{
    breadcrumbs?: BreadcrumbItem[];
}>();
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <slot />
    </AppLayout>
</template>
