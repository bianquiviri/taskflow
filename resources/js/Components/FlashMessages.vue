<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import Icon from './Icon.vue';

const props = defineProps({
    messages: {
        type: Object,
        default: () => ({}),
    },
});

const AUTO_DISMISS_MS = 5000;

const toneConfig = {
    success: {
        icon: 'check',
        text: 'text-gray-900 dark:text-white',
        iconColor: 'text-emerald-500',
        label: 'Success',
    },
    error: {
        icon: 'warning',
        text: 'text-gray-900 dark:text-white',
        iconColor: 'text-rose-500',
        label: 'Error',
    },
    warning: {
        icon: 'warning',
        text: 'text-gray-900 dark:text-white',
        iconColor: 'text-amber-500',
        label: 'Warning',
    },
    info: {
        icon: 'info',
        text: 'text-gray-900 dark:text-white',
        iconColor: 'text-sky-500',
        label: 'Information',
    },
};

const toasts = ref([]);
const timers = new Map();
let nextId = 0;

function normalize(value) {
    if (!value) {
        return [];
    }
    return Array.isArray(value) ? value : [value];
}

function dismiss(id) {
    clearTimeout(timers.get(id));
    timers.delete(id);
    toasts.value = toasts.value.filter((toast) => toast.id !== id);
}

function pushToast(tone, text) {
    const id = nextId;
    nextId += 1;
    toasts.value.push({ id, tone, text });
    timers.set(
        id,
        setTimeout(() => dismiss(id), AUTO_DISMISS_MS),
    );
}

watch(
    () => props.messages,
    (messages) => {
        for (const [tone, value] of Object.entries(messages)) {
            if (!toneConfig[tone]) {
                continue;
            }
            for (const text of normalize(value)) {
                pushToast(tone, text);
            }
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    timers.forEach((timer) => clearTimeout(timer));
    timers.clear();
    toasts.value = [];
});
</script>

<template>
  <div
    v-if="toasts.length"
    aria-live="polite"
    class="fixed right-4 top-4 z-[60] flex w-full max-w-sm flex-col gap-3 sm:right-6 sm:top-6"
  >
    <div
      v-for="toast in toasts"
      :key="toast.id"
      role="status"
      class="flex items-start gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-lg dark:border-gray-700 dark:bg-gray-900"
    >
      <Icon
        :name="toneConfig[toast.tone].icon"
        :class="['mt-0.5 size-5 shrink-0', toneConfig[toast.tone].iconColor]"
      />
      <div class="min-w-0 flex-1">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
          {{ toneConfig[toast.tone].label }}
        </p>
        <p
          class="mt-0.5 text-sm font-medium"
          :class="toneConfig[toast.tone].text"
        >
          {{ toast.text }}
        </p>
      </div>
      <button
        type="button"
        class="rounded-md p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-300"
        :aria-label="`Dismiss ${toneConfig[toast.tone].label} message`"
        @click="dismiss(toast.id)"
      >
        <Icon
          name="close"
          class="size-4"
        />
      </button>
    </div>
  </div>
</template>