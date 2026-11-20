import { beforeEach, describe, expect, it, vi } from 'vitest';
import { useListQuery } from '@/composables/useListQuery';
import type { ListMeta } from '@/types';

const router = vi.hoisted(() => ({ get: vi.fn() }));
vi.mock('@inertiajs/vue3', () => ({
    router,
    usePage: () => ({ url: '/master-data/platforms?search=old&page=3' }),
}));

const meta = (over: Partial<ListMeta> = {}): ListMeta => ({
    search: '',
    sort: 'order',
    direction: 'asc',
    per_page: 25,
    per_page_options: [10, 25],
    sorts: [
        { key: 'order', label: 'Urutan' },
        { key: 'name', label: 'Nama' },
    ],
    filters: [
        {
            key: 'status',
            label: 'Status',
            type: 'select',
            options: [],
            primary: true,
            value: null,
        },
    ],
    active_count: 0,
    ...over,
});

beforeEach(() => router.get.mockReset());

describe('master data list in the query string', () => {
    it('keeps the super admin company on every visit, together with the filter and sort', () => {
        const query = useListQuery(
            () => meta(),
            ['items', 'list'],
            () => ({ tenant_id: '7' }),
        );

        query.setFilter('status', 'inactive');

        expect(router.get).toHaveBeenCalledWith(
            '/master-data/platforms',
            {
                tenant_id: '7',
                filter: { status: 'inactive' },
                sort: 'order',
                direction: 'asc',
                per_page: 25,
            },
            expect.objectContaining({
                only: ['items', 'list'],
                preserveState: true,
                replace: true,
            }),
        );
    });

    it('goes back to the first page on a change and carries the page number only after page 1', () => {
        const query = useListQuery(() => meta(), ['items']);

        query.goToPage(1);
        expect(router.get.mock.calls[0][1]).not.toHaveProperty('page');
        query.goToPage(3);
        expect(router.get.mock.calls[1][1]).toMatchObject({ page: 3 });
        query.setFilter('status', 'active');
        expect(router.get.mock.calls[2][1]).not.toHaveProperty('page');
    });

    it('flips the direction when the same sort is chosen again', () => {
        const query = useListQuery(
            () => meta({ sort: 'name', direction: 'asc' }),
            ['items'],
        );

        query.toggleSort('name');
        expect(router.get.mock.calls[0][1]).toMatchObject({
            sort: 'name',
            direction: 'desc',
        });
        query.toggleSort('order');
        expect(router.get.mock.calls[1][1]).toMatchObject({
            sort: 'order',
            direction: 'asc',
        });
    });

    it('clears search and filters in one visit', () => {
        const query = useListQuery(
            () =>
                meta({
                    search: 'x',
                    filters: [
                        {
                            key: 'status',
                            label: 'S',
                            type: 'select',
                            options: [],
                            primary: true,
                            value: 'inactive',
                        },
                    ],
                }),
            ['items'],
            () => ({ tenant_id: '7' }),
        );

        query.clearAll();

        expect(router.get.mock.calls[0][1]).toEqual({
            tenant_id: '7',
            sort: 'order',
            direction: 'asc',
            per_page: 25,
        });
        expect(query.search.value).toBe('');
    });

    it('starts from the search the server echoed back', () => {
        expect(
            useListQuery(() => meta({ search: 'tix' }), ['items']).search.value,
        ).toBe('tix');
    });
});
