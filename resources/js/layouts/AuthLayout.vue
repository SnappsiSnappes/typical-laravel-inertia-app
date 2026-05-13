<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import AuthSimpleLayout from '@/layouts/auth/AuthSimpleLayout.vue';

const { props } = usePage();

// Проверяем, есть ли авторизованный пользователь
// Inertia обычно передает auth.user в глобальные пропсы
const isAuthenticated = computed(() => {
    return !!props.auth?.user;
});

const { title = '', description = '' } = defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <!-- Если юзер залогинен (это ConfirmPassword), используем Layout настроек -->
    <AppLayout v-if="isAuthenticated" :breadcrumbs="[]">
        <SettingsLayout>
            <div class="max-w-xl">
                <h2 class="text-lg font-medium mb-4">{{ title }}</h2>
                <p class="text-sm text-muted-foreground mb-6" v-if="description">
                    {{ description }}
                </p>
                <slot />
            </div>
        </SettingsLayout>
    </AppLayout>

    <!-- Если гость (Login/Register), используем простой AuthLayout -->
    <AuthSimpleLayout v-else :title="title" :description="description">
        <slot />
    </AuthSimpleLayout>
</template>