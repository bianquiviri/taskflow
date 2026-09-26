<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { applyTheme, oppositeTheme, resolveTheme } from '../Composables/useTheme';
import Icon from './Icon.vue';

const props = defineProps({
    theme: {
        type: String,
        default: 'light',
    },
});

const isDark = computed(() => resolveTheme(props.theme) === 'dark');

const label = computed(() => (isDark.value ? 'Switch to light mode' : 'Switch to dark mode'));

function toggle() {
    const theme = oppositeTheme(props.theme);

    applyTheme(theme);

    router.patch('/theme', { theme }, { preserveScroll: true, preserveState: true });
}
</script>

<template>
  <button
    type="button"
    :aria-label="label"
    :aria-pressed="isDark"
    class="flex size-control items-center justify-center rounded-control text-content-subtle transition-colors hover:bg-sunken hover:text-content focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus"
    @click="toggle"
  >
    <Icon
      :name="isDark ? 'sun' : 'moon'"
      class="size-5"
    />
  </button>
</template>
