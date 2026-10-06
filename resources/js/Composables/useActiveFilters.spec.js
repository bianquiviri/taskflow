import { describe, expect, it } from 'vitest';
import { effectScope, ref } from 'vue';

import { useActiveFilters } from './useActiveFilters';

const filters = (values) => ref(values);

const read = (ref_) => {
    const scope = effectScope();

    return scope.run(() => useActiveFilters(ref_));
};

describe('useActiveFilters', () => {
    it('is false when every filter is empty', () => {
        expect(read(filters({
            search: null,
            status: null,
            priority: '',
            assignee_id: undefined,
        })).value).toBe(false);
    });

    it('is true as soon as one filter carries a value', () => {
        expect(read(filters({ search: null, status: 'todo' })).value).toBe(true);
        expect(read(filters({ search: null, assignee_id: 4 })).value).toBe(true);
    });

    it('follows the filters as they change', () => {
        const given = filters({ search: null });
        const active = read(given);

        expect(active.value).toBe(false);

        given.value = { search: 'venue' };

        expect(active.value).toBe(true);
    });

    it('survives a listing without filters at all', () => {
        expect(read(filters(null)).value).toBe(false);
    });
});