import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';

const { formState } = vi.hoisted(() => ({
    formState: {
        role: 'member',
        processing: false,
        errors: {},
        post: vi.fn(),
        patch: vi.fn(),
        delete: vi.fn(),
        reset: vi.fn(),
    },
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: { props: ['title'], template: '<div />' },
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
    useForm: (initial) => {
        Object.assign(formState, initial);
        return formState;
    },
}));

import TeamMemberRow from './TeamMemberRow.vue';

const team = { id: 7, name: 'Platform', slug: 'platform' };

const roles = [
    { value: 'admin', label: 'Admin', tone: 'sky' },
    { value: 'member', label: 'Member', tone: 'gray' },
];

const member = {
    id: 2,
    name: 'Grace Hopper',
    email: 'grace@example.com',
    role: 'member',
    canBeRemoved: true,
    canBeReassigned: true,
};

const mountRow = (overrides = {}) =>
    mount(TeamMemberRow, {
        props: { team, member, roles, canManageMembers: true, ...overrides },
    });

beforeEach(() => {
    Object.assign(formState, { role: 'member', processing: false, errors: {} });
    formState.post.mockReset();
    formState.patch.mockReset();
    formState.delete.mockReset();
});

describe('Teams/TeamMemberRow.vue', () => {
    it('shows the member identity with a role badge', () => {
        const text = mountRow().get('[data-test="member-row"]').text();

        expect(text).toContain('Grace Hopper');
        expect(text).toContain('grace@example.com');
        expect(text).toContain('Member');
    });

    it('saves a new role for a manager', async () => {
        const wrapper = mountRow();

        await wrapper.get('select[name="role"]').setValue('admin');
        await wrapper.get('form').trigger('submit');

        expect(formState.patch).toHaveBeenCalledWith('/teams/7/members/2', expect.any(Object));
    });

    it('clears the form once the role is saved', async () => {
        const wrapper = mountRow();

        await wrapper.get('form').trigger('submit');
        const [, options] = formState.patch.mock.calls[0];
        options.onSuccess();

        expect(options.preserveScroll).toBe(true);
        expect(formState.reset).toHaveBeenCalled();
    });

    it('displays the role validation error', () => {
        formState.errors = { role: 'The owner role cannot be changed.' };

        expect(mountRow().get('[role="alert"]').text()).toBe('The owner role cannot be changed.');
    });

    it('removes the member', async () => {
        const wrapper = mountRow();

        await wrapper.get('[data-test="remove-member"]').trigger('click');

        expect(formState.delete).toHaveBeenCalledWith('/teams/7/members/2', expect.any(Object));
    });

    it('hides the controls a viewer may not use', () => {
        const wrapper = mountRow({
            member: { ...member, canBeRemoved: false, canBeReassigned: false },
            canManageMembers: false,
        });

        expect(wrapper.find('form').exists()).toBe(false);
        expect(wrapper.find('[data-test="remove-member"]').exists()).toBe(false);
    });

    it('keeps the badge when the role is unknown', () => {
        const wrapper = mountRow({ member: { ...member, role: 'ghost' }, roles: [] });

        expect(wrapper.get('[data-test="member-row"]').text()).toContain('Grace Hopper');
        expect(wrapper.get('[data-test="member-row"]').text()).not.toContain('Member');
    });
});
