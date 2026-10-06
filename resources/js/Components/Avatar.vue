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
    /**
     * Decorative avatars repeat a name that is already rendered as text next to
     * them. Pass false when the avatar is the only carrier of that name.
     */
    decorative: { type: Boolean, default: true },
});

const sizeClasses = {
    sm: 'size-8 text-xs',
    md: 'size-10 text-sm',
    lg: 'size-14 text-base',
};

const tones = [
    'bg-brand-500 text-content-inverted',
    'bg-success-soft text-success-text',
    'bg-danger-soft text-danger-text',
    'bg-warning-soft text-warning-text',
    'bg-info-soft text-info-text',
    'bg-sunken text-content-muted',
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
    class="inline-flex shrink-0 items-center justify-center overflow-hidden rounded-full font-semibold ring-1 ring-line-strong"
    :class="[sizeClasses[size], src ? '' : tone]"
    :aria-hidden="decorative ? 'true' : undefined"
    :role="decorative ? undefined : 'img'"
    :aria-label="decorative ? undefined : name"
  >
    <img
      v-if="src"
      :src="src"
      :alt="decorative ? name : ''"
      class="size-full object-cover"
    >
    <span v-else-if="initials">{{ initials }}</span>
    <IconUser
      v-else
      class="size-1/2"
    />
  </span>
</template>