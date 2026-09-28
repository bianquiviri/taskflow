<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Badge from '../../Components/Badge.vue';
import Button from '../../Components/Button.vue';
import Icon from '../../Components/Icon.vue';

const props = defineProps({
    team: { type: Object, required: true },
    invitation: { type: Object, required: true },
    roles: { type: Array, required: true },
    canManageMembers: { type: Boolean, default: false },
});

const form = useForm({});

const role = computed(
    () => props.roles.find((option) => option.value === props.invitation.role) ?? null,
);

const statuses = {
    pending: { label: 'Pending', tone: 'amber' },
    expired: { label: 'Expired', tone: 'gray' },
    cancelled: { label: 'Cancelled', tone: 'gray' },
};

const status = computed(() => statuses[props.invitation.status] ?? statuses.pending);

const formattedExpiry = computed(() =>
    new Intl.DateTimeFormat('en-GB', { dateStyle: 'medium' }).format(
        new Date(props.invitation.expiresAt),
    ),
);

function resend() {
    form.post(`/teams/${props.team.id}/invitations/${props.invitation.id}/resend`, {
        preserveScroll: true,
    });
}

function cancel() {
    form.delete(`/teams/${props.team.id}/invitations/${props.invitation.id}`, {
        preserveScroll: true,
    });
}
</script>

<template>
  <li
    data-test="invitation-row"
    class="flex flex-wrap items-center gap-3 rounded-panel border border-line bg-raised px-4 py-3"
  >
    <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-warning-soft text-warning-text">
      <Icon
        name="bell"
        class="size-4"
      />
    </span>
    <div class="min-w-0 flex-1">
      <p class="truncate text-sm text-content">
        {{ invitation.email ?? 'Anyone with the link' }}
      </p>
      <p class="text-xs text-content-muted">
        Expires on {{ formattedExpiry }}
      </p>
    </div>
    <Badge :tone="role ? role.tone : 'gray'">
      {{ role ? role.label : invitation.role }}
    </Badge>
    <Badge :tone="status.tone">
      {{ status.label }}
    </Badge>
    <Button
      v-if="invitation.canBeResent && canManageMembers"
      data-test="resend-invitation"
      variant="secondary"
      :disabled="form.processing"
      @click="resend"
    >
      Resend
    </Button>
    <Button
      v-if="invitation.canBeCancelled && canManageMembers"
      data-test="cancel-invitation"
      variant="ghost"
      :disabled="form.processing"
      @click="cancel"
    >
      Cancel
    </Button>
  </li>
</template>
