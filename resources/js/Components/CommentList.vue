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
      class="flex gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900"
    >
      <Avatar
        :name="authorName(comment)"
        size="sm"
      />
      <div class="min-w-0 flex-1">
        <p
          class="text-sm text-gray-900 dark:text-white"
        >
          <span class="font-semibold">{{ authorName(comment) }}</span>
          <span
            v-if="createdAt(comment)"
            class="ml-2 text-xs text-gray-500 dark:text-gray-400"
          >
            {{ createdAt(comment) }}
          </span>
        </p>
        <p class="mt-1 whitespace-pre-line text-sm text-gray-700 dark:text-gray-200">
          {{ comment.body }}
        </p>
      </div>
    </li>
  </ul>
</template>
