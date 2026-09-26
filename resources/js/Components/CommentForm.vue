<script setup>
import { useForm } from '@inertiajs/vue3';
import Button from './Button.vue';
import FormTextarea from './FormTextarea.vue';

const props = defineProps({
    url: { type: String, required: true },
});

const form = useForm({ body: '' });

function submit() {
    form.post(props.url, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
  <form
    class="space-y-3"
    @submit.prevent="submit"
  >
    <FormTextarea
      v-model="form.body"
      name="body"
      label="Add a comment"
      placeholder="Share an update or mention a teammate with @handle"
      :error="form.errors.body"
      :disabled="form.processing"
    />

    <div class="flex items-center justify-between gap-4">
      <p class="text-xs text-content-subtle">
        Mention a teammate with @handle to notify them.
      </p>
      <Button
        type="submit"
        :loading="form.processing"
      >
        Comment
      </Button>
    </div>
  </form>
</template>
