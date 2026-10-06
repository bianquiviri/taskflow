import { describe, expect, it, vi } from 'vitest';
import { nextTick, reactive, ref } from 'vue';
import { grant, useCan } from './useCan';

const { holder, pageState } = vi.hoisted(() => {
    const pageState = { url: '/', props: { can: [] } };

    return { holder: { page: null }, pageState };
});

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => {
        holder.page = reactive(pageState);

        return holder.page;
    },
}));

describe('useCan', () => {
    it('reads the permissions shared with every page', () => {
        pageState.props = { can: ['projects.create', 'teams.view'] };

        const { can } = useCan();

        expect(can('projects.create')).toBe(true);
        expect(can('teams.view')).toBe(true);
        expect(can('teams.manageMembers')).toBe(false);
    });

    it('denies every permission when the prop is missing', () => {
        pageState.props = {};

        const { can, canAny, canAll, permissions } = useCan();

        expect(can('projects.create')).toBe(false);
        expect(canAny(['projects.create'])).toBe(false);
        expect(canAll(['projects.create'])).toBe(false);
        expect(permissions.value).toEqual([]);
    });

    it('grants extra permissions supplied by the page', () => {
        pageState.props = { can: ['projects.create'] };

        const { can } = useCan(['tasks.create', 'tasks.assign']);

        expect(can('tasks.create')).toBe(true);
        expect(can('tasks.update')).toBe(false);
    });

    it('merges a reactive list of page level permissions', async () => {
        pageState.props = { can: ['projects.create'] };
        const extra = ref(['tasks.create']);

        const { can } = useCan(extra);

        expect(can('tasks.create')).toBe(true);

        extra.value = [];
        await nextTick();

        expect(can('tasks.create')).toBe(false);
        expect(can('projects.create')).toBe(true);
    });

    it('accepts per entity booleans and keeps the denied ones out', () => {
        pageState.props = { can: ['projects.create'] };

        const { can, permissions } = useCan({
            'projects.update': true,
            'projects.archive': false,
        });

        expect(can('projects.update')).toBe(true);
        expect(can('projects.archive')).toBe(false);
        expect(permissions.value).toEqual(['projects.create', 'projects.update']);
    });

    it('answers canAny and canAll against the merged list', () => {
        pageState.props = { can: ['projects.viewAny', 'projects.create'] };

        const { canAny, canAll } = useCan({ 'projects.update': true });

        expect(canAny(['projects.update', 'teams.manageMembers'])).toBe(true);
        expect(canAll(['projects.update', 'teams.manageMembers'])).toBe(false);
        expect(canAll(['projects.viewAny', 'projects.create'])).toBe(true);
        expect(canAll([])).toBe(true);
    });

    it('reacts to permission changes without remounting', async () => {
        pageState.props = { can: ['projects.create'] };

        const { can } = useCan();
        expect(can('teams.view')).toBe(false);

        holder.page.props = { can: ['projects.create', 'teams.view'] };
        await nextTick();

        expect(can('teams.view')).toBe(true);
    });

    it('ignores malformed entries in the shared prop', () => {
        pageState.props = { can: ['projects.create', 42, null, ''] };

        const { permissions } = useCan();

        expect(permissions.value).toEqual(['projects.create']);
    });
});

describe('grant', () => {
    it('keeps only the enabled keys of a boolean map', () => {
        expect(grant({ a: true, b: false, c: 1, d: 0, e: null })).toEqual(['a', 'c']);
    });

    it('keeps only the string entries of a list', () => {
        expect(grant(['a', '', null, 7, 'b'])).toEqual(['a', 'b']);
    });

    it('returns an empty list for missing input', () => {
        expect(grant(undefined)).toEqual([]);
        expect(grant(null)).toEqual([]);
    });
});
