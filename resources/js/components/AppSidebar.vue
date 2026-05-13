<script setup lang="ts">
import { Link, usePage, router } from '@inertiajs/vue3';
import { LayoutGrid,  LogInIcon, UserCheck  } from 'lucide-vue-next';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { AdminProducts, home, login, register } from '@/routes';
import type { NavItem } from '@/types';

// Получаем доступ к shared props (включая auth)
const page = usePage();
const isAuthenticated = () => !!page.props.auth?.user;


const getMainNavItems = (): NavItem[] => {
    if (isAuthenticated()) {
        return [
            {
                title: 'Админка',
                href: AdminProducts(),
                icon: LayoutGrid,
            },
            {
                title: 'Главная',
                href: home(),
                icon: LayoutGrid,
            },
        ]
    }
    return [
        {
            title: 'Главная',
            href: home(),
            icon: LayoutGrid,
        },
    ]
}

// Динамические футер-элементы в зависимости от авторизации
const getFooterNavItems = (): NavItem[] => {
    if (isAuthenticated()) {
        return [

        ];
    }

    return [
        {
            title: 'Войти',
            href: login(),
            icon: LogInIcon,
        },
        {
            title: 'Регистрация',
            href: register(),
            icon: UserCheck,
        },
    ];
};



</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="home()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="getMainNavItems()" />
        </SidebarContent>

        <SidebarFooter>
            <!-- Динамический футер в зависимости от авторизации -->
            <NavFooter :items="getFooterNavItems()" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>