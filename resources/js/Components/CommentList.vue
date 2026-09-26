<script setup>
import Avatar from './Avatar.vue';
import EmptyState from './EmptyState.vue';

defineProps({
    comments: { type: Array, required: true },
});

function authorName(comment) {
    return comment.user?.name ?? 'Former member';
}

function createdAt(comment) {
    return comment.created_at
        ? new Date(comment.created_at).toLocaleString()
        : null;
}
</script>

<template>
  <EmptyState
    v-if="comments.length === 0"
    icon="tasks"
    title="No comments yet"
    description="Start the conversation on this task."
  />

  <ul
    v-else
    class="space-y-4"
  >
    <li
      v-for="comment in comments"
      :key="comment.id"
      data-test="comment"
      class="flex gap-3 rounded-panel border border-line bg-raised p-4 shadow-sm"
    >
      <Avatar
        :name="authorName(comment)"
        size="sm"
      />
      <div class="min-w-0 flex-1">
        <p
          class="text-sm text-content"
        >
          <span class="font-semibold">{{ authorName(comment) }}</span>
          <span
            v-if="createdAt(comment)"
            class="ml-2 text-xs text-content-subtle"
          >
            {{ createdAt(comment) }}
          </span>
        </p>
        <p class="mt-1 whitespace-pre-line text-sm text-content-muted">
          {{ comment.body }}
        </p>
      </div>
    </li>
  </ul>
</template>
