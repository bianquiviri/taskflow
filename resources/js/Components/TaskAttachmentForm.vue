<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Button from './Button.vue';

const props = defineProps({
    url: { type: String, required: true },
    accept: { type: Array, default: () => [] },
    maxSize: { type: String, default: '' },
});

const form = useForm({ file: null });
const input = ref(null);

const hint = computed(() => `Choose a file up to ${props.maxSize}.`);

const describedBy = computed(() =>
    [
        hint.value ? 'task-attachment-hint' : null,
        form.errors.file ? 'task-attachment-error' : null,
    ].filter(Boolean).join(' ') || undefined,
);

function select(event) {
    form.file = event.target.files?.[0] ?? null;
    form.clearErrors('file');
}

function submit() {
    form.post(props.url, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.file = null;

            if (input.value) {
                input.value.value = '';
            }
        },
    });
}
</script>

<template>
  <form
    class="space-y-3"
    @submit.prevent="submit"
  >
    <div>
      <label
        for="task-attachment-file"
        class="mb-1.5 block text-sm font-medium text-content-muted"
      >
        Attach a file
      </label>
      <input
        id="task-attachment-file"
        ref="input"
        type="file"
        name="file"
        data-test="attachment-input"
        :accept="accept.join(',')"
        :disabled="form.processing"
        :aria-invalid="form.errors.file ? 'true' : undefined"
        :aria-describedby="describedBy"
        class="block w-full rounded-control border border-line-strong bg-raised px-3 py-2 text-sm text-content-muted shadow-sm file:mr-3 file:rounded-control file:border-0 file:bg-sunken file:px-3 file:py-1 file:text-sm file:font-semibold file:text-content-muted hover:file:bg-line focus:outline-none focus:ring-2 focus:ring-focus focus:ring-offset-2 focus:ring-offset-canvas disabled:cursor-not-allowed disabled:bg-sunken"
        @change="select"
      >
      <p
        v-if="form.errors.file"
        id="task-attachment-error"
        data-test="attachment-error"
        class="mt-1.5 text-sm text-danger-text"
        role="alert"
      >
        {{ form.errors.file }}
      </p>
    </div>

    <div class="flex items-center justify-between gap-4">
      <p
        id="task-attachment-hint"
        class="text-xs text-content-subtle"
      >
        {{ hint }}
      </p>
      <Button
        type="submit"
        :loading="form.processing"
        :disabled="!form.file"
      >
        Upload
      </Button>
    </div>
  </form>
</template>
