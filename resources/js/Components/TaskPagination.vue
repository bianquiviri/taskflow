<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    links: { type: Array, required: true },
});

const ENTITIES = {
    '&laquo;': '\u00ab',
    '&raquo;': '\u00bb',
};

function label(value) {
    return String(value).replace(/&[a-z]+;/g, (entity) => ENTITIES[entity] ?? entity);
}
</script>

<template>
  <nav
    v-if="links.length > 0"
    data-test="task-pagination"
    aria-label="Pagination"
    class="mt-6 flex flex-wrap items-center gap-1"
  >
    <template
      v-for="link in links"
      :key="link.label"
    >
      <span
        v-if="!link.url"
        data-test="pagination-disabled"
        aria-disabled="true"
        class="rounded-control px-3 py-2 text-sm text-content-faint"
      >{{ label(link.label) }}</span>
      <Link
        v-else
        :href="link.url"
        preserve-scroll
        data-test="pagination-link"
        class="rounded-control px-3 py-2 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2 focus-visible:ring-offset-raised"
        :class="link.active
          ? 'bg-brand-soft text-brand-text'
          : 'text-content-muted hover:bg-sunken hover:text-content'"
        :aria-current="link.active ? 'page' : undefined"
      >
        {{ label(link.label) }}
      </Link>
    </template>
  </nav>
</template>
