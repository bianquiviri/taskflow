<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Avatar from '../Components/Avatar.vue';
import FlashMessages from '../Components/FlashMessages.vue';
import Icon from '../Components/Icon.vue';
import ThemeToggle from '../Components/ThemeToggle.vue';
import { useCan } from '../Composables/useCan';
import { useTheme } from '../Composables/useTheme';

const DESKTOP_QUERY = '(min-width: 1024px)';

const props = defineProps({
    appName: { type: String, default: 'TaskFlow' },
    user: {
        type: Object,
        default: null,
    },
    navItems: {
        type: Array,
        default: () => [
            { label: 'Dashboard', href: '/dashboard', icon: 'home' },
            { label: 'Projects', href: '/projects', icon: 'projects' },
            { label: 'Tasks', href: '/tasks', icon: 'tasks' },
            { label: 'Settings', href: '/settings', icon: 'settings' },
        ],
    },
});

const page = usePage();
const drawerOpen = ref(false);
const isDesktop = ref(false);
const drawerNav = ref(null);
const drawerTrigger = ref(null);
let mediaQuery = null;

const flash = computed(() => page.props.flash ?? {});
const { theme } = useTheme(() => page.props.theme);
const { can } = useCan();

const currentUser = computed(() => props.user ?? page.props.auth?.user ?? null);
const team = computed(() => page.props.auth?.team ?? null);
const navigation = computed(() => {
    const current = team.value;

    if (current === null || !can('teams.view')) {
        return props.navItems;
    }

    return [...props.navItems, { label: current.name, href: `/teams/${current.id}`, icon: 'projects' }];
});

/**
 * Off-canvas the drawer is translated away, so it must also leave the tab order
 * and the accessibility tree. On wide screens the sidebar is always in place.
 */
const drawerIsAway = computed(() => !isDesktop.value && !drawerOpen.value);

function isActive(href) {
    const current = String(page.url).split(/[?#]/)[0];

    if (current === href) {
        return true;
    }

    return href !== '/' && current.startsWith(`${href}/`);
}

function syncViewport(matches) {
    isDesktop.value = matches ?? mediaQuery?.matches ?? false;
}

function openDrawer() {
    drawerOpen.value = true;
    nextTick(() => drawerNav.value?.focus());
}

/**
 * Focus only travels back to the trigger when it was inside the drawer, so a
 * mouse user closing it over the overlay is left alone.
 */
function closeDrawer() {
    const focusWasInside = drawerNav.value?.closest('aside')?.contains(document.activeElement) ?? false;

    drawerOpen.value = false;

    if (focusWasInside) {
        nextTick(() => drawerTrigger.value?.focus());
    }
}

function handleKeydown(event) {
    if (event.key !== 'Escape' || !drawerOpen.value) {
        return;
    }

    event.preventDefault();
    closeDrawer();
}

watch(
    () => page.url,
    () => {
        closeDrawer();
    },
);

onMounted(() => {
    mediaQuery = window.matchMedia(DESKTOP_QUERY);
    syncViewport();
    mediaQuery.addEventListener('change', syncViewport);
    document.addEventListener('keydown', handleKeydown);
});

onBeforeUnmount(() => {
    mediaQuery?.removeEventListener('change', syncViewport);
    document.removeEventListener('keydown', handleKeydown);
});
</script>

<template>
  <div class="min-h-dvh bg-canvas">
    <a
      href="#main-content"
      class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[70] focus:rounded-control focus:bg-raised focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-content focus:shadow-lg"
    >
      Skip to content
    </a>

    <div
      v-if="drawerOpen"
      data-test="drawer-overlay"
      class="fixed inset-0 z-40 bg-overlay backdrop-blur-sm lg:hidden"
      aria-hidden="true"
      @click="closeDrawer()"
    />

    <aside
      id="app-drawer"
      :inert="drawerIsAway ? '' : undefined"
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
          class="rounded-md p-1.5 text-content-subtle hover:bg-sunken hover:text-content focus-visible:ring-2 focus-visible:ring-focus lg:hidden"
          aria-label="Close menu"
          @click="closeDrawer()"
        >
          <Icon
            name="close"
            class="size-5"
          />
        </button>
      </div>

      <nav
        ref="drawerNav"
        tabindex="-1"
        aria-label="Main"
        class="flex-1 space-y-1 overflow-y-auto px-3 py-4 focus:outline-none"
      >
        <Link
          v-for="item in navigation"
          :key="item.href"
          :href="item.href"
          class="flex items-center gap-3 rounded-control px-3 py-2 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus"
          :class="isActive(item.href)
            ? 'bg-brand-soft text-brand-text'
            : 'text-content-muted hover:bg-sunken hover:text-content'"
          :aria-current="isActive(item.href) ? 'page' : undefined"
          @click="closeDrawer()"
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
          v-if="currentUser"
          class="flex items-center gap-3"
        >
          <Avatar
            :name="currentUser.name"
            :src="currentUser.avatar"
            size="sm"
          />
          <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-medium text-content">
              {{ currentUser.name }}
            </p>
            <p
              v-if="currentUser.email"
              class="truncate text-xs text-content-subtle"
            >
              {{ currentUser.email }}
            </p>
          </div>
        </div>
        <p
          v-else
          class="text-xs text-content-subtle"
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
          ref="drawerTrigger"
          type="button"
          class="rounded-md p-2 text-content-subtle hover:bg-sunken hover:text-content focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus lg:hidden"
          aria-label="Open menu"
          aria-controls="app-drawer"
          :aria-expanded="drawerOpen"
          @click="openDrawer()"
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
            class="rounded-md p-2 text-content-subtle hover:bg-sunken hover:text-content focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus"
            aria-label="Notifications"
          >
            <Icon
              name="bell"
              class="size-5"
            />
          </button>
          <Avatar
            v-if="currentUser"
            :name="currentUser.name"
            :src="currentUser.avatar"
            :decorative="false"
            size="sm"
          />
        </div>
      </header>

      <FlashMessages :messages="flash" />

      <!-- The skip link targets this region, so it is focusable without being a tab stop. -->
      <main
        id="main-content"
        tabindex="-1"
        class="flex-1 px-4 py-gutter focus:outline-none sm:px-6 lg:px-8"
      >
        <slot />
      </main>
    </div>
  </div>
</template>
