<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import Avatar from '../../Components/Avatar.vue';
import Badge from '../../Components/Badge.vue';
import Button from '../../Components/Button.vue';
import EmptyState from '../../Components/EmptyState.vue';
import FormInput from '../../Components/FormInput.vue';
import FormSelect from '../../Components/FormSelect.vue';
import Icon from '../../Components/Icon.vue';

const props = defineProps({
    team: { type: Object, required: true },
    members: { type: Array, required: true },
    pendingInvitations: { type: Array, required: true },
    roles: { type: Array, required: true },
    invitableRoles: { type: Array, required: true },
    canManageMembers: { type: Boolean, default: false },
});

const form = useForm({
    email: '',
    role: 'member',
});

const roleFor = (value) => props.roles.find((role) => role.value === value) ?? null;

const formattedExpiry = (value) =>
    new Intl.DateTimeFormat('en-GB', { dateStyle: 'medium' }).format(new Date(value));

function invite() {
    form.post(`/teams/${props.team.id}/invitations`, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function revoke(invitation) {
    form.delete(`/teams/${props.team.id}/invitations/${invitation.id}`, { preserveScroll: true });
}

function removeMember(member) {
    form.delete(`/teams/${props.team.id}/members/${member.id}`, { preserveScroll: true });
}
</script>

<template>
  <Head :title="team.name" />

  <div class="mx-auto w-full max-w-4xl px-6 py-10">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h1 class="text-3xl font-bold tracking-tight text-content">
        {{ team.name }}
      </h1>
      <Link
        data-test="team-settings-link"
        :href="`/teams/${team.id}/settings`"
        class="text-sm font-medium text-brand-text hover:underline"
      >
        Team settings
      </Link>
    </div>

    <section
      v-if="canManageMembers"
      data-test="invite-form"
      class="mt-8 rounded-panel border border-line bg-raised p-5 shadow-sm"
    >
      <h2 class="text-sm font-semibold uppercase tracking-wide text-content-subtle">
        Invite somebody
      </h2>

      <form
        class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-start"
        @submit.prevent="invite"
      >
        <FormInput
          v-model="form.email"
          class="flex-1"
          name="email"
          type="email"
          label="Email address"
          placeholder="teammate@example.com"
          :error="form.errors.email"
          :disabled="form.processing"
        />
        <FormSelect
          v-model="form.role"
          class="sm:w-40"
          name="role"
          label="Role"
          :error="form.errors.role"
          :disabled="form.processing"
        >
          <option
            v-for="role in invitableRoles"
            :key="role.value"
            :value="role.value"
          >
            {{ role.label }}
          </option>
        </FormSelect>
        <Button
          class="mt-7"
          type="submit"
          :loading="form.processing"
        >
          Send invitation
        </Button>
      </form>
    </section>

    <section class="mt-10">
      <h2 class="text-sm font-semibold uppercase tracking-wide text-content-subtle">
        Members
      </h2>

      <EmptyState
        v-if="members.length === 0"
        data-test="members-empty"
        class="mt-4"
        icon="user"
        title="No members yet"
        description="Invite somebody to start collaborating."
      />

      <ul
        v-else
        class="mt-4 space-y-3"
      >
        <li
          v-for="member in members"
          :key="member.id"
          data-test="member-row"
          class="flex items-center gap-3 rounded-panel border border-line bg-raised px-4 py-3"
        >
          <Avatar
            size="sm"
            :name="member.name"
          />
          <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-medium text-content">
              {{ member.name }}
            </p>
            <p class="truncate text-xs text-content-subtle">
              {{ member.email }}
            </p>
          </div>
          <Badge
            v-if="roleFor(member.role)"
            :tone="roleFor(member.role).tone"
          >
            {{ roleFor(member.role).label }}
          </Badge>
          <Button
            v-if="member.canBeRemoved"
            variant="ghost"
            @click="removeMember(member)"
          >
            Remove
          </Button>
        </li>
      </ul>
    </section>

    <section class="mt-10">
      <h2 class="text-sm font-semibold uppercase tracking-wide text-content-subtle">
        Pending invitations
      </h2>

      <div
        v-if="pendingInvitations.length === 0"
        data-test="invitations-empty"
        class="mt-4"
      >
        <EmptyState
          icon="bell"
          title="No pending invitations"
          description="Invitations that are waiting for an answer will show up here."
        />
      </div>

      <ul
        v-else
        class="mt-4 space-y-3"
      >
        <li
          v-for="invitation in pendingInvitations"
          :key="invitation.id"
          data-test="invitation-row"
          class="flex items-center gap-3 rounded-panel border border-line bg-raised px-4 py-3"
        >
          <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-warning-soft text-warning-text">
            <Icon
              name="bell"
              class="size-4"
            />
          </span>
          <div class="min-w-0 flex-1">
            <p class="truncate text-sm text-content">
              {{ invitation.email }}
            </p>
            <p class="text-xs text-content-subtle">
              Expires on {{ formattedExpiry(invitation.expiresAt) }}
            </p>
          </div>
          <Badge
            v-if="roleFor(invitation.role)"
            :tone="roleFor(invitation.role).tone"
          >
            {{ roleFor(invitation.role).label }}
          </Badge>
          <Button
            v-if="canManageMembers"
            variant="ghost"
            @click="revoke(invitation)"
          >
            Revoke
          </Button>
        </li>
      </ul>
    </section>
  </div>
</template>
