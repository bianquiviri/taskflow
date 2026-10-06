<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import Button from './Button.vue';
import EmptyState from './EmptyState.vue';

defineProps({
    attachments: { type: Array, required: true },
});

const removing = ref(0);

function remove(attachment) {
    removing.value = attachment.id;

    router.delete(attachment.delete_url, {
        preserveScroll: true,
        onFinish: () => {
            removing.value = 0;
        },
    });
}

function uploadedAt(attachment) {
    return attachment.uploaded_at
        ? new Date(attachment.uploaded_at).toLocaleString()
        : null;
}

function meta(attachment) {
    return [attachment.human_size, attachment.uploaded_by, uploadedAt(attachment)]
        .filter(Boolean)
        .join(' · ');
}
</script>

<template>
  <EmptyState
    v-if="attachments.length === 0"
    icon="projects"
    title="No attachments yet"
    description="Attach the spec, the mockup or the report so the team has it at hand."
  />

  <ul
    v-else
    class="space-y-3"
  >
    <li
      v-for="attachment in attachments"
      :key="attachment.id"
      data-test="attachment"
      class="flex flex-wrap items-center justify-between gap-3 rounded-panel border border-line bg-raised p-4 shadow-sm"
    >
      <div class="min-w-0">
        <a
          :href="attachment.download_url"
          data-test="attachment-download"
          class="block truncate text-sm font-semibold text-content underline decoration-line-strong underline-offset-4 hover:decoration-brand-500"
        >
          {{ attachment.name }}
        </a>
        <p
          data-test="attachment-meta"
          class="mt-1 truncate text-xs text-content-subtle"
        >
          {{ meta(attachment) }}
        </p>
      </div>

      <Button
        v-if="attachment.can_delete"
        data-test="attachment-remove"
        variant="danger"
        :loading="removing === attachment.id"
        :aria-label="`Remove ${attachment.name}`"
        @click="remove(attachment)"
      >
        Remove
      </Button>
    </li>
  </ul>
</template>
