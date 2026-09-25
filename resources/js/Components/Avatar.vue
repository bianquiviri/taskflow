<script setup>
import { computed } from 'vue';
import IconUser from './icons/IconUser.vue';

const props = defineProps({
    name: { type: String, default: '' },
    src: { type: String, default: null },
    size: {
        type: String,
        default: 'md',
        validator: (value) => ['sm', 'md', 'lg'].includes(value),
    },
});

const sizeClasses = {
    sm: 'size-8 text-xs',
    md: 'size-10 text-sm',
    lg: 'size-14 text-base',
};

const tones = [
    'bg-indigo-500',
    'bg-emerald-500',
    'bg-rose-500',
    'bg-amber-500',
    'bg-sky-500',
    'bg-violet-500',
    'bg-teal-500',
    'bg-fuchsia-500',
];

const initials = computed(() => {
    const parts = props.name.trim().split(/\s+/).filter(Boolean).slice(0, 2);
    return parts.map((part) => part[0].toUpperCase()).join('');
});

const tone = computed(() => {
    let hash = 0;
    for (let i = 0; i < props.name.length; i += 1) {
        hash = (hash << 5) - hash + props.name.charCodeAt(i);
        hash |= 0;
    }
    return tones[Math.abs(hash) % tones.length];
});
</script>

<template>
  <span
    class="inline-flex shrink-0 items-center justify-center overflow-hidden rounded-full font-semibold text-white ring-1 ring-black/10 dark:ring-white/20"
    :class="[sizeClasses[size], src ? '' : tone]"
    aria-hidden="true"
  >
    <img
      v-if="src"
      :src="src"
      :alt="name"
      class="size-full object-cover"
    >
    <span v-else-if="initials">{{ initials }}</span>
    <IconUser
      v-else
      class="size-1/2"
    />
  </span>
</template>