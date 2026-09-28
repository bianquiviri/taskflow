<script setup>
import { useId } from 'vue';
import Avatar from '../../Components/Avatar.vue';
import Button from '../../Components/Button.vue';

defineProps({
    name: { type: String, required: true },
    src: { type: String, default: null },
    error: { type: String, default: '' },
    processing: { type: Boolean, default: false },
});

const emit = defineEmits(['select', 'remove']);
const inputId = `avatar-input-${useId()}`;

function selected(event) {
    const file = event.target.files?.[0];

    if (file) {
        emit('select', file);
    }
}
</script>

<template>
  <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
    <Avatar
      :name="name"
      :src="src"
      size="lg"
    />

    <div class="flex-1">
      <div class="flex flex-wrap items-center gap-3">
        <label
          :for="inputId"
          class="rounded-control border border-line-strong bg-raised px-4 py-2 text-sm font-semibold text-content-muted shadow-sm transition-colors hover:bg-sunken hover:text-content focus-within:ring-2 focus-within:ring-focus"
          :class="processing ? 'pointer-events-none opacity-60' : 'cursor-pointer'"
        >
          <span>{{ src ? 'Change photo' : 'Upload a photo' }}</span>
          <input
            :id="inputId"
            data-test="avatar-input"
            class="sr-only"
            type="file"
            accept="image/jpeg,image/png,image/webp"
            :disabled="processing"
            @change="selected"
          >
        </label>

        <Button
          v-if="src"
          data-test="avatar-remove"
          variant="secondary"
          :disabled="processing"
          @click="emit('remove')"
        >
          Remove
        </Button>
      </div>

      <p
        v-if="error"
        data-test="avatar-error"
        class="mt-2 text-sm text-danger-text"
        role="alert"
      >
        {{ error }}
      </p>
      <p
        v-else
        class="mt-2 text-xs text-content-subtle"
      >
        JPEG, PNG or WebP, up to 2 MB.
      </p>
    </div>
  </div>
</template>
