import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import CommentList from './CommentList.vue';

const comments = [
    {
        id: 1,
        body: 'Ready for review',
        created_at: '2026-09-26T10:00:00.000000Z',
        user: { id: 7, name: 'Ada Lovelace' },
    },
    {
        id: 2,
        body: 'Shipped it',
        created_at: '2026-09-26T11:00:00.000000Z',
        user: { id: 8, name: 'Grace Hopper' },
    },
];

const IconStub = {
    props: ['name'],
    template: '<span data-test="empty-state-icon" :data-name="name" />',
};

describe('CommentList.vue', () => {
    it('renders every comment with its author and body', () => {
        const wrapper = mount(CommentList, { props: { comments } });

        const items = wrapper.findAll('[data-test="comment"]');

        expect(items).toHaveLength(2);
        expect(items[0].text()).toContain('Ada Lovelace');
        expect(items[0].text()).toContain('Ready for review');
        expect(items[1].text()).toContain('Grace Hopper');
        expect(items[1].text()).toContain('Shipped it');
    });

    it('labels a comment whose author was removed', () => {
        const wrapper = mount(CommentList, {
            props: { comments: [{ id: 3, body: 'Orphan', created_at: null, user: null }] },
        });

        const comment = wrapper.get('[data-test="comment"]');

        expect(comment.text()).toContain('Former member');
        expect(comment.text()).toContain('Orphan');
    });

    it('shows an empty state when the task has no comments', () => {
        const wrapper = mount(CommentList, {
            props: { comments: [] },
            global: { stubs: { Icon: IconStub } },
        });

        expect(wrapper.get('[data-test="empty-state-icon"]').attributes('data-name')).toBe('tasks');
        expect(wrapper.text()).toContain('No comments yet');
    });
});
