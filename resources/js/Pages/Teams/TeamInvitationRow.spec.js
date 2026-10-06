import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';

const { formState } = vi.hoisted(() => ({
    formState: {
        processing: false,
        errors: {},
        post: vi.fn(),
        delete: vi.fn(),
    },
}));

vi.mock('@inertiajs/vue3', () => ({
    useForm: (initial) => {
        Object.assign(formState, initial);
        return formState;
    },
}));

import TeamInvitationRow from './TeamInvitationRow.vue';

const team = { id: 7, name: 'Platform', slug: 'platform' };

const roles = [
    { value: 'admin', label: 'Admin', tone: 'sky' },
    { value: 'member', label: 'Member', tone: 'gray' },
];

const invitation = {
    id: 30,
    email: 'invitee@example.com',
    role: 'admin',
    status: 'pending',
    expiresAt: '2026-10-07T10:00:00+00:00',
    canBeResent: true,
    canBeCancelled: true,
};

const mountRow = (overrides = {}) =>
    mount(TeamInvitationRow, {
        props: { team, invitation, roles, canManageMembers: true, ...overrides },
    });

beforeEach(() => {
    formState.processing = false;
    formState.errors = {};
    formState.post.mockReset();
    formState.delete.mockReset();
});

describe('Teams/TeamInvitationRow.vue', () => {
    it('shows the invited address with its role and expiry', () => {
        const text = mountRow().get('[data-test="invitation-row"]').text();

        expect(text).toContain('invitee@example.com');
        expect(text).toContain('Admin');
        expect(text).toContain('7 Oct 2026');
        expect(text).toContain('Pending');
    });

    it('labels an expired invitation', () => {
        const text = mountRow({ invitation: { ...invitation, status: 'expired' } })
            .get('[data-test="invitation-row"]')
            .text();

        expect(text).toContain('Expired');
    });

    it('labels a cancelled invitation', () => {
        const text = mountRow({ invitation: { ...invitation, status: 'cancelled' } })
            .get('[data-test="invitation-row"]')
            .text();

        expect(text).toContain('Cancelled');
    });

    it('resends a pending invitation', async () => {
        const wrapper = mountRow();

        await wrapper.get('[data-test="resend-invitation"]').trigger('click');

        expect(formState.post).toHaveBeenCalledWith('/teams/7/invitations/30/resend', expect.any(Object));
    });

    it('cancels a pending invitation', async () => {
        const wrapper = mountRow();

        await wrapper.get('[data-test="cancel-invitation"]').trigger('click');

        expect(formState.delete).toHaveBeenCalledWith('/teams/7/invitations/30', expect.any(Object));
    });

    it('hides the resend action when the invitation is no longer pending', () => {
        const wrapper = mountRow({ invitation: { ...invitation, canBeResent: false } });

        expect(wrapper.find('[data-test="resend-invitation"]').exists()).toBe(false);
        expect(wrapper.find('[data-test="cancel-invitation"]').exists()).toBe(true);
    });

    it('hides the actions from viewers that cannot manage members', () => {
        const wrapper = mountRow({ canManageMembers: false });

        expect(wrapper.find('[data-test="resend-invitation"]').exists()).toBe(false);
        expect(wrapper.find('[data-test="cancel-invitation"]').exists()).toBe(false);
    });

    it('keeps the row without a badge when the role is unknown', () => {
        const text = mountRow({ roles: [] }).get('[data-test="invitation-row"]').text();

        expect(text).toContain('invitee@example.com');
        expect(text).not.toContain('Admin');
    });
});
