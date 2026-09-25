<script setup>
defineProps({
    tone: {
        type: String,
        default: 'indigo',
        validator: (value) => ['indigo', 'sky', 'gray', 'green', 'amber', 'red'].includes(value),
    },
});

const toneClasses = {
    indigo: 'bg-indigo-100 text-indigo-700 ring-indigo-600/20 dark:bg-indigo-500/10 dark:text-indigo-300 dark:ring-indigo-400/20',
    sky: 'bg-sky-100 text-sky-700 ring-sky-600/20 dark:bg-sky-500/10 dark:text-sky-300 dark:ring-sky-400/20',
    gray: 'bg-gray-100 text-gray-700 ring-gray-600/20 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-400/20',
    green: 'bg-green-100 text-green-700 ring-green-600/20 dark:bg-green-500/10 dark:text-green-300 dark:ring-green-400/20',
    amber: 'bg-amber-100 text-amber-800 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-400/20',
    red: 'bg-red-100 text-red-700 ring-red-600/20 dark:bg-red-500/10 dark:text-red-300 dark:ring-red-400/20',
};
</script>

<template>
  <span
    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset"
    :class="toneClasses[tone]"
  >
    <slot />
  </span>
</template>
