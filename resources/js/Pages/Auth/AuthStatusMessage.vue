<script setup>
import { computed } from 'vue';
import Icon from '../../Components/Icon.vue';

/*
| The auth controllers flash translation keys, not copy, so rendering the raw
| value would put `password-reset-link-sent` in front of the visitor. Unknown
| keys are humanized rather than dropped, so a new backend message still reads
| as a sentence.
*/
const MESSAGES = {
    'password-reset-link-sent': 'We have emailed you a password reset link. Check your inbox.',
    'verification-link-sent': 'A fresh verification link is on its way to your inbox.',
    'email-verified': 'Your email address is verified. Welcome to TaskFlow!',
};

const props = defineProps({
    status: { type: String, default: null },
});

const message = computed(() => {
    const status = props.status?.trim();

    if (!status) {
        return '';
    }

    return MESSAGES[status] ?? humanize(status);
});

function humanize(value) {
    const label = value.replace(/[_-]+/g, ' ').trim();

    return label ? `${label.charAt(0).toUpperCase()}${label.slice(1)}` : '';
}
</script>

<template>
  <p
    v-if="message"
    data-test="auth-status"
    role="status"
    aria-live="polite"
    class="flex items-start gap-2.5 rounded-control border border-success/30 bg-success-soft px-3.5 py-3 text-sm font-medium text-success-text"
  >
    <Icon
      name="check"
      aria-hidden="true"
      class="mt-0.5 size-4 shrink-0"
    />
    <span>{{ message }}</span>
  </p>
</template>
