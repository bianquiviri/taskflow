import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';

vi.mock('@inertiajs/vue3', () => ({
    Head: {
        props: ['title'],
        template: '<div />',
    },
    useForm: (initial) => ({ body: '', processing: false, errors: {}, post: vi.fn(), ...initial }),
}));

import Show from './Show.vue';
import CommentForm from '../../Components/CommentForm.vue';

const task = {
    id: 9,
    title: 'Ship the release',
    description: 'Tag, build, publish.',
    status: 'in_progress',
    priority: 'high',
    project: { id: 3, name: 'TaskFlow' },
    assignee: null,
};

const comments = [
    {
        id: 1,
        body: 'Ready for review @grace-hopper',
        created_at: '2026-09-26T10:00:00.000000Z',
        user: { id: 7, name: 'Ada Lovelace' },
    },
];

describe('Tasks/Show.vue', () => {
    it('renders the task details', () => {
        const wrapper = mount(Show, { props: { task, comments: [] } });

        expect(wrapper.text()).toContain('Ship the release');
        expect(wrapper.text()).toContain('Tag, build, publish.');
        expect(wrapper.text()).toContain('TaskFlow');
    });

    it('renders the comment thread and the comment form', () => {
        const wrapper = mount(Show, { props: { task, comments } });

        expect(wrapper.get('[data-test="comment"]').text()).toContain('Ready for review @grace-hopper');
        expect(wrapper.get('textarea').attributes('name')).toBe('body');
        expect(wrapper.getComponent(CommentForm).props('url')).toBe('/tasks/9/comments');
    });

    it('shows the empty state when the task has no comment', () => {
        const wrapper = mount(Show, { props: { task, comments: [] } });

        expect(wrapper.text()).toContain('No comments yet');
    });
});
