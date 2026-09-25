<script setup>
import { computed, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Avatar from '../Components/Avatar.vue';
import FlashMessages from '../Components/FlashMessages.vue';
import Icon from '../Components/Icon.vue';

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
  <div class="min-h-dvh bg-gray-100 dark:bg-gray-950">
    <div
      v-if="drawerOpen"
      class="fixed inset-0 z-40 bg-gray-900/50 backdrop-blur-sm lg:hidden"
      aria-hidden="true"
      @click="drawerOpen = false"
    />

    <aside
      class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-r border-gray-200 bg-white transition-transform duration-200 ease-in-out dark:border-gray-800 dark:bg-gray-900 lg:translate-x-0"
      :class="drawerOpen ? 'translate-x-0' : '-translate-x-full'"
    >
      <div class="flex h-16 items-center justify-between border-b border-gray-200 px-5 dark:border-gray-800">
        <Link
          href="/"
          class="flex items-center gap-2.5"
          @click="drawerOpen = false"
        >
          <span class="flex size-8 items-center justify-center rounded-lg bg-indigo-600 text-white">
            <Icon
              name="logo"
              class="size-5"
            />
          </span>
          <span class="text-lg font-bold tracking-tight text-gray-900 dark:text-white">{{ appName }}</span>
        </Link>
        <button
          type="button"
          class="rounded-md p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-300 lg:hidden"
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
          class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors"
          :class="isActive(item.href)
            ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300'
            : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white'"
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

      <div class="border-t border-gray-200 p-4 dark:border-gray-800">
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
            <p class="truncate text-sm font-medium text-gray-900 dark:text-white">
              {{ user.name }}
            </p>
            <p
              v-if="user.email"
              class="truncate text-xs text-gray-500 dark:text-gray-400"
            >
              {{ user.email }}
            </p>
          </div>
        </div>
        <p
          v-else
          class="text-xs text-gray-400 dark:text-gray-500"
        >
          Signed in as guest
        </p>
      </div>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col lg:pl-72">
      <header
        class="sticky top-0 z-30 flex h-16 items-center gap-x-4 border-b border-gray-200 bg-white/80 px-4 backdrop-blur dark:border-gray-800 dark:bg-gray-900/80 sm:px-6 lg:px-8"
      >
        <button
          type="button"
          class="rounded-md p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-300 lg:hidden"
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
          <button
            type="button"
            class="rounded-md p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-300"
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

      <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
        <slot />
      </main>
    </div>
  </div>
</template>