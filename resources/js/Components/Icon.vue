<script setup>
import { computed } from 'vue';
import IconBell from './icons/IconBell.vue';
import IconCheck from './icons/IconCheck.vue';
import IconClose from './icons/IconClose.vue';
import IconHome from './icons/IconHome.vue';
import IconInfo from './icons/IconInfo.vue';
import IconLogo from './icons/IconLogo.vue';
import IconLogout from './icons/IconLogout.vue';
import IconMenu from './icons/IconMenu.vue';
import IconMoon from './icons/IconMoon.vue';
import IconPlus from './icons/IconPlus.vue';
import IconProjects from './icons/IconProjects.vue';
import IconSettings from './icons/IconSettings.vue';
import IconSun from './icons/IconSun.vue';
import IconTasks from './icons/IconTasks.vue';
import IconUser from './icons/IconUser.vue';
import IconWarning from './icons/IconWarning.vue';

const props = defineProps({
    name: { type: String, required: true },
});

const icons = {
    bell: IconBell,
    check: IconCheck,
    close: IconClose,
    home: IconHome,
    info: IconInfo,
    logo: IconLogo,
    logout: IconLogout,
    menu: IconMenu,
    moon: IconMoon,
    plus: IconPlus,
    projects: IconProjects,
    settings: IconSettings,
    sun: IconSun,
    tasks: IconTasks,
    user: IconUser,
    warning: IconWarning,
};

const iconComponent = computed(() => icons[props.name]);
</script>

<template>
  <component :is="iconComponent" />
</template>