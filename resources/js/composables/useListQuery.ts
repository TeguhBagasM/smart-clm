import { router, usePage } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import type { InjectionKey, Ref } from 'vue';
import { inject, provide, ref, watch } from 'vue';
import type { DateRangeValue, FilterValue, ListMeta } from '@/types';

type Visit = {
    search: string;
    sort: string;
    direction: 'asc' | 'desc';
    per_page: number;
    filters: Record<string, FilterValue>;
    page?: number;
};

export type ListQuery = ReturnType<typeof useListQuery>;

const key: InjectionKey<ListQuery> = Symbol('list-query');

export function isEmptyFilter(value: FilterValue): boolean {
    if (value === null || value === '') return true;
    if (Array.isArray(value)) return value.length === 0;
    if (typeof value === 'object') {
        const range = value as DateRangeValue;
        return !range.from && !range.to;
    }
    return false;
}

/**
 * Keeps a list's search, filters, sort and page in the URL: every change is an
 * Inertia visit that reloads only the list props, and a filter or sort change
 * goes back to page 1. Search waits for a pause in typing.
 */
export function useListQuery(list: () => ListMeta, only: string[]) {
    const search: Ref<string> = ref(list().search);
    const loading = ref(false);
    const page = usePage();

    function visit(patch: Partial<Visit>, pageNumber?: number): void {
        const meta = list();
        const state: Visit = {
            search: search.value,
            sort: meta.sort,
            direction: meta.direction,
            per_page: meta.per_page,
            filters: Object.fromEntries(
                meta.filters.map((filter) => [filter.key, filter.value]),
            ),
            ...patch,
        };
        const filter = Object.fromEntries(
            Object.entries(state.filters).filter(
                ([, value]) => !isEmptyFilter(value),
            ),
        );

        router.get(
            new URL(page.url, 'http://localhost').pathname,
            {
                ...(state.search ? { search: state.search } : {}),
                ...(Object.keys(filter).length ? { filter } : {}),
                sort: state.sort,
                direction: state.direction,
                per_page: state.per_page,
                ...(pageNumber && pageNumber > 1 ? { page: pageNumber } : {}),
            },
            {
                only,
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onStart: () => (loading.value = true),
                onFinish: () => (loading.value = false),
            },
        );
    }

    const searchLater = useDebounceFn(() => visit({}), 300);

    watch(search, (value) => {
        if (value !== list().search) void searchLater();
    });
    watch(
        () => list().search,
        (value) => {
            if (value !== search.value) search.value = value;
        },
    );

    function filterValues(): Record<string, FilterValue> {
        return Object.fromEntries(
            list().filters.map((filter) => [filter.key, filter.value]),
        );
    }

    return {
        search,
        loading,
        setFilter: (name: string, value: FilterValue) =>
            visit({ filters: { ...filterValues(), [name]: value } }),
        clearFilter: (name: string) =>
            visit({ filters: { ...filterValues(), [name]: null } }),
        clearAll: () => {
            search.value = '';
            visit({ search: '', filters: {} });
        },
        setSort: (sort: string) => visit({ sort }),
        toggleSort: (sort: string) =>
            visit({
                sort,
                direction:
                    sort === list().sort && list().direction === 'asc'
                        ? 'desc'
                        : 'asc',
            }),
        setDirection: (direction: 'asc' | 'desc') => visit({ direction }),
        setPerPage: (perPage: number) => visit({ per_page: perPage }),
        goToPage: (pageNumber: number) => visit({}, pageNumber),
    };
}

export function provideListQuery(query: ListQuery): void {
    provide(key, query);
}

export function useListContext(): ListQuery {
    const query = inject(key);

    if (!query) throw new Error('useListContext must be used inside ListPage.');

    return query;
}
