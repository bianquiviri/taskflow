<script setup>
import { computed, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Avatar from '../Components/Avatar.vue';
import FlashMessages from '../Components/FlashMessages.vue';
import Icon from '../Components/Icon.vue';
import ThemeToggle from '../Components/ThemeToggle.vue';
import { useTheme } from '../Composables/useTheme';

defineProps({
    appName: { type: String, default: 'TaskFlow' },
    user: {
        type: Object,
        default: null,
    },
    navItems: {
        type: Array,
        default: () => [
            { label: 'Dashboard', href: '/', icon: 'home' },
            { label: 'Projects', href: '/projects', icon: 'projects' },
            { label: 'Tasks', href: '/tasks', icon: 'tasks' },
            { label: 'Settings', href: '/settings', icon: 'settings' },
        ],
    },
});

const page = usePage();
const drawerOpen = ref(false);

const flash = computed(() => page.props.flash ?? {});
const { theme } = useTheme(() => page.props.theme);

function isActive(href) {
    return page.url === href;
}

watch(
    () => page.url,
    () => {
        drawerOpen.value = false;
    },
);
</script>

<template>
  <div class="min-h-dvh bg-canvas">
    <div
      v-if="drawerOpen"
      class="fixed inset-0 z-40 bg-overlay backdrop-blur-sm lg:hidden"
      aria-hidden="true"
      @click="drawerOpen = false"
    />

    <aside
      class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-r border-line bg-raised transition-transform duration-200 ease-in-out lg:translate-x-0"
      :class="drawerOpen ? 'translate-x-0' : '-translate-x-full'"
    >
      <div class="flex h-16 items-center justify-between border-b border-line px-5">
        <Link
          href="/"
          class="flex items-center gap-2.5"
          @click="drawerOpen = false"
        >
          <span class="flex size-8 items-center justify-center rounded-control bg-brand-600 text-content-inverted">
            <Icon
              name="logo"
              class="size-5"
            />
          </span>
          <span class="text-lg font-bold tracking-tight text-content">{{ appName }}</span>
        </Link>
        <button
          type="button"
          class="rounded-md p-1.5 text-content-subtle hover:bg-sunken hover:text-content lg:hidden"
          aria-label="Close menu"
          @click="drawerOpen = false"
        >
          <Icon
            name="close"
            class="size-5"
          />
        </button>
      </div>

      <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
        <Link
          v-for="item in navItems"
          :key="item.href"
          :href="item.href"
          class="flex items-center gap-3 rounded-control px-3 py-2 text-sm font-medium transition-colors"
          :class="isActive(item.href)
            ? 'bg-brand-soft text-brand-text'
            : 'text-content-muted hover:bg-sunken hover:text-content'"
          :aria-current="isActive(item.href) ? 'page' : undefined"
          @click="drawerOpen = false"
        >
          <Icon
            :name="item.icon"
            class="size-5 shrink-0"
          />
          <span>{{ item.label }}</span>
        </Link>
      </nav>

      <div class="border-t border-line p-4">
        <div
          v-if="user"
          class="flex items-center gap-3"
        >
          <Avatar
            :name="user.name"
            :src="user.avatar"
            size="sm"
          />
          <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-medium text-content">
              {{ user.name }}
            </p>
            <p
              v-if="user.email"
              class="truncate text-xs text-content-subtle"
            >
              {{ user.email }}
            </p>
          </div>
        </div>
        <p
          v-else
          class="text-xs text-content-faint"
        >
          Signed in as guest
        </p>
      </div>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col lg:pl-72">
      <header
        class="sticky top-0 z-30 flex h-16 items-center gap-x-4 border-b border-line bg-raised/80 px-4 backdrop-blur sm:px-6 lg:px-8"
      >
        <button
          type="button"
          class="rounded-md p-2 text-content-subtle hover:bg-sunken hover:text-content lg:hidden"
          aria-label="Open menu"
          @click="drawerOpen = true"
        >
          <Icon
            name="menu"
            class="size-5"
          />
        </button>

        <slot name="header" />

        <div class="ml-auto flex items-center gap-x-3">
          <ThemeToggle :theme="theme" />
          <button
            type="button"
            class="rounded-md p-2 text-content-subtle hover:bg-sunken hover:text-content"
            aria-label="Notifications"
          >
            <Icon
              name="bell"
              class="size-5"
            />
          </button>
          <Avatar
            v-if="user"
            :name="user.name"
            :src="user.avatar"
            size="sm"
          />
        </div>
      </header>

      <FlashMessages :messages="flash" />

      <main class="flex-1 px-4 py-gutter sm:px-6 lg:px-8">
        <slot />
      </main>
    </div>
  </div>
</template>
