import { beforeEach, describe, expect, it, vi } from 'vitest';

const { patch } = vi.hoisted(() => ({ patch: vi.fn() }));

vi.mock('@inertiajs/vue3', () => ({ router: { patch } }));

import { useTaskStatusChange, withTaskStatus } from './useTaskStatusChange';

const task = { id: 7, title: 'Fix the login redirect', status: 'todo' };
const other = { id: 8, title: 'Write the release notes', status: 'done' };

function paginator(data) {
    return {
        data,
        total: 8,
        current_page: 1,
        last_page: 2,
        links: [{ url: '/projects/1?page=2', label: '2', active: false }],
    };
}

function optionsOf(call = 0) {
    return patch.mock.calls[call][2];
}

beforeEach(() => {
    patch.mockReset();
});

describe('useTaskStatusChange', () => {
    it('paints the new status at once through an optimistic visit', () => {
        const { changeStatus } = useTaskStatusChange();

        changeStatus(task, 'in_progress');

        expect(patch).toHaveBeenCalledTimes(1);
        expect(patch.mock.calls[0][0]).toBe('/tasks/7/status');
        expect(patch.mock.calls[0][1]).toEqual({ status: 'in_progress' });
        expect(optionsOf().optimistic({ tasks: paginator([task, other]) })).toEqual({
            tasks: paginator([{ ...task, status: 'in_progress' }, other]),
        });
    });

    it('keeps the pagination of the listing and the other tasks untouched', () => {
        const { changeStatus } = useTaskStatusChange();

        changeStatus(task, 'done');

        const patched = optionsOf().optimistic({ tasks: paginator([task, other]) }).tasks;

        expect(patched.total).toBe(8);
        expect(patched.last_page).toBe(2);
        expect(patched.links).toHaveLength(1);
        expect(patched.data[1]).toEqual(other);
    });

    it('keeps the scroll position and the page state of the visit', () => {
        useTaskStatusChange().changeStatus(task, 'done');

        expect(optionsOf().preserveScroll).toBe(true);
        expect(optionsOf().preserveState).toBe(true);
    });

    it('lets a page patch another prop, as the task page does with its task', () => {
        const { changeStatus } = useTaskStatusChange({
            apply: (props, changed, status) => ({ task: { ...props.task, status } }),
        });

        changeStatus(task, 'done');

        expect(optionsOf().optimistic({ task })).toEqual({ task: { ...task, status: 'done' } });
    });

    it('leaves a page without a task listing alone', () => {
        useTaskStatusChange().changeStatus(task, 'done');

        expect(optionsOf().optimistic({ project: { id: 1 } })).toEqual({});
    });

    it('ignores a status the task already has', () => {
        useTaskStatusChange().changeStatus(task, 'todo');

        expect(patch).not.toHaveBeenCalled();
    });

    it('ignores a change the user is not allowed to make', () => {
        useTaskStatusChange({ canChange: () => false }).changeStatus(task, 'done');

        expect(patch).not.toHaveBeenCalled();
    });

    it('ignores a second change while the first is still in flight', () => {
        const { changeStatus, isPending } = useTaskStatusChange();

        changeStatus(task, 'in_review');
        changeStatus(task, 'cancelled');

        expect(patch).toHaveBeenCalledTimes(1);
        expect(isPending(7)).toBe(true);
    });

    it('forgets a task once its visit finishes', () => {
        const { changeStatus, isPending } = useTaskStatusChange();

        changeStatus(task, 'done');
        optionsOf().onFinish();

        expect(isPending(7)).toBe(false);
    });

    it('reports the first error of a refused change', () => {
        const { changeStatus, error } = useTaskStatusChange();

        changeStatus(task, 'done');
        optionsOf().onError({ status: 'The selected status is invalid.' });

        expect(error.value).toBe('The selected status is invalid.');
    });

    it('clears the error of a previous change when a new one starts', () => {
        const { changeStatus, error } = useTaskStatusChange();

        changeStatus(task, 'done');
        optionsOf().onError({ status: 'The selected status is invalid.' });
        changeStatus(other, 'todo');

        expect(error.value).toBe('');
    });
});

describe('withTaskStatus', () => {
    it('keeps a listing without rows as it is', () => {
        expect(withTaskStatus({ data: [] }, 7, 'done')).toEqual({ data: [] });
    });

    it('ignores a listing it cannot read', () => {
        expect(withTaskStatus(null, 7, 'done')).toBeNull();
    });
});
