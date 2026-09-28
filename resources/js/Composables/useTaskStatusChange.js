import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

/**
 * A copy of a task listing with the status of one task replaced, keeping the
 * pagination of the page around it.
 *
 * @param {{data: object[]}|null} tasks
 * @param {number} taskId
 * @param {string} status
 */
export function withTaskStatus(tasks, taskId, status) {
    if (!tasks?.data) {
        return tasks;
    }

    return {
        ...tasks,
        data: tasks.data.map((task) => (task.id === taskId ? { ...task, status } : task)),
    };
}

function patchTaskListing(props, task, status) {
    return props.tasks?.data ? { tasks: withTaskStatus(props.tasks, task.id, status) } : {};
}

function firstError(errors) {
    const [first] = Object.values(errors ?? {});

    return typeof first === 'string' ? first : '';
}

/**
 * The status change behind the board drop and the inline status controls. The
 * new status is painted at once and Inertia restores the page baseline itself
 * when the server refuses the move, so this only guards the change, remembers
 * what is in flight and reports the refusal.
 */
export function useTaskStatusChange({ canChange = () => true, apply = patchTaskListing } = {}) {
    const pending = ref([]);
    const error = ref('');

    function isPending(taskId) {
        return pending.value.includes(taskId);
    }

    function changeStatus(task, status) {
        if (!task || !status || status === task.status || isPending(task.id) || !canChange(task)) {
            return;
        }

        pending.value = [...pending.value, task.id];
        error.value = '';

        router.patch(`/tasks/${task.id}/status`, { status }, {
            preserveScroll: true,
            preserveState: true,
            optimistic: (props) => apply(props, task, status),
            onError: (errors) => {
                error.value = firstError(errors);
            },
            onFinish: () => {
                pending.value = pending.value.filter((id) => id !== task.id);
            },
        });
    }

    return { changeStatus, error, isPending };
}
